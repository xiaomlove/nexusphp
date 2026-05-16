<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Database\Seeders\TestingDataSeeder;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/ok.php?type=...` HTML contract.
 *
 * The legacy script was a `mkglobal('type') || die()` switch
 * statement that emitted one of six localised "we are done"
 * messages used by the signup / confirm flows:
 *
 *   - `adminactivate` — "Signup OK, admin must validate next."
 *   - `inviter`       — "Signup OK, inviter must validate next."
 *   - `signup&email=` — "Signup OK, check email <addr>."
 *   - `sysop`         — "Sysop account activated."
 *   - `confirmed`     — "Already confirmed."
 *   - `confirm`       — "Account confirmed."
 *
 * The replacement controller drops the `stdhead()` / `stdfoot()`
 * chrome (every Phase 2 controller does the same — see
 * `RulesController` / `AboutNexusController`), validates the
 * dispatch parameter strictly (404 on unknown / missing /
 * `signup` without an email), and emits the localised copy from
 * `lang/<folder>/lang_ok.php` with English fallback.
 */
class OkControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` of the English row seeded by `LanguageTableSeeder`. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        // The global `Locale` middleware (registered as an HTTP
        // pipeline middleware in `App\Http\Kernel`) calls
        // `Carbon::setLocale(...)`. Without `main.defaultlang` /
        // language rows in the DB, the resolved locale is `null`
        // and Carbon's typed signature throws. Other Feature
        // tests rely on the `LegacyHttpFeatureTestCase` setup to
        // populate these; this suite only uses Laravel's HTTP
        // testing client, so we seed the lookup + settings
        // tables here once if they're empty.
        $defaultsLoaded = DB::table('settings')
            ->where('name', 'main.defaultlang')
            ->exists();
        if (! $defaultsLoaded) {
            (new TestingDataSeeder)->run();
        }

        // The `LogUserIp` middleware (registered globally and only
        // active for authenticated users) reads
        // `$_SERVER['REQUEST_URI']` directly. PHPUnit's HTTP test
        // client never populates this super-global, so the
        // middleware throws `Undefined array key "REQUEST_URI"`
        // before the request reaches the controller. Other Feature
        // tests that hit auth'd routes prime this in the same way
        // (see `TorrentDetailTest::setUp`).
        $_SERVER['REQUEST_URI'] = '/ok.php';
    }

    public function test_unknown_type_returns_404(): void
    {
        $this->get('/ok.php?type=bogus')->assertNotFound();
    }

    public function test_missing_type_returns_404(): void
    {
        $this->get('/ok.php')->assertNotFound();
    }

    public function test_signup_without_email_returns_404(): void
    {
        $this->get('/ok.php?type=signup')->assertNotFound();
    }

    public function test_adminactivate_renders_localised_copy(): void
    {
        $response = $this->get('/ok.php?type=adminactivate');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>User signup</title>', $body);
        $this->assertStringContainsString('<h2>Signup successful but Account not activated!</h2>', $body);
        $this->assertStringContainsString('Admin must validate new members', $body);
    }

    public function test_inviter_renders_localised_copy(): void
    {
        $response = $this->get('/ok.php?type=inviter');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<h2>Signup successful but Account not activated!</h2>', $body);
        $this->assertStringContainsString('your inviter must validate new members', $body);
    }

    public function test_signup_renders_email_with_html_escaping(): void
    {
        $hostile = 'admin@example.test"><script>alert(1)</script>';
        $response = $this->get('/ok.php?type=signup&email='.urlencode($hostile));

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<h2>Signup successful!</h2>', $body);
        $this->assertStringContainsString('A confirmation email has been sent', $body);
        $this->assertStringContainsString(
            'admin@example.test&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;',
            $body,
        );
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
    }

    public function test_sysop_branch_swaps_login_hint_based_on_auth_state(): void
    {
        $guestResponse = $this->get('/ok.php?type=sysop');
        $guestResponse->assertOk();
        $this->assertStringContainsString(
            'cookies in your browser',
            (string) $guestResponse->getContent(),
        );
        $this->assertStringContainsString(
            '<title>Sysop Account activation</title>',
            (string) $guestResponse->getContent(),
        );

        $user = $this->createAuthenticatableUser();
        $authedResponse = $this->actingAs($user, 'nexus-web')->get('/ok.php?type=sysop');
        $authedResponse->assertOk();
        $this->assertStringContainsString(
            'automatically logged in',
            (string) $authedResponse->getContent(),
        );
    }

    public function test_confirmed_renders_already_confirmed_copy(): void
    {
        $response = $this->get('/ok.php?type=confirmed');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>Already confirmed</title>', $body);
        $this->assertStringContainsString('Already confirmed', $body);
        $this->assertStringContainsString('proceed to', $body);
    }

    public function test_confirm_branch_uses_login_hint_and_substitutes_site_name(): void
    {
        // `basic.SITENAME` is seeded by `SettingsTableSeeder`
        // straight from `nexus/Install/settings.default.php`,
        // which fixes it at `NexusPHP`. `Setting::getSiteName()`
        // uses a static-in-function cache (`Setting::get`) keyed
        // on the process, so a per-test `updateOrInsert` would
        // not be observable here even though the row in the DB
        // would change. We assert against the seeded default,
        // which is the same string the controller will see.
        $user = $this->createAuthenticatableUser();
        $response = $this->actingAs($user, 'nexus-web')->get('/ok.php?type=confirm');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>Signup confirmation</title>', $body);
        $this->assertStringContainsString('Account successfully confirmed', $body);
        $this->assertStringContainsString('automatically logged in', $body);
        // `std_read_rules_faq` is "Before you start using %s we urge you ...".
        // The `%s` substitution proves the controller actually
        // sprintf'd `Setting::getSiteName()` (default "NexusPHP")
        // into the line, not just emitted the raw key.
        $this->assertStringContainsString('Before you start using NexusPHP', $body);
        $this->assertStringContainsString('rules.php', $body);
        $this->assertStringContainsString('faq.php', $body);
    }

    public function test_renders_chromeless_html_envelope(): void
    {
        $response = $this->get('/ok.php?type=adminactivate');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringStartsWith('<!DOCTYPE html', $body);
        $this->assertStringContainsString('<head>', $body);
        $this->assertStringContainsString('</html>', $body);
        // The legacy `stdhead()` chrome that we deliberately drop
        // included the global "Powered by NexusPHP" footer — make
        // sure we are not accidentally pulling that in here.
        $this->assertStringNotContainsString('Powered by', $body);
    }

    /**
     * Create a `users` row with a valid `lang` FK to the English
     * `language` row so the global `Locale` middleware can resolve
     * `$user->locale` to a non-null string. Without this, Carbon's
     * typed `setLocale(string)` throws inside the middleware before
     * the request even reaches the controller.
     */
    private function createAuthenticatableUser(): User
    {
        $user = $this->createLegacyUser();
        NexusDB::table('users')
            ->where('id', $user->id)
            ->update(['lang' => self::ENGLISH_LANGUAGE_ID]);
        $user->refresh();

        return $user;
    }
}

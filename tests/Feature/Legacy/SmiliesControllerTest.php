<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/smilies.php` reference-page contract.
 *
 * The legacy script wrapped `insert_smilies_frame()` in `stdhead()`
 * / `stdfoot()`. The migrated controller renders the same 2-column
 * `[emN] → pic/smilies/N.gif` grid in a chrome-less HTML envelope
 * (matching the `MoreSmiliesController` precedent).
 *
 * Mirrors the test patterns established by ThanksControllerTest /
 * MoreSmiliesControllerTest (seed `$_SERVER['REQUEST_URI']`, pin the
 * English language id on the test user) — see
 * `docs/migration-recipe.md` § "Common test pitfalls".
 */
class SmiliesControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        // `LogUserIp` global middleware reads `$_SERVER['REQUEST_URI']`
        // directly — the Laravel test HTTP client does not populate it.
        $_SERVER['REQUEST_URI'] = '/smilies.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/smilies.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location')
        );
    }

    public function test_authed_request_returns_smiley_reference_grid(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/smilies.php');

        $response->assertOk();
        $body = (string) $response->getContent();

        // Header label + the legacy two-column "Type... / To make a..."
        // table headers must be present.
        $this->assertStringContainsString('Smilies', $body);
        $this->assertStringContainsString('Type...', $body);
        $this->assertStringContainsString('To make a...', $body);

        // Verify every `[emN]` token + `pic/smilies/N.gif` reference
        // is emitted, exactly like the legacy `insert_smilies_frame()`.
        $this->assertStringContainsString('[em1]', $body);
        $this->assertStringContainsString('pic/smilies/1.gif', $body);
        $this->assertStringContainsString('[em96]', $body);
        $this->assertStringContainsString('pic/smilies/96.gif', $body);
        $this->assertStringContainsString('[em191]', $body);
        $this->assertStringContainsString('pic/smilies/191.gif', $body);
        $this->assertStringNotContainsString('pic/smilies/192.gif', $body);
    }

    /**
     * The default `createLegacyUser` does not set `lang`; the `Locale`
     * middleware that runs after `auth.nexus` reads
     * `$user->language?->site_lang_folder` and forwards it to
     * `Carbon::setLocale()`, which throws on null. Pin a known
     * language id so the middleware stack stays happy.
     */
    private function createTestUser(): User
    {
        return $this->createLegacyUser(
            overrides: ['lang' => self::ENGLISH_LANGUAGE_ID],
        );
    }
}

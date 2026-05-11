<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/moresmilies.php` popup contract.
 *
 * The legacy script rendered a chrome-less HTML popup with a
 * `SmileIT()` JavaScript callback that injects the picked smiley
 * token back into the calling form. The compose helpers in
 * `include/functions.php:985` open the popup via
 * `window.open("moresmilies.php?form=<f>&text=<t>", ...)`, so the
 * URL contract — query params plus the inline `SmileIT()` calls —
 * must stay stable.
 */
class MoreSmiliesControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        // `LogUserIp` global middleware reads `$_SERVER['REQUEST_URI']`
        // directly — the Laravel test HTTP client does not populate
        // it. Same pattern as ThanksControllerTest / PreviewControllerTest.
        $_SERVER['REQUEST_URI'] = '/moresmilies.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/moresmilies.php?form=compose&text=body');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location')
        );
    }

    public function test_authed_request_returns_full_smiley_grid(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/moresmilies.php?form=compose&text=body');

        $response->assertOk();
        $body = (string) $response->getContent();

        // Page wrapper + JavaScript callback.
        $this->assertStringContainsString('<title>More Clickable Smilies</title>', $body);
        $this->assertStringContainsString('function SmileIT(smile,form,text)', $body);
        $this->assertStringContainsString('window.opener.document.forms[form]', $body);

        // Verify the form/text query params are echoed into the
        // `SmileIT('[em1]', '<form>', '<text>')` call.
        $this->assertStringContainsString("SmileIT('[em1]','compose','body')", $body);

        // Verify all 191 smiley image references are present, on the
        // exact `pic/smilies/<N>.gif` URLs the legacy file emitted.
        $this->assertStringContainsString('pic/smilies/1.gif', $body);
        $this->assertStringContainsString('pic/smilies/96.gif', $body);
        $this->assertStringContainsString('pic/smilies/191.gif', $body);
        $this->assertStringNotContainsString('pic/smilies/192.gif', $body);

        // Close link.
        $this->assertStringContainsString('javascript: window.close()', $body);
        $this->assertStringContainsString('>Close<', $body);
    }

    public function test_query_params_are_html_escaped(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/moresmilies.php?form='.urlencode('"><script>alert(1)</script>').'&text=t');

        $response->assertOk();
        $body = (string) $response->getContent();

        // The legacy script wrapped the params in
        // `htmlspecialchars()` before emitting them into the inline
        // JS — the migrated controller MUST keep that escape so a
        // crafted `form=` value can't break out of the `SmileIT()`
        // call.
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&quot;&gt;&lt;script&gt;', $body);
    }

    public function test_missing_query_params_still_render(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/moresmilies.php');

        $response->assertOk();
        $body = (string) $response->getContent();

        // No `form`/`text` → empty-string callbacks; legacy behaviour
        // (the popup just won't work, but the page still renders).
        $this->assertStringContainsString("SmileIT('[em1]','','')", $body);
    }

    /**
     * Pin a known language id so the `Locale` middleware doesn't
     * crash on `Carbon::setLocale(null)` — same trick as the other
     * Phase 2 controllers.
     */
    private function createTestUser(): User
    {
        return $this->createLegacyUser(
            overrides: ['lang' => self::ENGLISH_LANGUAGE_ID],
        );
    }
}

<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Nexus\Plugin\Hook;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/staffmess.php` contract.
 *
 * Administrator-only mass-PM form (paired with the existing
 * `TakeStaffMessController` write-handler).
 *
 * Legacy semantics:
 *   - Guest → login redirect.
 *   - Below administrator → 403 (legacy was HTTP 200 / `stderr()`).
 *   - GET → 200 form posting to `/takestaffmess.php`.
 *   - `?sent=1` → confirmation banner.
 *   - `?returnto=<url>` → hidden `returnto` input echoes (HTML-escaped)
 *     so a downstream cancel/back action can route the user back.
 *   - Falls back to the `Referer` header when `?returnto` is absent.
 *   - `do_action('form_role_filter', 'Send to Role:')` is preserved
 *     verbatim so plugin callbacks that echo extra form rows still
 *     render at the legacy splice point.
 */
class StaffMessControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/staffmess.php';
    }

    protected function tearDown(): void
    {
        // `Nexus\Plugin\Hook` keeps its callback registry in a
        // private static property; reset it via reflection so a
        // plugin registered by one test doesn't leak into the next.
        $reflection = new \ReflectionClass(Hook::class);
        $callbacks = $reflection->getProperty('callbacks');
        $callbacks->setAccessible(true);
        $callbacks->setValue(null, []);

        parent::tearDown();
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/staffmess.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_non_administrator_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/staffmess.php')->assertForbidden();
    }

    public function test_administrator_get_renders_form(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/staffmess.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>Mass PM</title>', $body);
        // Form still posts to `/takestaffmess.php` so the existing
        // `TakeStaffMessController` write-handler keeps receiving
        // POSTs without a template change.
        $this->assertStringContainsString('action="takestaffmess.php"', $body);
        $this->assertStringContainsString('name="subject"', $body);
        $this->assertStringContainsString('name="msg"', $body);
        $this->assertStringContainsString('name="classes[]"', $body);
        // Sender radio (self / system).
        $this->assertStringContainsString('name="sender"', $body);
        $this->assertStringContainsString('value="self"', $body);
        $this->assertStringContainsString('value="system"', $body);
        // The class checkbox grid is driven by `User::$classes` —
        // pick two well-known labels that should always be there.
        $this->assertStringContainsString('Power User', $body);
        $this->assertStringContainsString('Administrator', $body);
    }

    public function test_sent_query_renders_confirmation_banner(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $body = (string) $this->get('/staffmess.php?sent=1')->getContent();
        $this->assertStringContainsString('The message has been sent.', $body);

        $bodyWithout = (string) $this->get('/staffmess.php')->getContent();
        $this->assertStringNotContainsString('The message has been sent.', $bodyWithout);
    }

    public function test_returnto_query_is_preserved_as_hidden_input(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $body = (string) $this->get('/staffmess.php?returnto=/userdetails.php%3Fid%3D42')
            ->getContent();

        $this->assertStringContainsString('name="returnto"', $body);
        $this->assertStringContainsString('value="/userdetails.php?id=42"', $body);
    }

    public function test_referer_header_is_used_when_returnto_missing(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $body = (string) $this->withHeader('Referer', '/userdetails.php?id=99')
            ->get('/staffmess.php')
            ->getContent();

        $this->assertStringContainsString('name="returnto"', $body);
        $this->assertStringContainsString('value="/userdetails.php?id=99"', $body);
    }

    public function test_returnto_is_html_escaped(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $body = (string) $this->get('/staffmess.php?returnto='.urlencode('"><script>alert(1)</script>'))
            ->getContent();

        // Raw payload must not appear; escaped form must.
        $this->assertStringNotContainsString('"><script>alert(1)</script>', $body);
        $this->assertStringContainsString('&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    public function test_no_hidden_returnto_when_neither_query_nor_referer(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $body = (string) $this->get('/staffmess.php')->getContent();

        // The form must not emit an empty `value=""` returnto.
        $this->assertStringNotContainsString('name="returnto"', $body);
    }

    public function test_form_role_filter_hook_is_called(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $marker = 'plugin-form-role-filter-marker-'.bin2hex(random_bytes(4));
        add_action('form_role_filter', function (string $label) use ($marker): void {
            echo '<tr><td>'.$marker.':'.$label.'</td></tr>';
        });

        $body = (string) $this->get('/staffmess.php')->getContent();

        $this->assertStringContainsString($marker.':Send to Role:', $body);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(
                ['lang' => self::ENGLISH_LANGUAGE_ID],
                $overrides,
            ),
        );
    }
}

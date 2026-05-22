<?php

namespace Tests\Feature\Legacy;

use App\Models\Setting;
use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class DonateControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/donate.php';
    }

    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(
                ['lang' => self::ENGLISH_LANGUAGE_ID],
                $overrides,
            ),
        );
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/donate.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_donation_disabled_shows_message(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        // Ensure donation is disabled
        Setting::set('main.donation', 'no');

        $response = $this->get('/donate.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('do not accept donations', $body);
    }

    public function test_do_thanks_renders_success_page(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        Setting::set('main.donation', 'yes');
        Setting::set('main.ACCOUNTANTID', '1');

        $response = $this->get('/donate.php?do=thanks');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Success', $body);
        $this->assertStringContainsString('sendmessage.php?receiver=1', $body);
    }

    public function test_no_accounts_configured_shows_error(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        Setting::set('main.donation', 'yes');
        Setting::set('main.PAYPALACCOUNT', '');
        Setting::set('main.ALIPAYACCOUNT', '');
        Setting::set('misc.donation_custom', '');

        $response = $this->get('/donate.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('No donation account', $body);
    }

    public function test_paypal_form_rendered_when_configured(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        Setting::set('main.donation', 'yes');
        Setting::set('main.PAYPALACCOUNT', 'donate@example.com');
        Setting::set('main.ALIPAYACCOUNT', '');
        Setting::set('misc.donation_custom', '');
        Setting::set('basic.BASEURL', 'example.com');

        $response = $this->get('/donate.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('PayPal', $body);
        $this->assertStringContainsString('donate@example.com', $body);
        $this->assertStringContainsString('paypal.com', $body);
    }
}

<?php

namespace Tests\Feature\Legacy;

use App\Services\DonateService;
use Tests\FeatureTestCase;

class DonateControllerTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/donate.php';
    }

    public function test_donation_disabled_returns_503_with_sorry_page(): void
    {
        $this->fakeService(['enabled' => false]);

        $response = $this->get('/donate.php');

        $response->assertStatus(503);
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Sorry', $body);
        $this->assertStringContainsString('does not accept donations', $body);
    }

    public function test_thanks_page_renders_link_to_accountant(): void
    {
        $this->fakeService([
            'enabled' => true,
            'accountantId' => 42,
        ]);

        $response = $this->get('/donate.php?do=thanks');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Success', $body);
        $this->assertStringContainsString('sendmessage.php?receiver=42', $body);
    }

    public function test_renders_paypal_form_when_paypal_account_set(): void
    {
        $this->fakeService([
            'enabled' => true,
            'paypal' => 'pp@example.com',
            'alipay' => null,
            'accountantId' => 1,
            'siteName' => 'Acme Tracker',
        ]);

        $response = $this->get('/donate.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Donate with PayPal', $body);
        $this->assertStringContainsString('value="pp@example.com"', $body);
        $this->assertStringContainsString('paypal.com/cgi-bin/webscr', $body);
        $this->assertStringContainsString('value="30.00"', $body);
        $this->assertStringContainsString('/donate.php?do=thanks', $body);
        $this->assertStringNotContainsString('Donate with Alipay', $body);
        $this->assertStringContainsString('sendmessage.php?receiver=1', $body);
        $this->assertStringContainsString('Acme Tracker', $body);
    }

    public function test_renders_alipay_form_when_alipay_account_set(): void
    {
        $this->fakeService([
            'enabled' => true,
            'paypal' => null,
            'alipay' => 'ali@example.com',
        ]);

        $response = $this->get('/donate.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Donate with Alipay', $body);
        $this->assertStringContainsString('ali@example.com', $body);
        $this->assertStringContainsString('alipay.com/trade/fast_pay.htm', $body);
        $this->assertStringNotContainsString('Donate with PayPal', $body);
    }

    public function test_renders_both_accounts_side_by_side_with_width_50_percent(): void
    {
        $this->fakeService([
            'enabled' => true,
            'paypal' => 'pp@example.com',
            'alipay' => 'ali@example.com',
        ]);

        $response = $this->get('/donate.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Donate with PayPal', $body);
        $this->assertStringContainsString('Donate with Alipay', $body);
        $this->assertStringContainsString('width="50%"', $body);
        $this->assertStringNotContainsString('colspan="2" width="100%"', $body);
    }

    public function test_returns_error_when_no_account_or_custom_message_configured(): void
    {
        $this->fakeService([
            'enabled' => true,
            'paypal' => null,
            'alipay' => null,
            'custom' => '',
        ]);

        $response = $this->get('/donate.php');

        $response->assertStatus(503);
        $body = (string) $response->getContent();
        $this->assertStringContainsString('No donation account is available', $body);
    }

    public function test_renders_custom_message_when_only_custom_configured(): void
    {
        $this->fakeService([
            'enabled' => true,
            'paypal' => null,
            'alipay' => null,
            'custom' => 'Wire transfer details: ACME-BANK-12345',
        ]);

        $response = $this->get('/donate.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('ACME-BANK-12345', $body);
    }

    public function test_site_name_is_html_escaped(): void
    {
        $this->fakeService([
            'enabled' => true,
            'paypal' => 'evil@example.com',
            'alipay' => null,
            'siteName' => '<script>alert(1)</script>',
        ]);

        $response = $this->get('/donate.php');

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    public function test_return_url_uses_base_url_with_protocol(): void
    {
        $this->fakeService([
            'enabled' => true,
            'paypal' => 'pp@example.com',
            'base' => 'https://tracker.test',
        ]);

        $response = $this->get('/donate.php');

        $body = (string) $response->getContent();
        $this->assertStringContainsString('https://tracker.test/donate.php?do=thanks', $body);
        $this->assertStringContainsString('https://tracker.test/donate.php', $body);
    }

    /**
     * @param  array{
     *     enabled?: bool,
     *     paypal?: ?string,
     *     alipay?: ?string,
     *     accountantId?: int,
     *     custom?: string,
     *     siteName?: string,
     *     base?: string,
     * }  $overrides
     */
    private function fakeService(array $overrides): void
    {
        $defaults = [
            'enabled' => true,
            'paypal' => null,
            'alipay' => null,
            'accountantId' => 1,
            'custom' => '',
            'siteName' => 'Test Site',
            'base' => 'https://example.test',
        ];
        $config = array_merge($defaults, $overrides);

        $stub = new class($config) extends DonateService
        {
            /** @param array<string,mixed> $config */
            public function __construct(private readonly array $config) {}

            public function isEnabled(): bool
            {
                return (bool) $this->config['enabled'];
            }

            public function accountantId(): int
            {
                return (int) $this->config['accountantId'];
            }

            public function paypalAccount(): ?string
            {
                return $this->config['paypal'];
            }

            public function alipayAccount(): ?string
            {
                return $this->config['alipay'];
            }

            public function customMessage(): string
            {
                return (string) $this->config['custom'];
            }

            public function siteName(): string
            {
                return (string) $this->config['siteName'];
            }

            public function baseUrlWithProtocol(): string
            {
                return (string) $this->config['base'];
            }
        };

        $this->app->instance(DonateService::class, $stub);
    }
}

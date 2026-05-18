<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\Email;

class DonateService
{
    public const DONATION_USD_AMOUNTS = [1, 5, 10, 15, 20, 30, 40, 50, 60, 100, 300];

    public function isEnabled(): bool
    {
        return (string) Setting::get('main.donation') === 'yes';
    }

    public function accountantId(): int
    {
        return (int) Setting::get('main.ACCOUNTANTID');
    }

    public function paypalAccount(): ?string
    {
        return $this->sanitizedEmail((string) Setting::get('main.PAYPALACCOUNT'));
    }

    public function alipayAccount(): ?string
    {
        return $this->sanitizedEmail((string) Setting::get('main.ALIPAYACCOUNT'));
    }

    public function customMessage(): string
    {
        $custom = (string) Setting::getByName('misc.donation_custom');
        $custom = trim($custom);
        if ($custom === '') {
            return '';
        }
        if (function_exists('format_comment')) {
            return (string) call_user_func('format_comment', $custom);
        }

        return htmlspecialchars($custom, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function siteName(): string
    {
        return (string) Setting::getSiteName();
    }

    public function baseUrlWithProtocol(): string
    {
        $base = Setting::getBaseUrl();
        $prefix = function_exists('get_protocol_prefix')
            ? (string) call_user_func('get_protocol_prefix')
            : (request()?->isSecure() ? 'https://' : 'http://');

        return $prefix.$base;
    }

    /**
     * @return list<int>
     */
    public function donationAmounts(): array
    {
        return self::DONATION_USD_AMOUNTS;
    }

    private function sanitizedEmail(string $raw): ?string
    {
        $email = Email::sanitizeForDisplay($raw);
        if ($email === '') {
            return null;
        }
        if (! Email::isWellFormed($email)) {
            return null;
        }

        return $email;
    }
}

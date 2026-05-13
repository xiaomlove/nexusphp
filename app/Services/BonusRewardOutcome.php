<?php

namespace App\Services;

use App\Models\Reward;

/**
 * Typed outcome of {@see BonusRewardService::attempt()}.
 *
 * Each named constructor encodes one branch of the legacy
 * `public/magic.php` script. The user-facing `$message` strings are
 * copied verbatim from the legacy file so the JS client
 * (`public/js/common.js#saveMagicValue`) keeps rendering the same
 * `alert()` text it has shown for years — wire-level backward
 * compatibility is part of the Phase 3 contract.
 */
final class BonusRewardOutcome
{
    private function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly ?Reward $reward,
    ) {}

    public static function success(Reward $reward): self
    {
        return new self(true, 'OK', $reward);
    }

    public static function invalidValue(): self
    {
        return new self(false, 'Invalid value.', null);
    }

    public static function insufficientBonus(): self
    {
        return new self(false, 'You do not have such bonus!', null);
    }

    public static function invalidTorrent(): self
    {
        return new self(false, 'Invalid torrent id!', null);
    }

    public static function invalidOwner(): self
    {
        return new self(false, 'Invalid torrent owner!', null);
    }

    public static function selfReward(): self
    {
        return new self(false, 'You are giving magic to yourself.', null);
    }

    public static function alreadyGiven(): self
    {
        return new self(false, 'You already gave the magic value!', null);
    }

    public static function dailyLimitReached(): self
    {
        return new self(false, 'You already reach times limit!', null);
    }
}

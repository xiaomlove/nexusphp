<?php

namespace App\Services;

use App\Models\BonusLogs;
use App\Models\Reward;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\RewardRepository;
use Nexus\Database\NexusDB;

/**
 * "Give magic" — the legacy `public/magic.php` workflow, hoisted into
 * a typed service so that the Laravel controller can stay thin and
 * the JSON contract has a single source of truth.
 *
 * Phase 3 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 3 — big user pages". Unlike Phase 2 (which mostly wraps
 * legacy pages in a Laravel controller), Phase 3 fully owns the
 * domain semantics:
 *
 *   - The 5 mutations (`magic` insert, `seedbonus` decrement on the
 *     rewarder, `bonus_logs` row for the rewarder, `seedbonus`
 *     increment on the owner, `bonus_logs` row for the owner) run
 *     inside a single `NexusDB::transaction()`. The legacy script
 *     fired them as 5 independent statements, so a crash mid-way
 *     (FPM worker OOM, DB connection drop, etc.) used to leave the
 *     books inconsistent — e.g. `magic` row inserted but the
 *     rewarder's `seedbonus` was never debited.
 *
 *   - `seedbonus` is mutated with `decrement` / `increment`, which
 *     emit `seedbonus = seedbonus ± N` SQL. This matches the legacy
 *     `KPS()` semantics from `include/functions.php` line 1359
 *     exactly (`KPS` is also unconditional, modulo the
 *     `$bonus_tweak` global which both sides honour identically).
 *
 *   - Pre-flight checks (bonus balance, self-reward, idempotency,
 *     daily limit) all live in `attempt()` and return a typed
 *     {@see BonusRewardOutcome}. The controller maps each outcome
 *     to the legacy JSON `{ret, msg, data}` shape that
 *     `public/js/common.js#saveMagicValue` expects.
 *
 * Important: this service intentionally does NOT call
 * {@see RewardRepository::store()} even though
 * that method does ~80 % of the same thing. The repository (a) does
 * not emit `bonus_logs` rows, so the audit trail would silently
 * disappear vs. legacy parity, and (b) raises `LogicException`s
 * with English messages that don't match the JSON contract the JS
 * client renders verbatim in `alert(res.msg)`. The repository keeps
 * serving the JSON-API endpoint `RewardController@store`; this
 * service serves the legacy front-end.
 */
class BonusRewardService
{
    /**
     * Attempt to grant `value` bonus from `$rewarder` to the owner
     * of torrent `$torrentId`. Returns a typed outcome — the caller
     * is responsible for shaping the wire response.
     *
     * @param  positive-int  $torrentId
     * @param  positive-int  $value
     */
    public function attempt(User $rewarder, int $torrentId, int $value): BonusRewardOutcome
    {
        // The legacy script accepted any integer value from the
        // POST body and only later compared it against the rewarder
        // balance. We also surface "value not in the configured
        // option set" as a typed outcome instead of relying on the
        // FormRequest, so this service is safe to call from
        // background jobs, console commands, etc.
        if (! in_array($value, $this->bonusOptions(), strict: true)) {
            return BonusRewardOutcome::invalidValue();
        }

        if ($value > (int) $rewarder->seedbonus) {
            return BonusRewardOutcome::insufficientBonus();
        }

        $torrent = NexusDB::table('torrents')
            ->where('id', $torrentId)
            ->select(['id', 'owner'])
            ->first();
        if ($torrent === null) {
            return BonusRewardOutcome::invalidTorrent();
        }
        $ownerId = (int) ((array) $torrent)['owner'];

        if ($ownerId === (int) $rewarder->id) {
            return BonusRewardOutcome::selfReward();
        }

        $alreadyGiven = NexusDB::table('magic')
            ->where('torrentid', $torrentId)
            ->where('userid', (int) $rewarder->id)
            ->exists();
        if ($alreadyGiven) {
            return BonusRewardOutcome::alreadyGiven();
        }

        if ($this->dailyLimitReached((int) $rewarder->id)) {
            return BonusRewardOutcome::dailyLimitReached();
        }

        $owner = User::query()->find($ownerId);
        if ($owner === null) {
            return BonusRewardOutcome::invalidOwner();
        }

        $reward = NexusDB::transaction(function () use ($rewarder, $owner, $torrentId, $value): Reward {
            /** @var Reward $reward */
            $reward = Reward::query()->create([
                'torrentid' => $torrentId,
                'userid' => (int) $rewarder->id,
                'value' => $value,
            ]);

            $rewarderOldBonus = (float) $rewarder->seedbonus;
            User::query()->where('id', $rewarder->id)->decrement('seedbonus', $value);
            BonusLogs::add(
                (int) $rewarder->id,
                $rewarderOldBonus,
                (float) $value,
                $rewarderOldBonus - $value,
                '',
                BonusLogs::BUSINESS_TYPE_REWARD_TORRENT,
            );

            $ownerOldBonus = (float) $owner->seedbonus;
            User::query()->where('id', $owner->id)->increment('seedbonus', $value);
            BonusLogs::add(
                (int) $owner->id,
                $ownerOldBonus,
                (float) $value,
                $ownerOldBonus + $value,
                '',
                BonusLogs::BUSINESS_TYPE_TORRENT_BE_REWARD,
            );

            return $reward;
        });

        return BonusRewardOutcome::success($reward);
    }

    /**
     * The list of allowed `value` amounts a user can pick from when
     * rewarding a torrent. Configurable via
     * `Setting::get('torrent.reward_bonus_options')`, falls back to
     * the model defaults — both paths can return string entries, so
     * we coerce to int. Returning `list<int>` keeps `in_array`
     * checks strict and side-steps the legacy "string-vs-int"
     * comparison footgun.
     *
     * @return list<int>
     */
    private function bonusOptions(): array
    {
        $raw = Setting::getBonusRewardOptions();
        $out = [];
        foreach ($raw as $v) {
            $n = (int) $v;
            if ($n > 0) {
                $out[] = $n;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Have we hit `Setting::getBonusRewardTimesLimit()` rewards for
     * this user since UTC midnight? A `0` limit means "unlimited",
     * matching the legacy `if ($timesLimit > 0 && …)` guard exactly.
     */
    private function dailyLimitReached(int $userId): bool
    {
        $limit = Setting::getBonusRewardTimesLimit();
        if ($limit <= 0) {
            return false;
        }

        $today = now()->startOfDay();
        $todayCount = (int) Reward::query()
            ->where('userid', $userId)
            ->where('created_at', '>=', $today)
            ->count();

        return $todayCount >= $limit;
    }
}

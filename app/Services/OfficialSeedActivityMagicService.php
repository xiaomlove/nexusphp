<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class OfficialSeedActivityMagicService
{
    private const MINIMUM_SEED_SECONDS = 86400;
    private const DAILY_REWARD_CAP = 500.0;
    private const HOURS_PER_DAY = 24;
    private const REFERENCE_HOURLY_REWARD = 644.40048;
    private const ACTIVITY_REWARD_MULTIPLIER = 20.0;
    private const REWARD_SCALE_FACTOR = self::DAILY_REWARD_CAP / self::HOURS_PER_DAY / self::REFERENCE_HOURLY_REWARD;

    public function settle(): array
    {
        $officialTagId = (int) Setting::get('bonus.official_tag');
        if ($officialTagId <= 0) {
            throw new \RuntimeException('Official torrent tag is not configured.');
        }

        $periodKey = now()->format('YmdH');
        $seeders = DB::table('peers as p')
            ->join('snatched as s', function ($join) {
                $join->on('s.torrentid', '=', 'p.torrent')->on('s.userid', '=', 'p.userid');
            })
            ->join('require_seed_torrents as rst', 'rst.torrent_id', '=', 'p.torrent')
            ->join('torrent_tags as tt', function ($join) use ($officialTagId) {
                $join->on('tt.torrent_id', '=', 'p.torrent')->where('tt.tag_id', '=', $officialTagId);
            })
            ->where('p.seeder', 'yes')
            ->where('p.userid', '>', 0)
            ->where('s.seedtime', '>=', self::MINIMUM_SEED_SECONDS)
            ->select('p.userid', 'p.torrent')
            ->distinct()
            ->orderBy('p.userid')
            ->get()
            ->groupBy('userid');

        $settled = 0;
        $rewarded = 0;
        foreach ($seeders as $userId => $rows) {
            $torrentIds = $rows->pluck('torrent')->map(static fn ($id) => (int) $id)->unique()->values()->all();
            $result = calculate_seed_bonus((int) $userId, $torrentIds);
            $rawAmount = max(0, (float) ($result['official_bonus'] ?? 0) * (float) ($result['base_factor'] ?? 1));
            $amount = round($rawAmount * self::REWARD_SCALE_FACTOR * self::ACTIVITY_REWARD_MULTIPLIER, 5);
            if ($amount <= 0) {
                continue;
            }
            DB::transaction(function () use ($userId, $periodKey, $torrentIds, $result, $amount, &$settled, &$rewarded) {
                DB::table('users')->where('id', $userId)->lockForUpdate()->first();
                $alreadySettled = DB::table('nova_official_seed_magic_reward_events')
                    ->where('user_id', $userId)->where('period_key', $periodKey)->exists();
                if ($alreadySettled) {
                    return;
                }
                $hourlyCap = self::DAILY_REWARD_CAP / self::HOURS_PER_DAY * self::ACTIVITY_REWARD_MULTIPLIER;
                $rewardAmount = round(min($amount, $hourlyCap), 5);
                if ($rewardAmount <= 0) {
                    return;
                }
                DB::table('nova_official_seed_magic_reward_events')->insert([
                    'user_id' => (int) $userId,
                    'period_key' => $periodKey,
                    'reward_amount' => $rewardAmount,
                    'formula_bonus' => (float) ($result['formula_bonus'] ?? 0),
                    'official_percent' => (float) ($result['official_mteam_percent'] ?? 0),
                    'base_factor' => (float) ($result['base_factor'] ?? 1),
                    'torrent_count' => count($torrentIds),
                    'torrent_ids' => implode(',', $torrentIds),
                    'created_at' => now()->toDateTimeString(),
                ]);
                $settled++;
                DB::table('users')->where('id', $userId)->increment('seedbonus', $rewardAmount);
                $rewarded++;
            }, 3);
        }
        return compact('periodKey', 'settled', 'rewarded');
    }
}

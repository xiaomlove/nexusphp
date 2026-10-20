<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;

class OfficialSeedActivityService
{
    public const ENTRY_SEEDERS_MAX = 2;
    public const MINIMUM_AGE_DAYS = 7;
    public const FIRST_TIER_DAYS = 15;
    public const SECOND_TIER_DAYS = 30;
    public const DISCONNECT_GRACE_HOURS = 48;

    public function sync(): array
    {
        $officialTagId = (int)Setting::get('bonus.official_tag');
        if ($officialTagId <= 0) {
            throw new \RuntimeException('Official torrent tag is not configured.');
        }

        $rounds = $this->refreshRoundStates();
        $activeRound = $this->activeRound();
        if (!$activeRound) {
            $result = ['rounds' => $rounds, 'added' => 0, 'closed' => 0, 'enrolled' => 0, 'reset' => 0, 'updated' => 0, 'rewarded' => 0];
            logger()->info('[NOVA_OFFICIAL_SEED_ACTIVITY]', $result);
            return $result;
        }

        $added = $this->admitCampaigns($officialTagId, $activeRound);
        $closed = $this->closeInvalidCampaigns($officialTagId, (int)$activeRound->id);
        [$enrolled, $reset] = $this->enrollCurrentSeeders((int)$activeRound->id);
        [$updated, $rewarded] = $this->updateProgressAndRewards((int)$activeRound->id);

        $result = compact('rounds', 'added', 'closed', 'enrolled', 'reset', 'updated', 'rewarded');
        logger()->info('[NOVA_OFFICIAL_SEED_ACTIVITY]', $result);
        return $result;
    }

    private function refreshRoundStates(): array
    {
        $now = now()->toDateTimeString();
        $activated = DB::table('nova_official_seed_rounds')
            ->where('status', 'scheduled')
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now)
            ->update(['status' => 'active', 'updated_at' => $now]);
        $expiredIds = DB::table('nova_official_seed_rounds')
            ->whereIn('status', ['scheduled', 'active'])
            ->where('ends_at', '<=', $now)
            ->orderBy('id')
            ->pluck('id');
        $ended = 0;
        foreach ($expiredIds as $roundId) {
            $ended += $this->finalizeRound((int)$roundId);
        }
        return ['activated' => $activated, 'ended' => $ended];
    }

    private function activeRound(): ?object
    {
        return DB::table('nova_official_seed_rounds')
            ->where('status', 'active')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->orderByDesc('id')
            ->first();
    }

    private function finalizeRound(int $roundId): int
    {
        return DB::transaction(function () use ($roundId) {
            $round = DB::table('nova_official_seed_rounds')->where('id', $roundId)->lockForUpdate()->first();
            if (!$round || $round->status === 'ended') {
                return 0;
            }
            $campaigns = DB::table('nova_official_seed_campaigns as c')
                ->leftJoin('torrents as t', 't.id', '=', 'c.torrent_id')
                ->where('c.round_id', $roundId)
                ->where('c.status', 'active')
                ->get(['c.id', DB::raw('COALESCE(t.seeders, 0) AS seeders')]);
            foreach ($campaigns as $campaign) {
                $seeders = (int)$campaign->seeders;
                $outcome = $seeders > 10 ? 'excluded_next' : ($seeders >= 3 ? 'ordinary' : 'low_seed');
                DB::table('nova_official_seed_campaigns')->where('id', $campaign->id)->update([
                    'status' => 'closed',
                    'ending_seeders' => $seeders,
                    'outcome' => $outcome,
                    'closed_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ]);
            }
            DB::table('nova_official_seed_rounds')->where('id', $roundId)->update([
                'status' => 'ended',
                'ended_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);
            $nextRoundExists = DB::table('nova_official_seed_rounds')
                ->where('id', '<>', $roundId)
                ->whereIn('status', ['scheduled', 'active'])
                ->where('ends_at', '>', now())
                ->exists();
            if (!$nextRoundExists) {
                $duration = max(3600, strtotime($round->ends_at) - strtotime($round->starts_at));
                DB::table('nova_official_seed_rounds')->insert([
                    'starts_at' => now()->toDateTimeString(),
                    'ends_at' => now()->addSeconds($duration)->toDateTimeString(),
                    'status' => 'active',
                    'created_by' => 0,
                    'created_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ]);
            }
            return 1;
        }, 3);
    }

    private function admitCampaigns(int $officialTagId, object $activeRound): int
    {
        $now = now()->toDateTimeString();
        $previousRoundId = DB::table('nova_official_seed_rounds')
            ->where('id', '<', $activeRound->id)
            ->where('status', 'ended')
            ->where('ends_at', '<=', $activeRound->starts_at)
            ->orderByDesc('ends_at')
            ->value('id');

        $candidateQuery = DB::table('torrents as t')
            ->join('torrent_tags as tt', 'tt.torrent_id', '=', 't.id')
            ->where('tt.tag_id', $officialTagId)
            ->where('t.banned', 'no')
            ->where('t.visible', 'yes')
            ->where('t.added', '<=', now()->subDays(self::MINIMUM_AGE_DAYS))
            ->where('t.seeders', '<=', self::ENTRY_SEEDERS_MAX);

        if ($previousRoundId) {
            $candidateQuery->whereNotExists(function ($query) use ($previousRoundId) {
                $query->select(DB::raw(1))
                    ->from('nova_official_seed_campaigns as previous_campaign')
                    ->whereColumn('previous_campaign.torrent_id', 't.id')
                    ->where('previous_campaign.round_id', $previousRoundId)
                    ->where('previous_campaign.outcome', 'excluded_next');
            });
        }

        $candidateQuery->whereNotExists(function ($query) use ($activeRound) {
            $query->select(DB::raw(1))
                ->from('nova_official_seed_campaigns as current_campaign')
                ->whereColumn('current_campaign.torrent_id', 't.id')
                ->where('current_campaign.round_id', (int)$activeRound->id);
        });

        $rows = [];
        foreach ($candidateQuery->select(['t.id', 't.size'])->distinct()->get() as $torrent) {
            $rows[] = [
                'round_id' => (int)$activeRound->id,
                'torrent_id' => (int)$torrent->id,
                'size_bytes' => (int)$torrent->size,
                'qualified_at' => $now,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $added = 0;
        foreach (array_chunk($rows, 500) as $batch) {
            $added += DB::table('nova_official_seed_campaigns')->insertOrIgnore($batch);
        }

        return $added;
    }

    private function closeInvalidCampaigns(int $officialTagId, int $roundId): int
    {
        $invalidIds = DB::table('nova_official_seed_campaigns as c')
            ->where('c.status', 'active')
            ->where('c.round_id', $roundId)
            ->whereNotExists(function ($query) use ($officialTagId) {
                $query->select(DB::raw(1))
                    ->from('torrents as t')
                    ->join('torrent_tags as tt', function ($join) use ($officialTagId) {
                        $join->on('tt.torrent_id', '=', 't.id')
                            ->where('tt.tag_id', '=', $officialTagId);
                    })
                    ->whereColumn('t.id', 'c.torrent_id')
                    ->where('t.banned', 'no')
                    ->where('t.visible', 'yes');
            })
            ->pluck('c.id')
            ->map(fn ($id) => (int)$id)
            ->all();

        if (!$invalidIds) {
            return 0;
        }

        return DB::table('nova_official_seed_campaigns')
            ->where('status', 'active')
            ->where('round_id', $roundId)
            ->whereIn('id', $invalidIds)
            ->update([
                'status' => 'closed',
                'outcome' => 'invalid',
                'closed_at' => now()->toDateTimeString(),
                'updated_at' => now()->toDateTimeString(),
            ]);
    }

    private function enrollCurrentSeeders(int $roundId): array
    {
        $now = now()->toDateTimeString();
        $nowTimestamp = time();
        $staleBefore = now()->subHours(self::DISCONNECT_GRACE_HOURS)->toDateTimeString();
        $enrolled = 0;
        $reset = 0;

        DB::table('nova_official_seed_campaigns')
            ->where('status', 'active')
            ->where('round_id', $roundId)
            ->orderBy('id')
            ->chunkById(100, function ($campaigns) use ($now, $nowTimestamp, $staleBefore, &$enrolled, &$reset) {
                $campaignByTorrent = $campaigns->keyBy(fn ($campaign) => (int)$campaign->torrent_id);
                $campaignIds = $campaigns->pluck('id')->map(fn ($id) => (int)$id)->all();
                $torrentIds = $campaigns->pluck('torrent_id')->map(fn ($id) => (int)$id)->all();

                $seeders = DB::table('peers as p')
                    ->join('snatched as s', function ($join) {
                        $join->on('s.torrentid', '=', 'p.torrent')
                            ->on('s.userid', '=', 'p.userid');
                    })
                    ->whereIn('p.torrent', $torrentIds)
                    ->where('p.seeder', 'yes')
                    ->where('p.userid', '>', 0)
                    ->groupBy('p.torrent', 'p.userid')
                    ->get(['p.torrent', 'p.userid', DB::raw('MAX(s.seedtime) AS seedtime')]);

                if ($seeders->isEmpty()) {
                    return;
                }

                $userIds = $seeders->pluck('userid')
                    ->map(fn ($id) => (int)$id)
                    ->unique()
                    ->values()
                    ->all();

                $existingByKey = DB::table('nova_official_seed_participants')
                    ->whereIn('campaign_id', $campaignIds)
                    ->whereIn('user_id', $userIds)
                    ->get(['id', 'campaign_id', 'user_id', 'last_seen_at'])
                    ->keyBy(fn ($participant) => (int)$participant->campaign_id . ':' . (int)$participant->user_id);

                $newRows = [];
                $heartbeatIds = [];
                $resetBaselines = [];

                foreach ($seeders as $seeder) {
                    $campaign = $campaignByTorrent->get((int)$seeder->torrent);
                    if (!$campaign) {
                        continue;
                    }

                    $key = (int)$campaign->id . ':' . (int)$seeder->userid;
                    $existing = $existingByKey->get($key);

                    if (!$existing) {
                        $newRows[] = [
                            'campaign_id' => (int)$campaign->id,
                            'torrent_id' => (int)$campaign->torrent_id,
                            'user_id' => (int)$seeder->userid,
                            'baseline_seedtime' => max(0, (int)$seeder->seedtime),
                            'seed_seconds' => 0,
                            'enrolled_at' => $now,
                            'last_seen_at' => $now,
                            'reward_15_bytes' => 0,
                            'reward_15_at' => null,
                            'reward_30_bytes' => 0,
                            'reward_30_at' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        continue;
                    }

                    $participantId = (int)$existing->id;
                    $gapSeconds = $existing->last_seen_at
                        ? $nowTimestamp - strtotime($existing->last_seen_at)
                        : PHP_INT_MAX;

                    if ($gapSeconds > self::DISCONNECT_GRACE_HOURS * 3600) {
                        $resetBaselines[$participantId] = max(0, (int)$seeder->seedtime);
                    } else {
                        $heartbeatIds[] = $participantId;
                    }
                }

                foreach (array_chunk($newRows, 500) as $batch) {
                    $enrolled += DB::table('nova_official_seed_participants')->insertOrIgnore($batch);
                }

                if ($heartbeatIds) {
                    DB::table('nova_official_seed_participants')
                        ->whereIn('id', array_values(array_unique($heartbeatIds)))
                        ->where(function ($query) use ($now) {
                            $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $now);
                        })
                        ->update([
                            'last_seen_at' => $now,
                            'updated_at' => $now,
                        ]);
                }

                if ($resetBaselines) {
                    $caseSql = 'CASE id ';
                    foreach ($resetBaselines as $participantId => $baseline) {
                        $caseSql .= 'WHEN ' . (int)$participantId . ' THEN ' . (int)$baseline . ' ';
                    }
                    $caseSql .= 'ELSE baseline_seedtime END';

                    $reset += DB::table('nova_official_seed_participants')
                        ->whereIn('id', array_keys($resetBaselines))
                        ->where(function ($query) use ($staleBefore) {
                            $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', $staleBefore);
                        })
                        ->update([
                            'baseline_seedtime' => DB::raw($caseSql),
                            'seed_seconds' => 0,
                            'enrolled_at' => $now,
                            'last_seen_at' => $now,
                            'updated_at' => $now,
                        ]);
                }
            });

        return [$enrolled, $reset];
    }

    private function updateProgressAndRewards(int $roundId): array
    {
        $updated = 0;
        $rewarded = 0;
        DB::table('nova_official_seed_participants as p')
            ->join('nova_official_seed_campaigns as c', 'c.id', '=', 'p.campaign_id')
            ->leftJoin('snatched as s', function ($join) {
                $join->on('s.torrentid', '=', 'p.torrent_id')
                    ->on('s.userid', '=', 'p.user_id');
            })
            ->where('c.status', 'active')
            ->where('c.round_id', $roundId)
            ->select([
                'p.id',
                'p.baseline_seedtime',
                'p.seed_seconds',
                'p.reward_15_at',
                'p.reward_30_at',
                DB::raw('COALESCE(MAX(s.seedtime), p.baseline_seedtime) AS current_seedtime'),
            ])
            ->groupBy('p.id', 'p.baseline_seedtime', 'p.seed_seconds', 'p.reward_15_at', 'p.reward_30_at')
            ->orderBy('p.id')
            ->chunkById(300, function ($participants) use (&$updated, &$rewarded) {
                $ids = [];
                $cases = [];
                $settleIds = [];

                foreach ($participants as $participant) {
                    $participantId = (int)$participant->id;
                    $seconds = max(
                        (int)$participant->seed_seconds,
                        (int)$participant->current_seedtime - (int)$participant->baseline_seedtime,
                        0
                    );
                    $ids[] = $participantId;
                    $cases[] = 'WHEN ' . $participantId . ' THEN ' . $seconds;

                    $needsFirstTier = $seconds >= self::FIRST_TIER_DAYS * 86400
                        && !$participant->reward_15_at;
                    $needsSecondTier = $seconds >= self::SECOND_TIER_DAYS * 86400
                        && !$participant->reward_30_at;
                    if ($needsFirstTier || $needsSecondTier) {
                        $settleIds[] = $participantId;
                    }
                }

                if ($ids) {
                    $caseSql = 'CASE id ' . implode(' ', $cases) . ' ELSE seed_seconds END';
                    DB::table('nova_official_seed_participants')
                        ->whereIn('id', $ids)
                        ->update([
                            'seed_seconds' => DB::raw('GREATEST(seed_seconds, ' . $caseSql . ')'),
                            'updated_at' => now()->toDateTimeString(),
                        ]);
                    $updated += count($ids);
                }

                foreach ($settleIds as $participantId) {
                    $rewarded += $this->settleParticipant($participantId);
                }
            }, 'p.id', 'id');

        return [$updated, $rewarded];
    }

    private function settleParticipant(int $participantId): int
    {
        return DB::transaction(function () use ($participantId) {
            $participant = DB::table('nova_official_seed_participants')->where('id', $participantId)->lockForUpdate()->first();
            if (!$participant) {
                return 0;
            }
            $campaign = DB::table('nova_official_seed_campaigns')->where('id', $participant->campaign_id)->first();
            $user = DB::table('users')->where('id', $participant->user_id)->lockForUpdate()->first(['id', 'uploaded']);
            if (!$campaign || !$user) {
                return 0;
            }

            $tiers = [];
            if ((int)$participant->seed_seconds >= self::FIRST_TIER_DAYS * 86400 && !$participant->reward_15_at) {
                $tiers[] = [self::FIRST_TIER_DAYS, 20, 'reward_15_bytes', 'reward_15_at'];
            }
            if ((int)$participant->seed_seconds >= self::SECOND_TIER_DAYS * 86400 && !$participant->reward_30_at) {
                $tiers[] = [self::SECOND_TIER_DAYS, 30, 'reward_30_bytes', 'reward_30_at'];
            }
            if (!$tiers) {
                return 0;
            }

            $uploaded = (int)$user->uploaded;
            $count = 0;
            foreach ($tiers as [$days, $percent, $bytesField, $atField]) {
                $reward = intdiv((int)$campaign->size_bytes * $percent, 100);
                $before = $uploaded;
                $uploaded += $reward;
                DB::table('nova_official_seed_reward_events')->insert([
                    'participant_id' => $participant->id,
                    'torrent_id' => $participant->torrent_id,
                    'user_id' => $participant->user_id,
                    'tier_days' => $days,
                    'reward_bytes' => $reward,
                    'uploaded_before' => $before,
                    'uploaded_after' => $uploaded,
                    'created_at' => now()->toDateTimeString(),
                ]);
                DB::table('nova_official_seed_participants')->where('id', $participant->id)->update([
                    $bytesField => $reward,
                    $atField => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ]);
                DB::table('messages')->insert([
                    'sender' => 0,
                    'receiver' => $participant->user_id,
                    'added' => now()->toDateTimeString(),
                    'subject' => '官种保种活动奖励已发放',
                    'msg' => "您为种子 [url=details.php?id={$participant->torrent_id}]#{$participant->torrent_id}[/url] 累计保种已满 {$days} 天，本档发放上传量 " . $this->formatBytes($reward) . '。30 天档为补发 30%，两档累计为种子体积的 50%。',
                    'unread' => 'yes',
                    'location' => 1,
                    'saved' => 'no',
                ]);
                $count++;
            }
            DB::table('users')->where('id', $participant->user_id)->update(['uploaded' => $uploaded]);
            return $count;
        }, 3);
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $value = (float)$bytes;
        $unit = 0;
        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }
        return number_format($value, $unit === 0 ? 0 : 2) . ' ' . $units[$unit];
    }
}

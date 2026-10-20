<?php

namespace App\Repositories;

use App\Models\RequireSeedTorrent;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\UserRequireSeedTorrent;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;

class RequireSeedTorrentRepository extends BaseRepository
{
    public const ENTRY_SEEDERS_MAX = 1;
    public const EXIT_SEEDERS_MIN = 8;
    public const MINIMUM_AGE_DAYS = 3;

    public function syncSeedGuard(): array
    {
        return DB::transaction(function () {
            $officialTagId = (int)Setting::get('bonus.official_tag');
            if ($officialTagId <= 0) {
                throw new \RuntimeException('Official torrent tag is not configured.');
            }

            $removed = 0;
            $toRemove = RequireSeedTorrent::query()
                ->leftJoin('torrents', 'torrents.id', '=', 'require_seed_torrents.torrent_id')
                ->where(function ($query) {
                    $query->whereNull('torrents.id')
                        ->orWhere('torrents.seeders', '>=', self::EXIT_SEEDERS_MIN);
                })
                ->get([
                    'require_seed_torrents.*',
                    'torrents.id as existing_torrent_id',
                ]);

            foreach ($toRemove as $record) {
                if ($record->existing_torrent_id) {
                    $spState = (int)$record->original_sp_state;
                    $timeType = (int)$record->original_promotion_time_type;
                    $promotionUntil = $record->original_promotion_until;
                    if (
                        $timeType === Torrent::PROMOTION_TIME_TYPE_DEADLINE
                        && $promotionUntil
                        && Carbon::parse($promotionUntil)->isPast()
                    ) {
                        $spState = Torrent::PROMOTION_NORMAL;
                        $timeType = Torrent::PROMOTION_TIME_TYPE_GLOBAL;
                        $promotionUntil = null;
                    }
                    Torrent::query()->whereKey($record->torrent_id)->update([
                        'sp_state' => $spState,
                        'promotion_time_type' => $timeType,
                        'promotion_until' => $promotionUntil,
                    ]);
                }
                $record->delete();
                UserRequireSeedTorrent::query()->where('torrent_id', $record->torrent_id)->delete();
                $removed++;
            }

            $added = 0;
            Torrent::query()
                ->where('banned', 'no')
                ->where('visible', 'yes')
                ->where('added', '<=', now()->subDays(self::MINIMUM_AGE_DAYS))
                ->where('seeders', '<=', self::ENTRY_SEEDERS_MAX)
                ->whereHas('torrent_tags', function ($query) use ($officialTagId) {
                    $query->where('tag_id', $officialTagId);
                })
                ->whereNotIn('id', RequireSeedTorrent::query()->select('torrent_id'))
                ->select(['id', 'sp_state', 'promotion_time_type', 'promotion_until'])
                ->chunkById(500, function ($torrents) use (&$added) {
                    $now = now()->toDateTimeString();
                    $records = [];
                    $torrentIds = [];
                    foreach ($torrents as $torrent) {
                        $torrentIds[] = $torrent->id;
                        $records[] = [
                            'torrent_id' => $torrent->id,
                            'original_sp_state' => $torrent->sp_state,
                            'original_promotion_time_type' => $torrent->promotion_time_type,
                            'original_promotion_until' => $torrent->promotion_until,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    RequireSeedTorrent::query()->insert($records);
                    Torrent::query()->whereIn('id', $torrentIds)->update([
                        'sp_state' => Torrent::PROMOTION_FREE_TWO_TIMES_UP,
                        'promotion_time_type' => Torrent::PROMOTION_TIME_TYPE_PERMANENT,
                        'promotion_until' => null,
                    ]);
                    $added += count($records);
                });

            $activeIds = RequireSeedTorrent::query()->pluck('torrent_id');
            if ($activeIds->isNotEmpty()) {
                Torrent::query()->whereIn('id', $activeIds)->update([
                    'sp_state' => Torrent::PROMOTION_FREE_TWO_TIMES_UP,
                    'promotion_time_type' => Torrent::PROMOTION_TIME_TYPE_PERMANENT,
                    'promotion_until' => null,
                ]);
            }

            $redis = NexusDB::redis();
            $redis->del(self::getTorrentCacheKey());
            $now = now()->toDateTimeString();
            foreach ($activeIds as $torrentId) {
                $redis->hSet(self::getTorrentCacheKey(), $torrentId, $now);
            }

            $result = [
                'added' => $added,
                'removed' => $removed,
                'active' => $activeIds->count(),
            ];
            do_log('[SEED_GUARD_SYNC] ' . json_encode($result));
            return $result;
        });
    }

    public function autoAddToListCronjob(): void
    {
        $logPrefix = "[RequireSeedTorrentRepository.autoAddToListCronjob]";
        $enabled = Setting::getIsRequireSeedSectionEnabled();
        if (!$enabled) {
            do_log("$logPrefix, not enabled");
            return;
        }
        $countMaxAllowed = Setting::getRequireSeedSectionTorrentCountMax();
        $countNow = RequireSeedTorrent::query()->count();
        if ($countNow >= $countMaxAllowed) {
            do_log("$logPrefix, max allowed $countMaxAllowed reached");
            return;
        }
        $count = $countMaxAllowed - $countNow;
        $seederMax = Setting::getRequireSeedSectionSeederLte();
        $seederMin = Setting::getRequireSeedSectionSeederGte();
        $logPrefix .= ", countMaxAllowed: $countMaxAllowed, countNow: $countNow, count: $count, seederMax: $seederMax, seederMin: $seederMin";
        $query = Torrent::query()->where('banned', 'no')
            ->where('seeders', '<=', $seederMax)
            ->where('seeders', '>=', $seederMin)
        ;
        $tags = Setting::getRequireSeedSectionTags();
        if (!empty($tags)) {
            $logPrefix .= ", tags: " . implode(',', $tags);
            $query->whereHas('torrent_tags', function ($query) use ($tags) {
                $query->whereIn('tag_id', $tags);
            });
        }
        $list = $query->leftJoin("require_seed_torrents", "torrents.id", "=", "require_seed_torrents.torrent_id")
            ->whereNull("require_seed_torrents.id")
            ->orderBy('torrents.seeders', 'asc')
            ->orderBy('torrents.times_completed', 'desc')
            ->orderBy('torrents.hits', 'desc')
            ->limit($count)
            ->get(['torrents.id'])
        ;
        $data = [];
        $nowStr = now()->toDateTimeString();
        $redis = NexusDB::redis();
        $cacheKey = self::getTorrentCacheKey();
        foreach ($list as $item) {
            $data[] = [
                'torrent_id' => $item->id,
                'created_at' => $nowStr,
                'updated_at' => $nowStr,
            ];
            $redis->hset($cacheKey, $item->id, $nowStr);
        }
        RequireSeedTorrent::query()->insert($data);
        do_log("$logPrefix, success inserted: " . count($data));
    }

    public function autoRemoveFromListCronjob(): void
    {
        $idArr = RequireSeedTorrent::query()->pluck('torrent_id')->toArray();
        if (empty($idArr)) {
            do_log("no data to remove");
            return;
        }
        $seederMax = Setting::getRequireSeedSectionSeederLte();
        $seederMin = Setting::getRequireSeedSectionSeederGte();
        $torrents = Torrent::query()->whereIn('id', $idArr)
            ->where('seeders', '<', $seederMin)
            ->get(['id'])
        ;
        if (!empty($torrents)) {
            $this->doRemove($torrents);
            do_log(sprintf("remove %s seeders < %s", count($torrents), $seederMin));
        }
        $torrents = Torrent::query()->whereIn('id', $idArr)
            ->where('seeders', '>', $seederMax)
            ->get(['id'])
        ;
        if (!empty($torrents)) {
            $this->doRemove($torrents);
            do_log(sprintf("remove %s seeders > %s", count($torrents), $seederMax));
        }
    }

    public function doRemove(Collection $torrents): void
    {
        $idArr = [];
        $redis = NexusDB::redis();
        $promotionState = Setting::getRequireSeedSectionPromotionState();
        $ttlInSeconds = 24 * 3600;
        foreach ($torrents as $torrent) {
            $idArr[] = $torrent->id;
            $promotionStateCacheKey = sprintf("%s:%s", Torrent::REQUIRE_SEED_SECTION_PROMOTION_STATE_CACHE_KEY, $torrent->id);
            $redis->setex($promotionStateCacheKey, $ttlInSeconds, $promotionState);
            //remove torrent from list
            $redis->hDel(self::getTorrentCacheKey(), $torrent->id);
            //remove all users under torrent
            $redis->unlink(self::getTorrentUserCacheKey($torrent->id));
        }
        RequireSeedTorrent::query()->whereIn('torrent_id', $idArr)->delete();
        UserRequireSeedTorrent::query()->whereIn('torrent_id', $idArr)->delete();
        do_log("success removed " . count($idArr));
    }

    private static function getTorrentCacheKey(): string
    {
        return Torrent::REQUIRE_SEED_SECTION_TORRENT_ON_LIST_CACHE_KEY;
    }

    private static function getTorrentUserCacheKey($torrentId): string
    {
        return sprintf("%s:%s", Torrent::REQUIRE_SEED_SECTION_TORRENT_USER_CACHE_KEY, $torrentId);
    }

    public static function shouldRecordUser(\Redis $redis, $userId, $torrentId): bool
    {
        $logPrefix = "userId: $userId, torrentId: $torrentId";
        //check enabled or not
        if (!Setting::getIsRequireSeedSectionEnabled()) {
            do_log("$logPrefix, not enabled", 'debug');
            return false;
        }
        //first, torrent on list
        $onListCacheKey = self::getTorrentCacheKey();
        if (!$redis->hExists($onListCacheKey, $torrentId)) {
            do_log("$logPrefix, torrent not on list: $onListCacheKey", 'debug');
            return false;
        }
        //second, torrent user not exists
        $torrentUserCacheKey = self::getTorrentUserCacheKey($torrentId);
        if ($redis->hExists($torrentUserCacheKey, $userId)) {
            do_log("$logPrefix, user already exists: $torrentUserCacheKey", 'debug');
            return false;
        }
        return true;
    }

    public static function recordUser(\Redis $redis, $userId, $torrentId, array $snatchedInfo): void
    {
        $torrentUserCacheKey = self::getTorrentUserCacheKey($torrentId);
        $nowStr = now()->toDateTimeString();
        $values = [
            'user_id' => $userId,
            'torrent_id' => $torrentId,
            'seed_time_begin' => $snatchedInfo['seedtime'],
            'uploaded_begin' => $snatchedInfo['uploaded'],
            'created_at' => $nowStr,
        ];
        $uniqueBy = ['user_id', 'torrent_id'];
        $update = ['updated_at'];
        UserRequireSeedTorrent::query()->upsert($values, $uniqueBy, $update);
        $redis->hset($torrentUserCacheKey, $userId, $nowStr);
        do_log("success insert user: $userId, torrent: $torrentId");
    }

    public function autoSettlementCronjob(): void
    {

    }
}

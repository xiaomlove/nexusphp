<?php
namespace App\Repositories;

use App\Exceptions\InsufficientPermissionException;
use App\Models\Claim;
use App\Models\Message;
use App\Models\Peer;
use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;

class ClaimRepository extends BaseRepository
{
    const STAT_CACHE_PREFIX = 'claim_stats_';

    public function getList(array $params)
    {
        $query = Claim::query()->with(['family']);
        if (!empty($params['family_id'])) {
            $query->where('family_id', $params['family_id']);
        }
        $query->orderBy('family_id', 'desc');
        return $query->paginate();
    }

    public function store($uid, $torrentId)
    {
        $snatch = $this->canBeClaimByUser($torrentId, $uid);
        $insert = [
            'uid' => $uid,
            'torrent_id' => $torrentId,
            'snatched_id' => $snatch->id,
            'seed_time_begin' => $snatch->seedtime,
            'uploaded_begin' => $snatch->uploaded,
        ];
        $this->clearStatsCache($uid);
        return Claim::query()->create($insert);
    }

    public function canBeClaimByUser(int $torrentId, int $uid)
    {
        $isEnabled = Claim::getConfigIsEnabled();
        if (!$isEnabled) {
            throw new \RuntimeException(nexus_trans("torrent.claim_disabled"));
        }
        $exists = Claim::query()->where('uid', $uid)->where('torrent_id', $torrentId)->exists();
        if ($exists) {
            throw new \RuntimeException(nexus_trans("torrent.claim_already"));
        }
        $snatch = Snatch::query()->where('userid', $uid)->where('torrentid', $torrentId)->first();
        if (!$snatch) {
            throw new \RuntimeException(nexus_trans("torrent.no_snatch"));
        }
        $claimTorrentTTL = Claim::getConfigTorrentTTL();
        $torrent = Torrent::query()->findOrFail($torrentId, Torrent::$commentFields);
        if ($torrent->added->addDays($claimTorrentTTL)->gte(Carbon::now())) {
            throw new \RuntimeException(nexus_trans("torrent.can_no_be_claimed_yet"));
        }
        if (!has_role_work_seeding($uid)) {
            $max = Claim::getConfigTorrentUpLimit();
            $count = Claim::query()->where('uid', $uid)->count();
            if ($count >= $max) {
                throw new \RuntimeException(nexus_trans("torrent.claim_number_reach_torrent_maximum"));
            }
            $max = Claim::getConfigUserUpLimit();
            $count = Claim::query()->where('torrent_id', $torrentId)->count();
            if ($count >= $max) {
                throw new \RuntimeException(nexus_trans("torrent.claim_number_reach_user_maximum"));
            }
        }
        return $snatch;
    }

    public function update(array $params, $id)
    {
        $model = Claim::query()->findOrFail($id);
        $model->update($params);
        return $model;
    }

    public function getDetail($id)
    {
        $model = Claim::query()->findOrFail($id);
        return $model;
    }

    public function delete($id, $uid)
    {
        $isEnabled = Claim::getConfigIsEnabled();
        if (!$isEnabled) {
            throw new \RuntimeException(nexus_trans("torrent.claim_disabled"));
        }
        $model = Claim::query()->findOrFail($id);
        if ($model->uid != $uid) {
            throw new \RuntimeException("No permission");
        }
        $deductBonus = Claim::getConfigGiveUpDeductBonus();
        return NexusDB::transaction(function () use ($model, $deductBonus) {
            $this->clearStatsCache($model->uid);
            User::query()->where('id', $model->uid)->decrement('seedbonus', $deductBonus);
            do_log(sprintf("[GIVE_UP_CLAIM_TORRENT], user: %s, deduct bonus: %s", $model->uid, $deductBonus), 'alert');
            return $model->delete();
        });
    }

    public function getStats($uid)
    {
        $key = self::STAT_CACHE_PREFIX . $uid;
        return NexusDB::remember($key, 3600, function () use ($uid) {
            $max = Claim::getConfigTorrentUpLimit();
            return sprintf('%s/%s', Claim::query()->where('uid', $uid)->count(), $max);
        });
    }

    public function clearStatsCache($uid)
    {
        NexusDB::cache_del(self::STAT_CACHE_PREFIX . $uid);
    }

    public function settleCronjob(): array
    {
        $startOfThisMonth = Carbon::now()->startOfMonth();
        $query = Claim::query()
            ->select(['uid'])
            ->where("created_at", "<", $startOfThisMonth)
            ->where(function (Builder $query) use ($startOfThisMonth) {
                $query->where('last_settle_at', '<', $startOfThisMonth)->orWhereNull('last_settle_at');
            })
            ->groupBy('uid')
        ;
        $size = 1000;
        $page = 1;
        $successCount = $failCount = 0;
        while (true) {
            $logPrefix = "size: $size, page: $page";
            $result = (clone $query)->forPage($page, $size)->get();
            if ($result->isEmpty()) {
                do_log("$logPrefix, no more data...");
                break;
            }
            do_log("get counts: " . $result->count());
            foreach ($result as $row) {
                $uid = $row->uid;
                do_log("$logPrefix, begin to settle user: $uid...");
                try {
                    $result = $this->settleUser($uid);
                    do_log("$logPrefix, settle user: $uid done!, result: " . var_export($result, true));
                    if ($result) {
                        $successCount++;
                    } else {
                        $failCount++;
                    }
                } catch (\Throwable $exception) {
                    do_log("$logPrefix, settle user: $uid fail!, error: " . $exception->getMessage() . $exception->getTraceAsString(), 'error');
                    $failCount++;
                }
            }
            $page++;
        }
        return ['success_count' => $successCount, 'fail_count' => $failCount];
    }

    public function settleUser($uid, $force = false, $test = false): bool
    {
        $hasRoleWorkSeeding = has_role_work_seeding($uid);
        if ($hasRoleWorkSeeding) {
            do_log("uid: $uid, filter: user_has_role_work_seeding => true, skip");
            return false;
        }
        $now = Carbon::now();
        $startOfThisMonth = $now->clone()->startOfMonth();
        $user = User::query()->with('language')->findOrFail($uid);
        $list = Claim::query()
            ->where('uid', $uid)
            ->where("created_at", "<", $startOfThisMonth)
            ->with(['snatch', 'torrent' => fn ($query) => $query->select(Torrent::$commentFields)])
            ->get()
        ;
        $seedTimeRequiredHours = Claim::getConfigStandardSeedTimeHours();
        $uploadedRequiredTimes = Claim::getConfigStandardUploadedTimes();
        $bonusMultiplier = Claim::getConfigBonusMultiplier();
        $bonusDeduct = Claim::getConfigRemoveDeductBonus();
        $reachedTorrentIdArr = $unReachedTorrentIdArr = $remainTorrentIdArr = $unReachedIdArr = $toUpdateIdArr = [];
        $totalSeedTime = 0;
        $seedTimeCaseWhen = $uploadedCaseWhen = [];
        $toDelClaimId = [];
        do_log(
            "uid: $uid, claim torrent count: " . $list->count()
            . ", seedTimeRequiredHours: $seedTimeRequiredHours"
            . ", uploadedRequiredTimes: $uploadedRequiredTimes"
            . ", bonusMultiplier: $bonusMultiplier"
            . ", bonusDeduct: $bonusDeduct"
        );
        foreach ($list as $row) {
            if ($row->last_settle_at && $row->last_settle_at->gte($startOfThisMonth)) {
                do_log("ID: {$row->id} already settle", 'alert');
                if (!$force) {
                    do_log("No force, return", 'alert');
                    return false;
                }
            }
            if (!$row->snatch) {
                $toDelClaimId[$row->id] = $row->id;
                do_log("No snatch, continue", 'alert');
                continue;
            }
            if (!$row->torrent) {
                $toDelClaimId[$row->id] = $row->id;
                do_log("No torrent, continue", 'alert');
                continue;
            }
            if (
                bcsub($row->snatch->seedtime, $row->seed_time_begin) >= $seedTimeRequiredHours * 3600
                || bcsub($row->snatch->uploaded, $row->uploaded_begin) >= $uploadedRequiredTimes * $row->torrent->size
            ) {
                do_log("[REACHED], uid: $uid, torrent: " . $row->torrent_id);
                $reachedTorrentIdArr[] = $row->torrent_id;
                $toUpdateIdArr[] = $row->id;
                $totalSeedTime += bcsub($row->snatch->seedtime, $row->seed_time_begin);
                $seedTimeCaseWhen[] = sprintf('when %s then %s', $row->id, $row->snatch->seedtime);
                $uploadedCaseWhen[] = sprintf('when %s then %s', $row->id, $row->snatch->uploaded);
            } else {
                $targetStartOfMonth = $row->created_at->startOfMonth();
                if ($startOfThisMonth->diffInMonths($targetStartOfMonth) > 1) {
                    do_log("[UNREACHED_REMOVE], uid: $uid, torrent: " . $row->torrent_id);
                    $unReachedIdArr[] = $row->id;
                    $unReachedTorrentIdArr[] = $row->torrent_id;
                } else {
                    do_log("[UNREACHED_FIRST_MONTH], uid: $uid, torrent: " . $row->torrent_id);
                    $seedTimeCaseWhen[] = sprintf('when %s then %s', $row->id, $row->snatch->seedtime);
                    $uploadedCaseWhen[] = sprintf('when %s then %s', $row->id, $row->snatch->uploaded);
                    $toUpdateIdArr[] = $row->id;
                    $remainTorrentIdArr[] = $row->torrent_id;
                }
            }
        }
        $bonusResult = calculate_seed_bonus($uid, $reachedTorrentIdArr);
        $seedTimeHoursAvg = $totalSeedTime / (count($reachedTorrentIdArr) ?: 1) / 3600;
        do_log(sprintf(
            "reachedTorrentIdArr: %s, unReachedIdArr: %s, bonusResult: %s, seedTimeHours: %s",
            json_encode($reachedTorrentIdArr), json_encode($unReachedIdArr), json_encode($bonusResult), $seedTimeHoursAvg
        ), 'alert');
        $bonusFinal = $bonusResult['seed_points'] * $seedTimeHoursAvg * $bonusMultiplier;
        do_log("bonus final: $bonusFinal", 'alert');

        $totalDeduct = $bonusDeduct * count($unReachedIdArr);
        do_log("totalDeduct: $totalDeduct", 'alert');

        $message = $this->buildMessage(
            $user, $reachedTorrentIdArr, $unReachedTorrentIdArr, $remainTorrentIdArr,
            $bonusResult, $bonusFinal, $seedTimeHoursAvg, $bonusDeduct, $totalDeduct
        );
        do_log("message: " . nexus_json_encode($message), 'alert');

        /**
         * Just do a test, debug from log
         */
        if ($test) {
            do_log("[TEST], return");
            return true;
        }

        //Wrap with transaction
        DB::transaction(function () use ($uid, $unReachedIdArr, $toUpdateIdArr, $bonusFinal, $totalDeduct, $uploadedCaseWhen, $seedTimeCaseWhen, $message, $now) {
            //Increase user bonus
            User::query()->where('id', $uid)->increment('seedbonus', $bonusFinal);
            do_log("Increase user bonus: $bonusFinal", 'alert');

            //Handle unreached
            if (!empty($unReachedIdArr)) {
                Claim::query()->whereIn('id', $unReachedIdArr)->delete();
                User::query()->where('id', $uid)->decrement('seedbonus', $totalDeduct);
                do_log("Deduct user bonus: $totalDeduct", 'alert');
            }

            //Update claim `last_settle_at` and init `seed_time_begin` & `uploaded_begin`
            if (!empty($toUpdateIdArr)) {
                $sql = sprintf(
                    "update claims set uploaded_begin = case id %s end, seed_time_begin = case id %s end, last_settle_at = '%s', updated_at = '%s' where id in (%s)",
                    implode(' ', $uploadedCaseWhen), implode(' ', $seedTimeCaseWhen), $now->toDateTimeString(), $now->toDateTimeString(), implode(',', $toUpdateIdArr)
                );
                $affectedRows = DB::update($sql);
                do_log("query: $sql, affectedRows: $affectedRows");
            }

            //Send message
            Message::add($message);
        });
        if (!empty($toDelClaimId)) {
            do_log("del claim: %s", json_encode($toDelClaimId));
            Claim::query()->whereIn("id", array_keys($toDelClaimId))->delete();
        }
        do_log("[DONE], cost time: " . (time() - $now->timestamp) . " seconds");
        return true;
    }

    private function buildMessage(
        User $user, $reachedTorrentIdArr, $unReachedTorrentIdArr, $remainTorrentIdArr,
        $bonusResult, $bonusFinal, $seedTimeHoursAvg, $deductPerTorrent, $deductTotal
    ) {
        $now = Carbon::now();
        $allTorrentIdArr = array_merge($reachedTorrentIdArr, $unReachedTorrentIdArr, $remainTorrentIdArr);
        //这里不使用占位符，在 $allTorrentIdArr 过大（超过3000）时容易结果为空且不报异常
        $torrentInfo = Torrent::query()
            ->whereRaw(sprintf("id in (%s)", implode(',', $allTorrentIdArr)))
            ->get(Torrent::$commentFields)
            ->keyBy('id')
        ;
        $msg = [];
        $locale = $user->locale;
        do_log("build message, user: {$user->id}, locale: $locale");
        $msg[] = nexus_trans('claim.msg_title', ['month' => $now->clone()->subMonths(1)->format('Y-m')], $locale);
        $msg[] = nexus_trans('claim.claim_total', [ 'total' => count($allTorrentIdArr)], $locale);

        $reachList = collect($reachedTorrentIdArr)->map(
            fn($item) => sprintf("[url=details.php?id=%s]%s[/url]", $item, $torrentInfo->get($item)->name)
        )->implode("\n");
        $msg[] = nexus_trans("claim.claim_reached_counts", ['counts' => count($reachedTorrentIdArr)], $locale) . "\n$reachList";
        $msg[] = nexus_trans(
            "claim.claim_reached_summary", [
            'bonus_per_hour' => number_format($bonusResult['seed_bonus'], 2),
            'hours'=> number_format($seedTimeHoursAvg, 2),
            'bonus_total'=> number_format($bonusFinal, 2)
        ], $locale
        );

        $remainList = collect($remainTorrentIdArr)->map(
            fn($item) => sprintf("[url=details.php?id=%s]%s[/url]", $item, $torrentInfo->get($item)->name)
        )->implode("\n");
        $msg[] = nexus_trans("claim.claim_unreached_remain_counts", ['counts' => count($remainTorrentIdArr)], $locale) . "\n$remainList";

        $unReachList = collect($unReachedTorrentIdArr)->map(
            fn($item) => sprintf("[url=details.php?id=%s]%s[/url]", $item, $torrentInfo->get($item)->name)
        )->implode("\n");
        $msg[] = nexus_trans("claim.claim_unreached_remove_counts", ['counts' => count($unReachedTorrentIdArr)], $locale) . "\n$unReachList";
        if ($deductTotal) {
            $msg[] = nexus_trans(
                "claim.claim_unreached_summary", [
                    'deduct_per_torrent' => number_format($deductPerTorrent, 2),
                    'deduct_total' => number_format($deductTotal, 2)
                ], $locale
            );
        }
        return [
            'receiver' => $user->id,
            'added' => $now,
            'subject' => nexus_trans('claim.msg_subject', ['month' => $now->clone()->subMonths(1)->format('Y-m')], $locale),
            'msg' => implode("\n\n", $msg),
        ];
    }

    public function buildActionButtons($torrentId, $claimData, $reload = 0): string
    {
        $buttonHtml = '<button data-action="%s" data-reload="%s" data-confirm="%s" data-claim_id="%s" data-torrent_id="%s" style="width: max-content;display: %s;align-items: center"><img style="margin-right: 4px;" class="staff_%s" src="pic/trans.gif">%s</button>';
        $addButton = sprintf($buttonHtml, 'addClaim', $reload, nexus_trans('claim.add_claim_confirm'), $claimData ? $claimData->id : 0, $torrentId, '%s', 'edit', nexus_trans('claim.add_claim'));
        $removeButton = sprintf($buttonHtml, 'removeClaim', $reload, nexus_trans('claim.remove_claim_confirm'), $claimData ? $claimData->id : 0, $torrentId, '%s', 'delete', nexus_trans('claim.remove_claim'));

        if ($claimData) {
            //Only show remove
            return sprintf($addButton, 'none') . sprintf($removeButton, 'flex');
        } else {
            //Only show add
            return sprintf($addButton, 'flex') . sprintf($removeButton, 'none');
        }

    }


}

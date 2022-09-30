<?php
namespace App\Repositories;

use App\Models\HitAndRun;
use App\Models\Message;
use App\Models\SearchBox;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserBanLog;
use Carbon\Carbon;
use Elasticsearch\Endpoints\Search;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;

class HitAndRunRepository extends BaseRepository
{
    public function getList(array $params)
    {
        $query = HitAndRun::query()->with(['user', 'torrent', 'snatch']);
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }
        if (!empty($params['uid'])) {
            $query->where('uid', $params['uid']);
        }
        if (!empty($params['torrent_id'])) {
            $query->where('torrent_id', $params['torrent_id']);
        }
        if (!empty($params['username'])) {
            $query->whereHas('user', function (Builder $query) use ($params) {
                return $query->where('username', $params['username']);
            });
        }
        $query->orderBy('id', 'desc');
        return $query->paginate();
    }

    public function store(array $params)
    {
        $model = HitAndRun::query()->create($params);
        return $model;
    }

    public function update(array $params, $id)
    {
        $model = HitAndRun::query()->findOrFail($id);
        $model->update($params);
        return $model;
    }

    public function getDetail($id)
    {
        $model = HitAndRun::query()->with(['user', 'torrent', 'snatch'])->findOrFail($id);
        return $model;
    }

    public function delete($id)
    {
        $model = HitAndRun::query()->findOrFail($id);
        $result = $model->delete();
        return $result;
    }

    public function bulkDelete(array $params, User $user)
    {
        $result = $this->getBulkQuery($params)->delete();
        do_log(sprintf(
            'user: %s bulk delete by filter: %s, result: %s',
            $user->id, json_encode($params), json_encode($result)
        ), 'alert');
        return $result;
    }

    private function getBulkQuery(array $params): Builder
    {
        $query = HitAndRun::query();
        $hasWhere = false;
        $validFilter = ['uid', 'id'];
        foreach ($validFilter as $item) {
            if (!empty($params[$item])) {
                $hasWhere = true;
                $query->whereIn($item, Arr::wrap($params[$item]));
            }
        }
        if (!$hasWhere) {
            throw new \InvalidArgumentException("No filter.");
        }
        return $query;
    }

    public function cronjobUpdateStatus($uid = null, $torrentId = null, $ignoreTime = false)
    {
        $enableSpecialSection = Setting::get('main.spsct') == 'yes';
        $browseMode = Setting::get('main.browsecat');
        $specialMode = Setting::get('main.specialcat');
        $this->doCronjobUpdateStatus($browseMode, $uid, $torrentId, $ignoreTime);
        if ($enableSpecialSection && $browseMode != $specialMode) {
            $this->doCronjobUpdateStatus($specialMode, $uid, $torrentId, $ignoreTime, $specialMode);
        }
    }

    private function doCronjobUpdateStatus($searchBoxId, $uid = null, $torrentId = null, $ignoreTime = false)
    {
        do_log("searchBoxId: $searchBoxId, uid: $uid, torrentId: $torrentId, ignoreTime: " . var_export($ignoreTime, true));
        $setting = HitAndRun::getConfig('*', $searchBoxId);
        $size = 1000;
        $page = 1;
        if (empty($setting['mode'])) {
            do_log("H&R not set.");
            return false;
        }
        if ($setting['mode'] == HitAndRun::MODE_DISABLED) {
            do_log("H&R mode is disabled.");
            return false;
        }
        if (empty($setting['inspect_time'])) {
            do_log("H&R inspect_time is not set.");
            return false;
        }
        $query = HitAndRun::query()
            ->where('status', HitAndRun::STATUS_INSPECTING)
            ->with([
                'torrent' => function ($query) {$query->select(['id', 'size', 'name', 'category']);},
                'snatch',
                'user' => function ($query) {$query->select(['id', 'username', 'lang', 'class', 'donoruntil', 'enabled']);},
                'user.language',
            ]);
        if (!is_null($uid)) {
            $query->where('uid', $uid);
        }
        if (!is_null($torrentId)) {
            $query->where('torrent_id', $torrentId);
        }
        if (!$ignoreTime) {
            $query->where('created_at', '<', Carbon::now()->subHours($setting['inspect_time']));
        }
        $query->whereHas('torrent.basic_category', function (Builder $query) use ($searchBoxId) {
            return $query->where('mode', $searchBoxId);
        });
        $successCounts = 0;
        $disabledUsers = [];
        while (true) {
            $logPrefix = "page: $page, size: $size";
            $rows = $query->forPage($page, $size)->get();
            do_log("$logPrefix, counts: " . $rows->count());
            if ($rows->isEmpty()) {
                do_log("$logPrefix, no more data..." . last_query());
                break;
            }
            foreach ($rows as $row) {
                $currentLog = "$logPrefix, [HANDLING] " . $row->toJson();
                do_log($logPrefix);
                if (!$row->user) {
                    do_log("$currentLog, user not exists, remove it!", 'error');
                    $row->delete();
                    continue;
                }
                if (!$row->snatch) {
                    do_log("$currentLog, snatch not exists, skip!", 'error');
                    continue;
                }
                if (!$row->torrent) {
                    do_log("$currentLog, torrent not exists, remove it!", 'error');
                    $row->delete();
                    continue;
                }

                //If is VIP or above OR donated, pass
                if ($row->user->class >= HitAndRun::MINIMUM_IGNORE_USER_CLASS || $row->user->isDonating()) {
                    $result = $this->reachedBySpecialUserClass($row);
                    if ($result) {
                        $successCounts++;
                    }
                    continue;
                }

                //check seed time
                $targetSeedTime = $row->snatch->seedtime;
                $requireSeedTime = bcmul($setting['seed_time_minimum'], 3600);
                do_log("$currentLog, targetSeedTime: $targetSeedTime, requireSeedTime: $requireSeedTime");
                if ($targetSeedTime >= $requireSeedTime) {
                    $result = $this->reachedBySeedTime($row, $searchBoxId);
                    if ($result) {
                        $successCounts++;
                    }
                    continue;
                }

                //check share ratio
                $targetShareRatio = bcdiv($row->snatch->uploaded, $row->torrent->size, 4);
                $requireShareRatio = $setting['ignore_when_ratio_reach'];
                do_log("$currentLog, targetShareRatio: $targetShareRatio, requireShareRatio: $requireShareRatio");
                if ($targetShareRatio >= $requireShareRatio) {
                    $result = $this->reachedByShareRatio($row, $searchBoxId);
                    if ($result) {
                        $successCounts++;
                    }
                    continue;
                }

                //unreached
                if ($row->created_at->addHours($setting['inspect_time'])->lte(Carbon::now())) {
                    $result = $this->unreached($row, $searchBoxId, !isset($disabledUsers[$row->uid]));
                    if ($result) {
                        $successCounts++;
                        $disabledUsers[$row->uid] = true;
                    }
                }
            }
            $page++;
        }
        do_log("searchBoxId: $searchBoxId, [CRONJOB_UPDATE_HR_DONE]");
        return $successCounts;
    }

    private function geReachedMessage(HitAndRun $hitAndRun): array
    {
        return [
            'receiver' => $hitAndRun->uid,
            'added' => Carbon::now()->toDateTimeString(),
            'subject' => nexus_trans('hr.reached_message_subject', ['hit_and_run_id' => $hitAndRun->id], $hitAndRun->user->locale),
            'msg' => nexus_trans('hr.reached_message_content', [
                'completed_at' => $hitAndRun->snatch->completedat->toDateTimeString(),
                'torrent_id' => $hitAndRun->torrent_id,
                'torrent_name' => $hitAndRun->torrent->name,
            ], $hitAndRun->user->locale),
        ];
    }

    private function reachedByShareRatio(HitAndRun $hitAndRun, $searchBoxId): bool
    {
        do_log(__METHOD__);
        $comment = nexus_trans('hr.reached_by_share_ratio_comment', [
            'now' => Carbon::now()->toDateTimeString(),
            'seed_time_minimum' => HitAndRun::getConfig('seed_time_minimum', $searchBoxId),
            'seed_time' => bcdiv($hitAndRun->snatch->seedtime, 3600, 1),
            'share_ratio' => get_hr_ratio($hitAndRun->snatch->uploaded, $hitAndRun->snatch->downloaded),
            'ignore_when_ratio_reach' => HitAndRun::getConfig('ignore_when_ratio_reach', $searchBoxId),
        ], $hitAndRun->user->locale);
        $update = [
            'comment' => $comment
        ];
        return $this->inspectingToReached($hitAndRun, $update, __FUNCTION__);
    }

    private function reachedBySeedTime(HitAndRun $hitAndRun, $searchBoxId): bool
    {
        do_log(__METHOD__);
        $comment = nexus_trans('hr.reached_by_seed_time_comment', [
            'now' => Carbon::now()->toDateTimeString(),
            'seed_time' => bcdiv($hitAndRun->snatch->seedtime, 3600, 1),
            'seed_time_minimum' => HitAndRun::getConfig('seed_time_minimum', $searchBoxId)
        ], $hitAndRun->user->locale);
        $update = [
            'comment' => $comment
        ];
        return $this->inspectingToReached($hitAndRun, $update, __FUNCTION__);
    }

    private function reachedBySpecialUserClass(HitAndRun $hitAndRun): bool
    {
        do_log(__METHOD__);
        $comment = nexus_trans('hr.reached_by_special_user_class_comment', [
            'user_class_text' => $hitAndRun->user->class_text,
        ], $hitAndRun->user->locale);
        $update = [
            'comment' => $comment
        ];
        return $this->inspectingToReached($hitAndRun, $update, __FUNCTION__);
    }

    private function inspectingToReached(HitAndRun $hitAndRun, array $update, string $logPrefix = ''): bool
    {
        $update['status'] = HitAndRun::STATUS_REACHED;
        $affectedRows = DB::table($hitAndRun->getTable())
            ->where('id', $hitAndRun->id)
            ->where('status', HitAndRun::STATUS_INSPECTING)
            ->update($update);
        do_log("[$logPrefix], " . last_query() . ", affectedRows: $affectedRows");
        if ($affectedRows != 1) {
            do_log($hitAndRun->toJson() . ", [$logPrefix], affectedRows != 1, skip!", 'notice');
            return false;
        }
        $message = $this->geReachedMessage($hitAndRun);
        Message::query()->insert($message);
        return true;
    }

    private function unreached(HitAndRun $hitAndRun, $searchBoxId, $disableUser = true): bool
    {
        do_log(sprintf('hitAndRun: %s, disableUser: %s', $hitAndRun->toJson(), var_export($disableUser, true)));
        $comment = nexus_trans('hr.unreached_comment', [
            'now' => Carbon::now()->toDateTimeString(),
            'seed_time' => bcdiv($hitAndRun->snatch->seedtime, 3600, 1),
            'seed_time_minimum' => HitAndRun::getConfig('seed_time_minimum', $searchBoxId),
            'share_ratio' => get_hr_ratio($hitAndRun->snatch->uploaded, $hitAndRun->snatch->downloaded),
            'torrent_size' => mksize($hitAndRun->torrent->size),
            'ignore_when_ratio_reach' => HitAndRun::getConfig('ignore_when_ratio_reach', $searchBoxId)
        ], $hitAndRun->user->locale);
        $update = [
            'status' => HitAndRun::STATUS_UNREACHED,
            'comment' => $comment
        ];
        $affectedRows = DB::table($hitAndRun->getTable())
            ->where('id', $hitAndRun->id)
            ->where('status', HitAndRun::STATUS_INSPECTING)
            ->update($update);
        do_log("[H&R_UNREACHED], " . last_query() . ", affectedRows: $affectedRows");
        if ($affectedRows != 1) {
            do_log($hitAndRun->toJson() . ", [H&R_UNREACHED], affectedRows != 1, skip!", 'notice');
            return false;
        }
        $message = [
            'receiver' => $hitAndRun->uid,
            'added' => Carbon::now()->toDateTimeString(),
            'subject' => nexus_trans('hr.unreached_message_subject', ['hit_and_run_id' => $hitAndRun->id], $hitAndRun->user->locale),
            'msg' => nexus_trans('hr.unreached_message_content', [
                'completed_at' => $hitAndRun->snatch->completedat->toDateTimeString(),
                'torrent_id' => $hitAndRun->torrent_id,
                'torrent_name' => $hitAndRun->torrent->name,
            ], $hitAndRun->user->locale),
        ];
        Message::query()->insert($message);

        if (!$disableUser) {
            do_log("[DO_NOT_DISABLE_USER], return");
            return true;
        }
        if ($hitAndRun->user->enabled == 'no') {
            do_log("[USER_ALREADY_DISABLED], return");
            return true;
        }
        //disable user
        /** @var User $user */
        $user = $hitAndRun->user;
        $counts = $user->hitAndRuns()
            ->where('status', HitAndRun::STATUS_UNREACHED)
            ->whereHas('torrent.basic_category', function (Builder $query) use ($searchBoxId) {
                return $query->where('mode', $searchBoxId);
            })
            ->count();
        $disableCounts = HitAndRun::getConfig('ban_user_when_counts_reach', $searchBoxId);
        do_log("user: {$user->id}, H&R counts: $counts, disableCounts: $disableCounts", 'notice');
        if ($counts >= $disableCounts) {
            do_log("[DISABLE_USER_DUE_TO_H&R_UNREACHED]", 'notice');
            $comment = nexus_trans('hr.unreached_disable_comment', [], $user->locale);
            $user->updateWithModComment(['enabled' => User::ENABLED_NO], $comment);
            $message = [
                'receiver' => $hitAndRun->uid,
                'added' => Carbon::now()->toDateTimeString(),
                'subject' => $comment,
                'msg' => nexus_trans('hr.unreached_disable_message_content', [
                    'ban_user_when_counts_reach' => Setting::get('hr.ban_user_when_counts_reach'),
                ], $hitAndRun->user->locale),
            ];
            Message::query()->insert($message);
            $userBanLog = [
                'uid' => $user->id,
                'username' => $user->username,
                'reason' => $comment
            ];
            UserBanLog::query()->insert($userBanLog);
        }

        return true;
    }

    public function getStatusStats($uid, $formatted = true)
    {
        $enableSpecialSection = Setting::get('main.spsct') == 'yes';
        if ($enableSpecialSection) {
            $sql = "select hit_and_runs.status, categories.mode, count(*) as counts from hit_and_runs left join torrents on torrents.id = hit_and_runs.torrent_id left join categories on categories.id = torrents.category where hit_and_runs.uid = $uid group by hit_and_runs.status, categories.mode";
        } else {
            $sql = "select hit_and_runs.status, count(*) as counts from hit_and_runs where uid = $uid group by status";
        }
        $results = NexusDB::select($sql);
        if (!$formatted) {
            return $results;
        }
        if ($enableSpecialSection) {
            $grouped = [];
            foreach ($results as $item) {
                $grouped[$item['mode']][$item['status']] = $item['counts'];
            }
            $out = [];
            foreach (SearchBox::listSections() as $key => $info) {
                $out[] = sprintf(
                    '%s: %s/%s/%s',
                    $info['text'],
                    $grouped[$info['mode']][HitAndRun::STATUS_INSPECTING] ?? 0,
                    $grouped[$info['mode']][HitAndRun::STATUS_UNREACHED] ?? 0,
                    HitAndRun::getConfig('ban_user_when_counts_reach', $info['mode'])
                );
            }
            return implode(" ", $out);
        } else {
            foreach (SearchBox::listSections() as $key => $info) {
                if ($key == SearchBox::SECTION_BROWSE) {
                    return sprintf(
                        '%s/%s/%s',
                        $results[HitAndRun::STATUS_INSPECTING] ?? 0,
                        $results[HitAndRun::STATUS_UNREACHED] ?? 0,
                        HitAndRun::getConfig('ban_user_when_counts_reach', $info['mode'])
                    );
                }
            }
        }
    }



    public function listStatus(): array
    {
        $results = [];
        foreach (HitAndRun::$status as $key => $value) {
            $results[] = ['status' => $key, 'text' => nexus_trans('hr.status_' . $key)];
        }
        return $results;
    }

    public function pardon($id, User $user): bool
    {
        $model = HitAndRun::query()->findOrFail($id);
        if (!in_array($model->status, $this->getCanPardonStatus())) {
            throw new \LogicException("Can't be pardoned due to status is: " . $model->status_text . " !");
        }
        $model->status = HitAndRun::STATUS_PARDONED;
        $model->comment = $this->getCommentUpdateRaw(addslashes(date('Y-m-d') . ' - Pardon by ' . $user->username));
        $model->save();
        return true;
    }

    public function bulkPardon(array $params, User $user): int
    {
        $query = $this->getBulkQuery($params)->whereIn('status', $this->getCanPardonStatus());
        $update = [
            'status' => HitAndRun::STATUS_PARDONED,
            'comment' => $this->getCommentUpdateRaw(addslashes('Pardon by ' . $user->username)),
        ];
        $affected =  $query->update($update);
        do_log(sprintf(
            'user: %s bulk pardon by filter: %s, affected: %s',
            $user->id, json_encode($params), $affected
        ), 'alert');
        return $affected;
    }

    private function getCommentUpdateRaw($comment): \Illuminate\Database\Query\Expression
    {
        return DB::raw(sprintf("if (comment = '', '%s', concat('\n', '%s', comment))", $comment, $comment));
    }

    private function getCanPardonStatus(): array
    {
        return [HitAndRun::STATUS_INSPECTING, HitAndRun::STATUS_UNREACHED];
    }

    public function renderOnUploadPage($value, $searchBoxId): string
    {
        if (HitAndRun::getConfig('mode', $searchBoxId) == \App\Models\HitAndRun::MODE_MANUAL && user_can('torrent_hr')) {
            $hrRadio = sprintf('<label><input type="radio" name="hr[%s]" value="0"%s />NO</label>', $searchBoxId, $value == 0 ? ' checked' : '');
            $hrRadio .= sprintf('<label><input type="radio" name="hr[%s]" value="1"%s />YES</label>', $searchBoxId, $value == 1 ? ' checked' : '');
            return tr('H&R', $hrRadio, 1, "mode_$searchBoxId", true);
        }
        return '';
    }
}

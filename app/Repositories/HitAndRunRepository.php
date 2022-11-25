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
        $diffInSection = HitAndRun::diffInSection();
        $browseMode = Setting::get('main.browsecat');
        $setting = HitAndRun::getConfig('*', $browseMode);
        if ($setting['mode'] != HitAndRun::MODE_DISABLED) {
            $setting['diff_in_section'] = $diffInSection;
            $setting['search_box_id'] = $browseMode;
            $this->doCronjobUpdateStatus($setting, $uid, $torrentId, $ignoreTime);
            $this->checkAndDisableUser($setting);
        }
        $specialMode = Setting::get('main.specialcat');
        if ($diffInSection && $browseMode != $specialMode) {
            $setting = HitAndRun::getConfig('*', $specialMode);
            if ($setting['mode'] != HitAndRun::MODE_DISABLED) {
                $setting['diff_in_section'] = $diffInSection;
                $setting['search_box_id'] = $specialMode;
                $this->doCronjobUpdateStatus($setting, $uid, $torrentId, $ignoreTime);
                $this->checkAndDisableUser($setting);
            }
        }
    }

    private function doCronjobUpdateStatus(array $setting, $uid = null, $torrentId = null, $ignoreTime = false)
    {
        do_log("setting: " . json_encode($setting) . ", uid: $uid, torrentId: $torrentId, ignoreTime: " . var_export($ignoreTime, true));
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
                'user' => function ($query) {$query->select(['id', 'username', 'lang', 'class', 'donoruntil', 'enabled', 'notifs']);},
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
        if ($setting['diff_in_section']) {
            $query->whereHas('torrent.basic_category', function (Builder $query) use ($setting) {
                return $query->where('mode', $setting['search_box_id']);
            });
        }

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
                    $result = $this->reachedBySeedTime($row, $setting);
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
                    $result = $this->reachedByShareRatio($row, $setting);
                    if ($result) {
                        $successCounts++;
                    }
                    continue;
                }

                //unreached
                if ($row->created_at->addHours($setting['inspect_time'])->lte(Carbon::now())) {
                    $result = $this->unreached($row, $setting, !isset($disabledUsers[$row->uid]));
                    if ($result) {
                        $successCounts++;
                        $disabledUsers[$row->uid] = true;
                    }
                }
            }
            $page++;
        }
        do_log("[CRONJOB_UPDATE_HR_DONE]");
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

    private function reachedByShareRatio(HitAndRun $hitAndRun, array $setting): bool
    {
        do_log(__METHOD__);
        $comment = nexus_trans('hr.reached_by_share_ratio_comment', [
            'now' => Carbon::now()->toDateTimeString(),
            'seed_time_minimum' => $setting['seed_time_minimum'],
            'seed_time' => bcdiv($hitAndRun->snatch->seedtime, 3600, 1),
            'share_ratio' => get_hr_ratio($hitAndRun->snatch->uploaded, $hitAndRun->snatch->downloaded),
            'ignore_when_ratio_reach' => $setting['ignore_when_ratio_reach'],
        ], $hitAndRun->user->locale);
        $update = [
            'comment' => $comment
        ];
        return $this->inspectingToReached($hitAndRun, $update, __FUNCTION__);
    }

    private function reachedBySeedTime(HitAndRun $hitAndRun, array $setting): bool
    {
        do_log(__METHOD__);
        $comment = nexus_trans('hr.reached_by_seed_time_comment', [
            'now' => Carbon::now()->toDateTimeString(),
            'seed_time' => bcdiv($hitAndRun->snatch->seedtime, 3600, 1),
            'seed_time_minimum' => $setting['seed_time_minimum'],
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
        if ($hitAndRun->user->acceptNotification('hr_reached')) {
            $message = $this->geReachedMessage($hitAndRun);
            Message::query()->insert($message);
        } else {
            do_log($hitAndRun->toJson() . ", [$logPrefix], user do not accept hr_reached notification", 'notice');
        }
        return true;
    }

    private function unreached(HitAndRun $hitAndRun, array $setting, $disableUser = true): bool
    {
        do_log(sprintf('hitAndRun: %s, disableUser: %s', $hitAndRun->toJson(), var_export($disableUser, true)));
        $comment = nexus_trans('hr.unreached_comment', [
            'now' => Carbon::now()->toDateTimeString(),
            'seed_time' => bcdiv($hitAndRun->snatch->seedtime, 3600, 1),
            'seed_time_minimum' => $setting['seed_time_minimum'],
            'share_ratio' => get_hr_ratio($hitAndRun->snatch->uploaded, $hitAndRun->snatch->downloaded),
            'torrent_size' => mksize($hitAndRun->torrent->size),
            'ignore_when_ratio_reach' => $setting['ignore_when_ratio_reach']
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

        return true;
    }

    private function checkAndDisableUser(array $setting): void
    {
        $logPrefix = "setting: " . json_encode($setting);
        $disableCounts = HitAndRun::getConfig('ban_user_when_counts_reach', $setting['search_box_id']);
        if ($disableCounts <= 0) {
            do_log("$logPrefix, disableCounts: $disableCounts <= 0, invalid, return", 'error');
            return;
        }
        $query = HitAndRun::query()
            ->selectRaw("count(*) as counts, uid")
            ->where('status', HitAndRun::STATUS_UNREACHED)
            ->groupBy('uid')
            ->having("counts", '>=', $disableCounts)
        ;
        if ($setting['diff_in_section']) {
            $query->whereHas('torrent.basic_category', function (Builder $query) use ($setting) {
                return $query->where('mode', $setting['search_box_id']);
            });
        }
        $result = $query->get();
        if ($result->isEmpty()) {
            do_log("$logPrefix, No user to disable: " . last_query());
            return;
        }
        $users = User::query()
            ->with('language')
            ->where('class', '<', User::CLASS_VIP)
            ->where('enabled', User::ENABLED_YES)
            ->where('donor', 'no')
            ->find($result->pluck('uid')->toArray(), ['id', 'username', 'lang']);
        do_log("$logPrefix, Going to disable user: " . json_encode($users->toArray()));
        foreach ($users as $user) {
            $locale = $user->locale;
            $comment = nexus_trans('hr.unreached_disable_comment', [], $locale);
            $user->updateWithModComment(['enabled' => User::ENABLED_NO], sprintf('%s - %s', date('Y-m-d'), $comment));
            $message = [
                'receiver' => $user->id,
                'added' => Carbon::now()->toDateTimeString(),
                'subject' => $comment,
                'msg' => nexus_trans('hr.unreached_disable_message_content', [
                    'ban_user_when_counts_reach' => $disableCounts,
                ], $locale),
            ];
            Message::query()->insert($message);
            $userBanLog = [
                'uid' => $user->id,
                'username' => $user->username,
                'reason' => $comment
            ];
            UserBanLog::query()->insert($userBanLog);
            do_log("Disable user: " . nexus_json_encode($userBanLog));
        }
    }

    public function getStatusStats($uid, $formatted = true)
    {
        $diffInSection = HitAndRun::diffInSection();
        if ($diffInSection) {
            $sql = "select hit_and_runs.status, categories.mode, count(*) as counts from hit_and_runs left join torrents on torrents.id = hit_and_runs.torrent_id left join categories on categories.id = torrents.category where hit_and_runs.uid = $uid group by hit_and_runs.status, categories.mode";
        } else {
            $sql = "select hit_and_runs.status, count(*) as counts from hit_and_runs where uid = $uid group by status";
        }
        $results = NexusDB::select($sql);
        do_log("user: $uid, sql: $sql, results: " . json_encode($results));
        if (!$formatted) {
            return $results;
        }
        if ($diffInSection) {
            $grouped = [];
            foreach ($results as $item) {
                $grouped[$item['mode']][$item['status']] = $item['counts'];
            }
            $out = [];
            foreach (SearchBox::listSections() as $key => $info) {
                $out[] = sprintf(
                    '%s: %s/<font color="red">%s</font>/%s',
                    $info['text'],
                    $grouped[$info['mode']][HitAndRun::STATUS_INSPECTING] ?? 0,
                    $grouped[$info['mode']][HitAndRun::STATUS_UNREACHED] ?? 0,
                    HitAndRun::getConfig('ban_user_when_counts_reach', $info['mode'])
                );
            }
            return implode(" ", $out);
        } else {
            $grouped = [];
            foreach ($results as $item) {
                $grouped[$item['status']] = $item['counts'];
            }
            foreach (SearchBox::listSections() as $key => $info) {
                if ($key == SearchBox::SECTION_BROWSE) {
                    return sprintf(
                        '%s/<font color="red">%s</font>/%s',
                        $grouped[HitAndRun::STATUS_INSPECTING] ?? 0,
                        $grouped[HitAndRun::STATUS_UNREACHED] ?? 0,
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
        return HitAndRun::CAN_PARDON_STATUS;
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

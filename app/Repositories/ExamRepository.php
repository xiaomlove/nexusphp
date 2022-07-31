<?php
namespace App\Repositories;

use App\Exceptions\NexusException;
use App\Models\Exam;
use App\Models\ExamProgress;
use App\Models\ExamUser;
use App\Models\Message;
use App\Models\Setting;
use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\User;
use App\Models\UserBanLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ExamRepository extends BaseRepository
{
    public function getList(array $params)
    {
        $query = Exam::query();
        $query->orderBy('priority', 'desc')->orderBy('id', 'asc');
        return $query->paginate();
    }

    public function store(array $params)
    {
        $this->checkIndexes($params);
        $this->checkBeginEnd($params);
        $this->checkFilters($params);
        /**
         * does not limit this
         * @since 1.7.4
         */
//        $valid = $this->listValid(null, Exam::DISCOVERED_YES);
//        if ($valid->isNotEmpty() && $params['status'] == Exam::STATUS_ENABLED) {
//            throw new NexusException("Enabled and discovered exam already exists.");
//        }
        $exam = Exam::query()->create($this->formatParams($params));
        return $exam;
    }

    public function update(array $params, $id)
    {
        $this->checkIndexes($params);
        $this->checkBeginEnd($params);
        $this->checkFilters($params);
        /**
         * does not limit this
         * @since 1.7.4
         */
//        $valid = $this->listValid($id, Exam::DISCOVERED_YES);
//        if ($valid->isNotEmpty() && $params['status'] == Exam::STATUS_ENABLED) {
//            throw new NexusException("Enabled and discovered exam already exists.");
//        }
        $exam = Exam::query()->findOrFail($id);
        $exam->update($this->formatParams($params));
        return $exam;
    }

    private function formatParams(array $params): array
    {
        if (isset($params['begin']) && $params['begin'] == '') {
            $params['begin'] = null;
        }
        if (isset($params['end']) && $params['end'] == '') {
            $params['end'] = null;
        }
        $params['priority'] = intval($params['priority'] ?? 0);
        return $params;
    }

    private function checkIndexes(array $params): bool
    {
        if (empty($params['indexes'])) {
            throw new \InvalidArgumentException("Require index.");
        }
        $validIndex = [];
        foreach ($params['indexes'] as $index) {
            if (isset($index['checked']) && !$index['checked']) {
                continue;
            }
            if (isset($validIndex[$index['index']])) {
                throw new \InvalidArgumentException(nexus_trans('admin.resources.exam.index_duplicate', ['index' => nexus_trans("exam.index_text_{$index['index']}")]));
            }
            if (isset($index['require_value']) && !ctype_digit((string)$index['require_value'])) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid require value for index: %s.', $index['index']
                ));
            }
            $validIndex[$index['index']] = $index;
        }
        if (empty($validIndex)) {
            throw new \InvalidArgumentException("Require valid index.");
        }
        return true;
    }

    private function checkBeginEnd(array $params): bool
    {
        if (!empty($params['begin']) && !empty($params['end']) && empty($params['duration'])) {
            return true;
        }
        if (empty($params['begin']) && empty($params['end']) && isset($params['duration']) && ctype_digit((string)$params['duration']) && $params['duration'] > 0) {
            return true;
        }

        throw new \InvalidArgumentException("Require begin and end or only duration.");
    }

    private function checkFilters(array $params)
    {
        $filters = $params['filters'];
        $hasValid = false;

        $filter = Exam::FILTER_USER_CLASS;
        if (!empty($filters[$filter])) {
            $hasValid = true;
            $diff = array_diff($filters[$filter], array_keys(User::$classes));
            if (!empty($diff)) {
                throw new \InvalidArgumentException(sprintf('Invalid user class: %s', json_encode($diff)));
            }
        }

        $filter = Exam::FILTER_USER_DONATE;
        if (!empty($filters[$filter])) {
            $hasValid = true;
            $diff = array_diff($filters[$filter], array_keys(User::$donateStatus));
            if (!empty($diff)) {
                throw new \InvalidArgumentException(sprintf('Invalid user donate status: %s', json_encode($diff)));
            }
        }

        $filter = Exam::FILTER_USER_REGISTER_TIME_RANGE;
        $begin = $filters[$filter][0] ?? null;
        $end = $filters[$filter][1] ?? null;
        if ($begin) {
            if (strtotime($begin)) {
                $hasValid = true;
            } else {
                throw new \InvalidArgumentException("Invalid user register time begin: $begin" );
            }
        }
        if ($end) {
            if (strtotime($end)) {
                $hasValid = true;
            } else {
                throw new \InvalidArgumentException("Invalid user register time end: $end");
            }
        }

        if (!$hasValid) {
            throw new \InvalidArgumentException("No valid filters");
        }

        return true;
    }

    public function getDetail($id)
    {
        $exam = Exam::query()->findOrFail($id);
        return $exam;
    }

    /**
     * delete an exam task, also will delete all exam user and progress.
     *
     * @param $id
     * @return bool
     */
    public function delete($id)
    {
        $exam = Exam::query()->findOrFail($id);
        DB::transaction(function () use ($exam) {
            do {
                $deleted = ExamUser::query()->where('exam_id', $exam->id)->limit(10000)->delete();
            } while ($deleted > 0);
            do {
                $deleted = ExamProgress::query()->where('exam_id', $exam->id)->limit(10000)->delete();
            } while ($deleted > 0);
            $exam->delete();
        });
        return true;
    }

    public function listIndexes()
    {
        $out = [];
        foreach(Exam::$indexes as $key => $value) {
            $value['index'] = $key;
            $out[] = $value;
        }
        return $out;
    }

    /**
     * list valid exams
     *
     * @param null $excludeId
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function listValid($excludeId = null, $isDiscovered = null)
    {
        $now = Carbon::now();
        $query = Exam::query()
            ->where('status', Exam::STATUS_ENABLED)
            ->whereRaw("if(begin is not null and end is not null, begin <= '$now' and end >= '$now', duration > 0)")
        ;

        if (!is_null($excludeId)) {
            $query->whereNotIn('id', Arr::wrap($excludeId));
        }
        if (!is_null($isDiscovered)) {
            $query->where('is_discovered', $isDiscovered);
        }
        return $query->orderBy('priority', 'desc')->orderBy('id', 'asc')->get();
    }

    /**
     * list user match exams
     *
     * @param $uid
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function listMatchExam($uid)
    {
        $logPrefix = "uid: $uid";
        $exams = $this->listValid();
        if ($exams->isEmpty()) {
            do_log("$logPrefix, no valid exam.");
            return $exams;
        }
        $matched = $exams->filter(function (Exam $exam) use ($uid, $logPrefix) {
            return $this->isExamMatchUser($exam, $uid);
        });

        return $matched;
    }

    private function isExamMatchUser(Exam $exam, $user): bool
    {
        if (!$user instanceof User) {
            $user = User::query()->findOrFail(intval($user), ['id', 'username', 'added', 'class']);
        }
        $logPrefix = sprintf('exam: %s, user: %s', $exam->id, $user->id);
        $filters = $exam->filters;

        $filter = Exam::FILTER_USER_CLASS;
        if (!empty($filters->{$filter}) && !in_array($user->class, $filters->{$filter})) {
            do_log("$logPrefix, user class: {$user->class} not in: " . json_encode($filters->{$filter}));
            return false;
        }

        $filter = Exam::FILTER_USER_DONATE;
        if (!empty($filters->{$filter}) && !in_array($user->donate_status, $filters->{$filter})) {
            do_log("$logPrefix, user donate status: {$user->donate_status} not in: " . json_encode($filters->{$filter}));
            return false;
        }

        $filter = Exam::FILTER_USER_REGISTER_TIME_RANGE;
        $added = $user->added->toDateTimeString();
        $registerTimeBegin = isset($filters->{$filter}[0]) ? Carbon::parse($filters->{$filter}[0])->toDateTimeString() : '';
        $registerTimeEnd = isset($filters->{$filter}[1]) ? Carbon::parse($filters->{$filter}[1])->toDateTimeString() : '';
        if (!empty($registerTimeBegin) && $added < $registerTimeBegin) {
            do_log("$logPrefix, user added: $added not bigger than begin: " . $registerTimeBegin);
            return false;
        }
        if (!empty($registerTimeEnd) && $added > $registerTimeEnd) {
            do_log("$logPrefix, user added: $added not less than end: " . $registerTimeEnd);
            return false;
        }

        return true;
    }


    /**
     * assign exam to user
     *
     * @param int $uid
     * @param int $examId
     * @param null $begin
     * @param null $end
     * @return mixed
     */
    public function assignToUser(int $uid, int $examId, $begin = null, $end = null)
    {
        $logPrefix = "uid: $uid, examId: $examId, begin: $begin, end: $end";
        $exam = Exam::query()->find($examId);
        $user = User::query()->findOrFail($uid);
        if (Auth::user()->class <= $user->class) {
            throw new NexusException("No permission !");
        }
        if (!$this->isExamMatchUser($exam, $user)) {
            throw new NexusException("Exam: {$exam->id} no match this user.");
        }
        if ($user->exams()->where('status', ExamUser::STATUS_NORMAL)->exists()) {
            throw new NexusException("User: $uid already has exam on the way.");
        }
        $exists = $user->exams()->where('exam_id', $exam->id)->exists();
        if ($exists) {
            throw new NexusException("Exam: {$exam->id} already assign to user: {$user->id}.");
        }
        $data = [
            'exam_id' => $exam->id,
        ];
        if ($begin && $end) {
            $logPrefix .= ", specific begin and end";
            $data['begin'] = $begin;
            $data['end'] = $end;
        }
        do_log("$logPrefix, data: " . nexus_json_encode($data));
        $examUser = $user->exams()->create($data);
        $this->updateProgress($examUser, $user);
        return $examUser;
    }

    public function listUser(array $params)
    {
        $query = ExamUser::query();
        if (!empty($params['uid'])) {
            $query->where('uid', $params['uid']);
        }
        if (!empty($params['exam_id'])) {
            $query->where('exam_id', $params['exam_id']);
        }
        if (isset($params['is_done']) && is_numeric($params['is_done'])) {
            $query->where('is_done', $params['is_done']);
        }
        if (isset($params['status']) && is_numeric($params['status'])) {
            $query->where('status', $params['status']);
        }
        list($sortField, $sortType) = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);
        $result = $query->with(['user', 'exam'])->paginate();
        return $result;

    }

    /**
     * @deprecated old version used
     * @param int $uid
     * @param int $torrentId
     * @param array $indexAndValue
     * @return bool
     * @throws NexusException
     */
    public function addProgress(int $uid, int $torrentId, array $indexAndValue)
    {
        $logPrefix = "uid: $uid, torrentId: $torrentId, indexAndValue: " . json_encode($indexAndValue);
        do_log($logPrefix);

        $user = User::query()->findOrFail($uid);
        $user->checkIsNormal();

        $now = Carbon::now()->toDateTimeString();
        $examUser = $user->exams()->where('status', ExamUser::STATUS_NORMAL)->orderBy('id', 'desc')->first();
        if (!$examUser) {
            do_log("no exam is on the way, " . last_query());
            return false;
        }
        $exam = $examUser->exam;
        if (!$exam) {
            throw new NexusException("exam: {$examUser->exam_id} not exists.");
        }
        $begin = $examUser->begin;
        $end = $examUser->end;
        if (!$begin || !$end) {
            do_log(sprintf("no begin or end, examUser: %s", $examUser->toJson()));
            return false;
        }
        if ($now < $begin || $now > $end) {
            do_log(sprintf("now: %s, not in exam time range: %s ~ %s", $now, $begin, $end));
            return false;
        }
        $indexes = collect($exam->indexes)->keyBy('index');
        do_log("examUser: " . $examUser->toJson() . ", indexes: " . $indexes->toJson());

        if (!isset($indexAndValue[Exam::INDEX_SEED_BONUS])) {
            //seed bonus is relative to user all torrents, not single one, torrentId = 0
            $torrentFields = ['id', 'visible', 'banned'];
            $torrent = Torrent::query()->findOrFail($torrentId, $torrentFields);
            $torrent->checkIsNormal($torrentFields);
        }

        $insert = [];
        foreach ($indexAndValue as $indexId => $value) {
            if (!$indexes->has($indexId)) {
                do_log(sprintf('Exam: %s does not has index: %s.', $exam->id, $indexId));
                continue;
            }
            $indexInfo = $indexes->get($indexId);
            if (!isset($indexInfo['checked']) || !$indexInfo['checked']) {
                do_log(sprintf('Exam: %s index: %s is not checked.', $exam->id, $indexId));
                continue;
            }
            $insert[] = [
                'exam_user_id' => $examUser->id,
                'uid' => $user->id,
                'exam_id' => $exam->id,
                'torrent_id' => $torrentId,
                'index' => $indexId,
                'value' => $value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        if (empty($insert)) {
            do_log("no progress to insert.");
            return false;
        }
        ExamProgress::query()->insert($insert);
        do_log("[addProgress] " . nexus_json_encode($insert));

        /**
         * Updating progress is more performance intensive and will only be done with a certain probability
         */
        $probability = (int)nexus_env('EXAM_PROGRESS_UPDATE_PROBABILITY', 60);
        $random = mt_rand(1, 100);
        do_log("probability: $probability, random: $random");
        if ($random > $probability) {
            do_log("[SKIP_UPDATE_PROGRESS], random: $random > probability: $probability", 'warning');
            return true;
        }
        $examProgress = $this->calculateProgress($examUser);
        $examProgressFormatted = $this->getProgressFormatted($exam, $examProgress);
        $examNotPassed = array_filter($examProgressFormatted, function ($item) {
            return !$item['passed'];
        });
        $update = [
            'progress' => $examProgress,
            'is_done' => count($examNotPassed) ? ExamUser::IS_DONE_NO : ExamUser::IS_DONE_YES,
        ];
        do_log("[updateProgress] " . nexus_json_encode($update));
        $examUser->update($update);
        return true;
    }

    /**
     * in exam_progress table
     * old version: value is an increment
     * new version: both value and init_value are cumulative, increment = value - init_value
     *
     * in exam_users table, progress field always is increment
     * old version: progress = sum(exam_progress.value)
     * new version：progress = exam_progress.value - exam_progress.init_value
     *
     * @param $examUser
     * @param null $user
     * @return bool
     */
    public function updateProgress($examUser, $user = null)
    {
        $beginTimestamp = microtime(true);
        if (!$examUser instanceof ExamUser) {
            $uid = intval($examUser);
            $examUser = ExamUser::query()
                ->where('uid', $uid)
                ->where('status', ExamUser::STATUS_NORMAL)
                ->get();
            if ($examUser->isEmpty()) {
                do_log("user: $uid no exam.");
                return false;
            }
            if ($examUser->count() > 1) {
                do_log("user: $uid more than one active exam.");
                return false;
            }
            $examUser = $examUser->first();
        }
        if ($examUser->status != ExamUser::STATUS_NORMAL) {
            do_log("examUser: {$examUser->id} status not normal, won't update progress.");
            return false;
        }
        if ($examUser->is_done == ExamUser::IS_DONE_YES) {
            /**
             * continue  update
             * @since v1.7.0
             */
//            do_log("examUser: {$examUser->id} is done, won't update progress.");
//            return false;
        }
        $exam = $examUser->exam;
        if (!$user instanceof User) {
            $user = $examUser->user()->select(['id', 'uploaded', 'downloaded', 'seedtime', 'leechtime', 'seedbonus'])->first();
        }
        $attributes = [
            'exam_user_id' => $examUser->id,
            'uid' => $user->id,
            'exam_id' => $exam->id,
        ];
        $logPrefix = json_encode($attributes);
        $begin = $examUser->begin;
        if (empty($begin)) {
            throw new \InvalidArgumentException("$logPrefix, exam: {$examUser->id} no begin.");
        }
        $end = $examUser->end;
        if (empty($end)) {
            throw new \InvalidArgumentException("$logPrefix, exam: {$examUser->id} no end.");
        }
        $examUserProgressFieldData = [];
        $now = now();
        foreach ($exam->indexes as $index) {
            if (!isset($index['checked']) || !$index['checked']) {
                continue;
            }
            if (!isset(Exam::$indexes[$index['index']])) {
                $msg = "Unknown index: {$index['index']}";
                do_log("$logPrefix, $msg", 'error');
                throw new \RuntimeException($msg);
            }
            do_log("$logPrefix, [HANDLING INDEX {$index['index']}]: " . json_encode($index));
            //First, collect data to store/update in table: exam_progress
            $attributes['index'] = $index['index'];
            $attributes['created_at'] = $now;
            $attributes['updated_at'] = $now;
            $attributes['value'] = $user->{Exam::$indexes[$index['index']]['source_user_field']} ?? 0;
            do_log("[GET_TOTAL_VALUE]: " . $attributes['value']);
            $newVersionProgress = ExamProgress::query()
                ->where('exam_user_id', $examUser->id)
                ->where('torrent_id', -1)
                ->where('index', $index['index'])
                ->orderBy('id', 'desc')
                ->first();
            do_log("check newVersionProgress: " . last_query() . ", exists: " . json_encode($newVersionProgress));
            if ($newVersionProgress) {
                //just need to do update the value
                if ($attributes['value'] != $newVersionProgress->value) {
                    $newVersionProgress->update(['value' => $attributes['value']]);
                    do_log("newVersionProgress [EXISTS], doUpdate: " . last_query());
                } else {
                    do_log("newVersionProgress [EXISTS], no change....");
                }
                $attributes['init_value'] = $newVersionProgress->init_value;
            } else {
                //do insert. check the init value
                $progressData = $this->calculateProgress($examUser, true);
                $increment = $progressData[$index['index']] ?? 0;
                $initValue = $attributes['value'] - $increment;
                $attributes['init_value'] = max($initValue, 0);
                do_log("total: {$attributes['value']}, increment: $increment, init_value: $initValue, final init_value: {$attributes['init_value']}");
                $attributes['torrent_id'] = -1;
                ExamProgress::query()->insert($attributes);
                do_log("newVersionProgress [NOT EXISTS], doInsert with: " . json_encode($attributes));
            }

            //Second, update exam_user.progress
            if ($index['index'] == Exam::INDEX_SEED_TIME_AVERAGE) {
                $torrentCountsRes = Snatch::query()
                    ->where('userid', $user->id)
                    ->where('completedat', '>=', $begin)
                    ->where('completedat', '<=', $end)
                    ->selectRaw("count(distinct(torrentid)) as counts")
                    ->first();
                do_log("special index: {$index['index']}, get torrent count by: " . last_query());
                //if just seeding, no download torrent, counts = 1
                if ($torrentCountsRes && $torrentCountsRes->counts > 0) {
                    $torrentCounts = $torrentCountsRes->counts;
                    do_log("torrent count: $torrentCounts");
                } else {
                    $torrentCounts = 1;
                    do_log("torrent count is 0, use 1");
                }
                $examUserProgressFieldData[$index['index']] = bcdiv(bcsub($attributes['value'], $attributes['init_value']), $torrentCounts);
                do_log(sprintf(
                    "torrentCounts > 0, examUserProgress: (total(%s) - init_value(%s)) / %s = %s",
                    $attributes['value'], $attributes['init_value'], $torrentCounts, $examUserProgressFieldData[$index['index']]
                ));
            } else {
                $examUserProgressFieldData[$index['index']] = bcsub($attributes['value'], $attributes['init_value']);
                do_log(sprintf(
                    "normal index: {$index['index']}, examUserProgress: total(%s) - init_value(%s) = %s",
                    $attributes['value'], $attributes['init_value'], $examUserProgressFieldData[$index['index']]
                ));
            }
        }
        $examProgressFormatted = $this->getProgressFormatted($exam, $examUserProgressFieldData);
        $examNotPassed = array_filter($examProgressFormatted, function ($item) {
            return !$item['passed'];
        });

        $update = [
            'progress' => $examUserProgressFieldData,
            'is_done' => count($examNotPassed) ? ExamUser::IS_DONE_NO : ExamUser::IS_DONE_YES,
        ];
        $result = $examUser->update($update);
        do_log(sprintf(
            "[UPDATE_PROGRESS] %s, result: %s, cost time: %s sec",
            json_encode($update), var_export($result, true), sprintf('%.3f', microtime(true) - $beginTimestamp)
        ));
        $examUser->progress_formatted = $examProgressFormatted;
        return $examUser;
    }


    /**
     * get user exam status
     *
     * @param $uid
     * @param null $status
     * @return mixed|null
     */
    public function getUserExamProgress($uid, $status = null)
    {
        $logPrefix = "uid: $uid";
        $query = ExamUser::query()->where('uid', $uid)->orderBy('exam_id', 'desc');
        if (!is_null($status)) {
            $query->where('status', $status);
        }
        $examUsers = $query->get();
        if ($examUsers->isEmpty()) {
            do_log("$logPrefix, no examUser, query: " . last_query());
            return null;
        }
        if ($examUsers->count() > 1) {
            do_log("$logPrefix, user exam more than 1.", 'warning');
        }
        $examUser = $examUsers->first();
        $logPrefix .= ", examUser: " . $examUser->id;
        try {
            $updateResult = $this->updateProgress($examUser);
            if ($updateResult) {
                do_log("$logPrefix, [UPDATE_PROGRESS_SUCCESS_RETURN_DIRECTLY]");
                return $updateResult;
            } else {
                do_log("$logPrefix, [UPDATE_PROGRESS_FAIL]");
            }
        } catch (\Exception $exception) {
            do_log("$logPrefix, [UPDATE_PROGRESS_FAIL]: " . $exception->getMessage(), 'error');
        }
        $exam = $examUser->exam;
        $progress = $examUser->progress;
        do_log("$logPrefix, progress: " . nexus_json_encode($progress));
        $examUser->progress = $progress;
        $examUser->progress_formatted = $this->getProgressFormatted($exam, (array)$progress);
        return $examUser;
    }

    /**
     * @param ExamUser $examUser
     * @param false $allSum
     * @return array|null
     */
    public function calculateProgress(ExamUser $examUser, $allSum = false)
    {
        $logPrefix = "examUser: " . $examUser->id;
        $begin = $examUser->begin;
        $end = $examUser->end;
        if (!$begin) {
            do_log("$logPrefix, no begin");
            return null;
        }
        if (!$end) {
            do_log("$logPrefix, no end");
            return null;
        }
        $progressSum = $examUser->progresses()
            ->where('created_at', '>=', $begin)
            ->where('created_at', '<=', $end)
            ->selectRaw("`index`, sum(`value`) as sum")
            ->groupBy(['index'])
            ->get()
            ->pluck('sum', 'index')
            ->toArray();
        $logPrefix .= ", progressSum raw: " . json_encode($progressSum) . ", query: " . last_query();
        if ($allSum) {
            do_log($logPrefix);
            return $progressSum;
        }

        $index = Exam::INDEX_SEED_TIME_AVERAGE;
        if (isset($progressSum[$index])) {
            $torrentCount = $examUser->progresses()
                ->where('index', $index)
                ->where('torrent_id', '>=', 0)
                ->selectRaw('count(distinct(torrent_id)) as torrent_count')
                ->first()
                ->torrent_count;
            $progressSum[$index] = intval($progressSum[$index] / $torrentCount);
            $logPrefix .= ", get torrent count: $torrentCount, from query: " . last_query();
        }

        do_log("$logPrefix, final progressSum: " . json_encode($progressSum));

        return $progressSum;

    }

    public function getProgressFormatted(Exam $exam, array $progress, $locale = null)
    {
        $result = [];
        foreach ($exam->indexes as $key => $index) {
            if (!isset($index['checked']) || !$index['checked']) {
                continue;
            }
            $currentValue = $progress[$index['index']] ?? 0;
            $requireValue = $index['require_value'];
            $unit = Exam::$indexes[$index['index']]['unit'] ?? '';
            switch ($index['index']) {
                case Exam::INDEX_UPLOADED:
                case Exam::INDEX_DOWNLOADED:
                    $currentValueFormatted = mksize($currentValue);
                    $requireValueAtomic = $requireValue * 1024 * 1024 * 1024;
                    break;
                case Exam::INDEX_SEED_TIME_AVERAGE:
                    $currentValueFormatted = number_format($currentValue / 3600, 2) . " $unit";
                    $requireValueAtomic = $requireValue * 3600;
                    break;
                default:
                    $currentValueFormatted = $currentValue;
                    $requireValueAtomic = $requireValue;
            }
            $index['name'] = Exam::$indexes[$index['index']]['name'] ?? '';
            $index['index_formatted'] = nexus_trans('exam.index_text_' . $index['index']);
            $index['require_value_formatted'] = "$requireValue $unit";
            $index['current_value'] = $currentValue;
            $index['current_value_formatted'] = $currentValueFormatted;
            $index['passed'] = $currentValue >= $requireValueAtomic;
            $result[] = $index;
        }
        return $result;
    }


    public function removeExamUser(int $examUserId)
    {
        $examUser = ExamUser::query()->findOrFail($examUserId);
        $result = DB::transaction(function () use ($examUser) {
            do {
                $deleted = $examUser->progresses()->limit(10000)->delete();
            } while ($deleted > 0);
            return $examUser->delete();
        });
        return $result;
    }

    public function avoidExamUser(int $examUserId)
    {
        $examUser = ExamUser::query()->where('status',ExamUser::STATUS_NORMAL)->findOrFail($examUserId);
        $result = $examUser->update(['status' => ExamUser::STATUS_AVOIDED]);
        return $result;
    }

    public function removeExamUserBulk(array $params, User $user)
    {
        $result = $this->getExamUserBulkQuery($params)->delete();
        do_log(sprintf(
            'user: %s bulk delete by filter: %s, result: %s',
            $user->id, json_encode($params), json_encode($result)
        ), 'alert');
        return $result;
    }

    public function avoidExamUserBulk(array $params, User $user): int
    {
        $query = $this->getExamUserBulkQuery($params)->where('status', ExamUser::STATUS_NORMAL);
        $update = [
            'status' => ExamUser::STATUS_AVOIDED,
        ];
        $affected =  $query->update($update);
        do_log(sprintf(
            'user: %s bulk avoid by filter: %s, affected: %s',
            $user->id, json_encode($params), $affected
        ), 'alert');
        return $affected;
    }

    private function getExamUserBulkQuery(array $params): Builder
    {
        $query = ExamUser::query();
        $hasWhere = false;
        $validFilter = ['uid', 'id', 'exam_id'];
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

    public function recoverExamUser(int $examUserId)
    {
        $examUser = ExamUser::query()->where('status',ExamUser::STATUS_AVOIDED)->findOrFail($examUserId);
        $result = $examUser->update(['status' => ExamUser::STATUS_NORMAL]);
        return $result;
    }

    public function cronjonAssign()
    {
        $exams = $this->listValid(null, Exam::DISCOVERED_YES);
        if ($exams->isEmpty()) {
            do_log("No valid and discovered exam.");
            return false;
        }
        /**
         * valid exam can has multiple
         *
         * @since 1.7.4
         */
//        if ($exams->count() > 1) {
//            do_log("Valid and discovered exam more than 1.", "error");
//            return false;
//        }

        $result = 0;
        foreach ($exams as $exam) {
            $start = microtime(true);
            $count = $this->fetchUserAndDoAssign($exam);
            do_log(sprintf(
                'exam: %s assign to user count: %s -> %s, cost time: %s',
                $exam->id, gettype($count), $count, number_format(microtime(true) - $start, 3)
            ));
            $result += $count;
        }
        return $result;

    }

    public function fetchUserAndDoAssign(Exam $exam): bool|int
    {
        $filters = $exam->filters;
        do_log("exam: {$exam->id}, filters: " . nexus_json_encode($filters));
        $userTable = (new User())->getTable();
        $examUserTable = (new ExamUser())->getTable();
        //Fetch user doesn't has this exam and doesn't has any other unfinished exam
        $baseQuery = User::query()
            ->where("$userTable.enabled", User::ENABLED_YES)
            ->where("$userTable.status", User::STATUS_CONFIRMED)
            ->selectRaw("$userTable.*")
            ->orderBy("$userTable.id", "asc");

        $filter = Exam::FILTER_USER_CLASS;
        if (!empty($filters->$filter)) {
            $baseQuery->whereIn("$userTable.class", $filters->$filter);
        }

        $filter = Exam::FILTER_USER_DONATE;
        if (!empty($filters->$filter) && count($filters->$filter) == 1) {
            $donateStatus = $filters->$filter[0];
            if ($donateStatus == User::DONATE_YES) {
                $baseQuery->where(function (Builder $query) {
                    $query->where('donor', 'yes')->where(function (Builder $query) {
                        $query->where('donoruntil', '0000-00-00 00:00:00')->orWhereNull('donoruntil')->orWhere('donoruntil', '>=', Carbon::now());
                    });
                });
            } elseif ($donateStatus == User::DONATE_NO) {
                $baseQuery->where(function (Builder $query) {
                    $query->where('donor', 'no')->orWhere(function (Builder $query) {
                        $query->where('donoruntil', '!=','0000-00-00 00:00:00')->whereNotNull('donoruntil')->where('donoruntil', '<', Carbon::now());
                    });
                });
            } else {
                do_log("{$exam->id} filter $filter: $donateStatus invalid.", "error");
                return false;
            }
        }

        $filter = Exam::FILTER_USER_REGISTER_TIME_RANGE;
        $range = $filters->$filter;
        if (!empty($range)) {
            if (!empty($range[0])) {
                $baseQuery->where("$userTable.added", ">=", Carbon::parse($range[0])->toDateTimeString());
            }
            if (!empty($range[1])) {
                $baseQuery->where("$userTable.added", '<=', Carbon::parse($range[1])->toDateTimeString());
            }
        }
        //Does not has this exam
        $baseQuery->whereDoesntHave('exams', function (Builder $query) use ($exam) {
            $query->where('exam_id', $exam->id);
        });
        //Does not has any other normal exam
        $baseQuery->whereDoesntHave('exams', function (Builder $query) use ($exam) {
            $query->where('status',ExamUser::STATUS_NORMAL);
        });

        $size = 1000;
        $minId = 0;
        $result = 0;
        while (true) {
            $logPrefix = sprintf('[%s], exam: %s, size: %s', __FUNCTION__, $exam->id , $size);
            $users = (clone $baseQuery)->where("$userTable.id", ">", $minId)->limit($size)->get();
            do_log("$logPrefix, query: " . last_query() . ", counts: " . $users->count());
            if ($users->isEmpty()) {
                do_log("no more data...");
                break;
            }
            $now = Carbon::now()->toDateTimeString();
            foreach ($users as $user) {
                $minId = $user->id;
                $currentLogPrefix = sprintf("$logPrefix, user: %s", $user->id);
                $insert = [
                    'uid' => $user->id,
                    'exam_id' => $exam->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                do_log("$currentLogPrefix, exam will be assigned to this user.");
                $examUser = ExamUser::query()->create($insert);
                $this->updateProgress($examUser, $user);
                $result++;
            }
        }
        return $result;
    }

    public function cronjobCheckout($ignoreTimeRange = false): int
    {
        $now = Carbon::now()->toDateTimeString();
        $examUserTable = (new ExamUser())->getTable();
        $examTable = (new Exam())->getTable();
        $userTable = (new User())->getTable();
        $baseQuery = ExamUser::query()
            ->join($examTable, "$examUserTable.exam_id", "=", "$examTable.id")
            ->where("$examUserTable.status", ExamUser::STATUS_NORMAL)
            ->selectRaw("$examUserTable.*")
            ->with(['exam', 'user', 'user.language'])
            ->orderBy("$examUserTable.id", "asc");
        if (!$ignoreTimeRange) {
            $whenThens = [];
            $whenThens[] = "when $examUserTable.`end` is not null then $examUserTable.`end` < '$now'";
            $whenThens[] = "when $examTable.`end` is not null then $examTable.`end` < '$now'";
            $whenThens[] = "when $examTable.duration > 0 then date_add($examUserTable.created_at, interval $examTable.duration day) < '$now'";
            $baseQuery->whereRaw(sprintf("case %s else false end", implode(" ", $whenThens)));
        }

        $size = 1000;
        $minId = 0;
        $result = 0;

        while (true) {
            $logPrefix = sprintf('[%s], size: %s', __FUNCTION__, $size);
            $examUsers = (clone $baseQuery)->where("$examUserTable.id", ">", $minId)->limit($size)->get();
            do_log("$logPrefix, fetch exam users: {$examUsers->count()} by: " . last_query());
            if ($examUsers->isEmpty()) {
                do_log("$logPrefix, no more data...");
                break;
            }
            $result += $examUsers->count();
            $now = Carbon::now()->toDateTimeString();
            $examUserIdArr = $uidToDisable = $messageToSend = $userBanLog = $userModcommentUpdate = [];
            foreach ($examUsers as $examUser) {
                $minId = $examUser->id;
                $examUserIdArr[] = $examUser->id;
                $uid = $examUser->uid;
                $exam = $examUser->exam;
                $currentLogPrefix = sprintf("$logPrefix, user: %s, exam: %s, examUser: %s", $uid, $examUser->exam_id, $examUser->id);
                if (!$examUser->user) {
                    do_log("$currentLogPrefix, user not exists, remove it!", 'error');
                    $examUser->progresses()->delete();
                    $examUser->delete();
                    continue;
                }
                $locale = $examUser->user->locale;
                if ($examUser->is_done) {
                    do_log("$currentLogPrefix, [is_done]");
                    $subjectTransKey = 'exam.checkout_pass_message_subject';
                    $msgTransKey = 'exam.checkout_pass_message_content';
                } else {
                    do_log("$currentLogPrefix, [will be banned]");
                    $subjectTransKey = 'exam.checkout_not_pass_message_subject';
                    $msgTransKey = 'exam.checkout_not_pass_message_content';
                    //ban user
                    $uidToDisable[] = $uid;
                    $userModcomment = nexus_trans('exam.ban_user_modcomment', [
                        'exam_name' => $exam->name,
                        'begin' => $examUser->begin,
                        'end' => $examUser->end
                    ], $locale);
                    $userModcomment = sprintf('%s - %s', date('Y-m-d'), $userModcomment);
                    $userModcommentUpdate[] = sprintf("when `id` = %s then concat_ws('\n', '%s', modcomment)", $uid, $userModcomment);
                    $banLogReason = nexus_trans('exam.ban_log_reason', [
                        'exam_name' => $exam->name,
                        'begin' => $examUser->begin,
                        'end' => $examUser->end,
                    ], $locale);
                    $userBanLog[] = [
                        'uid' => $uid,
                        'username' => $examUser->user->username,
                        'reason' => $banLogReason,
                    ];
                }
                $subject =  nexus_trans($subjectTransKey, [], $locale);
                $msg = nexus_trans($msgTransKey, [
                    'exam_name' => $exam->name,
                    'begin' => $examUser->begin,
                    'end' => $examUser->end
                ], $locale);
                $messageToSend[] = [
                    'receiver' => $uid,
                    'added' => $now,
                    'subject' => $subject,
                    'msg' => $msg
                ];
            }
            DB::transaction(function () use ($uidToDisable, $messageToSend, $examUserIdArr, $userBanLog, $userModcommentUpdate, $userTable, $logPrefix) {
                ExamUser::query()->whereIn('id', $examUserIdArr)->update(['status' => ExamUser::STATUS_FINISHED]);
                do {
                    $deleted = ExamProgress::query()->whereIn('exam_user_id', $examUserIdArr)->limit(10000)->delete();
                    do_log("$logPrefix, [DELETE_EXAM_PROGRESS], deleted: $deleted");
                } while($deleted > 0);
                Message::query()->insert($messageToSend);
                if (!empty($uidToDisable)) {
                    $uidStr = implode(', ', $uidToDisable);
                    $sql = sprintf(
                        "update %s set enabled = '%s', modcomment = case %s end where id in (%s)",
                        $userTable, User::ENABLED_NO, implode(' ', $userModcommentUpdate), $uidStr
                    );
                    $updateResult = DB::update($sql);
                    do_log(sprintf("$logPrefix, disable %s users: %s, sql: %s, updateResult: %s", count($uidToDisable), $uidStr, $sql, $updateResult));
                }
                if (!empty($userBanLog)) {
                    UserBanLog::query()->insert($userBanLog);
                }
            });
        }
        return $result;
    }

    public function updateProgressBulk(): array
    {
        $query = ExamUser::query()
            ->where('status', ExamUser::STATUS_NORMAL)
            ->where('is_done', ExamUser::IS_DONE_NO);
        $page = 1;
        $size = 1000;
        $total = $success = 0;
        while (true) {
            $logPrefix = "[UPDATE_EXAM_PROGRESS], page: $page, size: $size";
            $rows = $query->forPage($page, $size)->get();
            $count = $rows->count();
            $total += $count;
            do_log("$logPrefix, " . last_query() . ", count: $count");
            if ($rows->isEmpty()) {
                do_log("$logPrefix, no more data...");
                break;
            }
            foreach ($rows as $row) {
                $result = $this->updateProgress($row);
                do_log("$logPrefix, examUser: " . $row->toJson() . ", result type: " . gettype($result));
                if ($result != false) {
                    $success += 1;
                }
            }
            $page++;
        }
        $result = compact('total', 'success');
        do_log("$logPrefix, result: " . json_encode($result));
        return $result;
    }

}

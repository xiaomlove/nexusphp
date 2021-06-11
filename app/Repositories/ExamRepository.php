<?php
namespace App\Repositories;

use App\Exceptions\NexusException;
use App\Models\Exam;
use App\Models\ExamIndexInitValue;
use App\Models\ExamProgress;
use App\Models\ExamUser;
use App\Models\Message;
use App\Models\Setting;
use App\Models\Snatch;
use App\Models\Torrent;
use App\Models\User;
use App\Models\UserBanLog;
use Carbon\Carbon;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ExamRepository extends BaseRepository
{
    public function getList(array $params)
    {
        $query = Exam::query();
        list($sortField, $sortType) = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);
        return $query->paginate();
    }

    public function store(array $params)
    {
        $this->checkIndexes($params);
        $this->checkBeginEnd($params);
        $valid = $this->listValid(null, Exam::DISCOVERED_YES);
        if ($valid->isNotEmpty() && $params['status'] == Exam::STATUS_ENABLED) {
            throw new NexusException("Enabled and discovered exam already exists.");
        }
        $exam = Exam::query()->create($params);
        return $exam;
    }

    public function update(array $params, $id)
    {
        $this->checkIndexes($params);
        $this->checkBeginEnd($params);
        $valid = $this->listValid($id, Exam::DISCOVERED_YES);
        if ($valid->isNotEmpty() && $params['status'] == Exam::STATUS_ENABLED) {
            throw new NexusException("Enabled and discovered exam already exists.");
        }
        $exam = Exam::query()->findOrFail($id);
        $exam->update($params);
        return $exam;
    }

    private function checkIndexes(array $params)
    {
        if (empty($params['indexes'])) {
            throw new \InvalidArgumentException("Require index.");
        }
        $validIndex = [];
        foreach ($params['indexes'] as $index) {
            if (isset($index['checked']) && !$index['checked']) {
                continue;
            }
            if (isset($index['require_value']) && !ctype_digit((string)$index['require_value'])) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid require value for index: %s.', $index['index']
                ));
            }
            $validIndex[] = $index;
        }
        if (empty($validIndex)) {
            throw new \InvalidArgumentException("Require valid index.");
        }
        return true;
    }

    private function checkBeginEnd(array $params)
    {
        if (!empty($params['begin']) && !empty($params['end']) && empty($params['duration'])) {
            return true;
        }
        if (empty($params['begin']) && empty($params['end']) && isset($params['duration']) && ctype_digit((string)$params['duration']) && $params['duration'] > 0) {
            return true;
        }

        throw new \InvalidArgumentException("Require begin and end or only duration.");
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
            ->orderBy('id', 'desc');
        if (!is_null($excludeId)) {
            $query->whereNotIn('id', Arr::wrap($excludeId));
        }
        if (!is_null($isDiscovered)) {
            $query->where('is_discovered', $isDiscovered);
        }
        return $query->get();
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

    private function isExamMatchUser(Exam $exam, $user)
    {
        if (!$user instanceof User) {
            $user = User::query()->findOrFail(intval($user), ['id', 'username', 'added', 'class']);
        }
        $logPrefix = sprintf('exam: %s, user: %s', $exam->id, $user->id);
        $filters = $exam->filters;

        $filter = Exam::FILTER_USER_CLASS;
        if (empty($filters->{$filter})) {
            do_log("$logPrefix, exam: {$exam->id} no class");
            return false;
        }
        if (!in_array($user->class, $filters->{$filter})) {
            do_log("$logPrefix, user class: {$user->class} not in: " . json_encode($filters->{$filter}));
            return false;
        }

        $filter = Exam::FILTER_USER_DONATE;
        if (empty($filters->{$filter})) {
            do_log("$logPrefix, exam: {$exam->id} no donate");
            return false;
        }
        if (!in_array($user->donate_status, $filters->{$filter})) {
            do_log("$logPrefix, user donate status: {$user->donate_status} not in: " . json_encode($filters->{$filter}));
            return false;
        }


        $filter = Exam::FILTER_USER_REGISTER_TIME_RANGE;
        if (empty($filters->{$filter})) {
            do_log("$logPrefix, exam: {$exam->id} no register time range");
            return false;
        }
        if (!$user->added) {
            do_log("$logPrefix, user no added time", 'warning');
            return false;
        }
        $added = $user->added->toDateTimeString();
        $registerTimeBegin = isset($filters->{$filter}[0]) ? Carbon::parse($filters->{$filter}[0])->toDateTimeString() : '';
        $registerTimeEnd = isset($filters->{$filter}[1]) ? Carbon::parse($filters->{$filter}[1])->toDateTimeString() : '';
        if (empty($registerTimeBegin)) {
            do_log("$logPrefix, exam: {$exam->id} no register_time_begin");
            return false;
        }
        if ($added < $registerTimeBegin) {
            do_log("$logPrefix, user added: $added not after: " . $registerTimeBegin);
            return false;
        }

        if (empty($registerTimeEnd)) {
            do_log("$logPrefix, exam: {$exam->id} no register_time_end");
            return false;
        }
        if ($added > $registerTimeEnd) {
            do_log("$logPrefix, user added: $added not before: " . $registerTimeEnd);
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
        list($sortField, $sortType) = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);
        $result = $query->with(['user', 'exam'])->paginate();
        return $result;

    }

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

    public function updateProgress($examUser, $user = null)
    {
        if (!$examUser instanceof ExamUser) {
            $examUser = ExamUser::query()->findOrFail((int)$examUser);
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
        $currentProgrss = [];
        foreach ($exam->indexes as $key => $index) {
            if (!isset($index['checked']) || !$index['checked']) {
                continue;
            }
            $attributes['index'] = $index['index'];
            if ($index['index'] == Exam::INDEX_UPLOADED) {
                $attributes['value'] = $user->uploaded;
            } elseif ($index['index'] == Exam::INDEX_DOWNLOADED) {
                $attributes['value'] = $user->downloaded;
            } elseif ($index['index'] == Exam::INDEX_SEED_BONUS) {
                $attributes['value'] = $user->seedbonus;
            } elseif ($index['index'] == Exam::INDEX_SEED_TIME_AVERAGE) {
                $torrentCountsRes = Snatch::query()
                    ->where('userid', $user->id)
                    ->where('completedat', '>=', $begin)
                    ->where('completedat', '<=', $end)
                    ->selectRaw("count(distinct(torrentid)) as torrent_counts")
                    ->first();
                do_log("$logPrefix, torrentCountsRes: " . json_encode($torrentCountsRes));
                if ($torrentCountsRes && $torrentCountsRes->torrent_counts > 0) {
                    $value = $user->seedtime / $torrentCountsRes->torrent_counts;
                } else {
                    $value = 0;
                }
                $attributes['value'] = $value;
            } else {
                $msg = "Unknown index: {$index['index']}";
                do_log("$logPrefix, $msg", 'error');
                throw new \RuntimeException($msg);
            }
            //at the begining, value = init_value, and then value increase.
            $progress = ExamProgress::query()
                ->where('exam_user_id', $examUser->id)
                ->where('torrent_id', -1)
                ->where('index', $index['index'])
                ->orderBy('id', 'desc')
                ->first();
            if ($progress) {
                //do update
                $progress->update(['value' => $attributes['value']]);
                do_log("$logPrefix, doUpdat: " . last_query());
            } else {
                //do insert
                $attributes['init_value'] = $attributes['value'];
                $attributes['torrent_id'] = -1;
                ExamProgress::query()->insert($attributes);
                do_log("$logPrefix, doInsert with: " . json_encode($attributes));
            }
            $currentProgrss[$index['index']] = $attributes['value'];
        }
        $examProgressFormatted = $this->getProgressFormatted($exam, $currentProgrss);
        $examNotPassed = array_filter($examProgressFormatted, function ($item) {
            return !$item['passed'];
        });
        $update = [
            'progress' => $currentProgrss,
            'is_done' => count($examNotPassed) ? ExamUser::IS_DONE_NO : ExamUser::IS_DONE_YES,
        ];
        do_log("[UPDATE_PROGRESS] " . nexus_json_encode($update));
        $examUser->update($update);

        return true;
    }

    public function getUserExamProgress($uid, $status = null, $with = ['exam', 'user'])
    {
        $logPrefix = "uid: $uid";
        $query = ExamUser::query()->where('uid', $uid)->orderBy('exam_id', 'desc');
        if (!is_null($status)) {
            $query->where('status', $status);
        }
        if (!empty($with)) {
            $query->with($with);
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
        $exam = $examUser->exam;
//        $progress = $this->calculateProgress($examUser);
        $progress = $examUser->progress;
        do_log("$logPrefix, progress: " . nexus_json_encode($progress));
        $examUser->progress = $progress;
        $examUser->progress_formatted = $this->getProgressFormatted($exam, (array)$progress);
        return $examUser;
    }

    public function calculateProgress(ExamUser $examUser)
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

    private function getProgressFormatted(Exam $exam, array $progress, $locale = null)
    {
        $result = [];
        foreach ($exam->indexes as $key => $index) {
            if (!isset($index['checked']) || !$index['checked']) {
                continue;
            }
            $currentValue = $progress[$index['index']] ?? 0;
            $requireValue = $index['require_value'];
            switch ($index['index']) {
                case Exam::INDEX_UPLOADED:
                case Exam::INDEX_DOWNLOADED:
                    $currentValueFormatted = mksize($currentValue);
                    $requireValueAtomic = $requireValue * 1024 * 1024 * 1024;
                    break;
                case Exam::INDEX_SEED_TIME_AVERAGE:
                    $currentValueFormatted = number_format($currentValue / 3600, 2) . " {$index['unit']}";
                    $requireValueAtomic = $requireValue * 3600;
                    break;
                default:
                    $currentValueFormatted = $currentValue;
                    $requireValueAtomic = $requireValue;
            }
            $index['name'] = Exam::$indexes[$index['index']]['name'] ?? '';
            $index['index_formatted'] = nexus_trans('exam.index_text_' . $index['index']);
            $index['require_value_formatted'] = "$requireValue " . ($index['unit'] ?? '');
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
        if ($exams->count() > 1) {
            do_log("Valid and discovered exam more than 1.", "error");
            return false;
        }
        /** @var Exam $exam */
        $exam = $exams->first();
        $filters = $exam->filters;
        do_log("exam: {$exam->id}, filters: " . nexus_json_encode($filters));
        $userTable = (new User())->getTable();
        $examUserTable = (new ExamUser())->getTable();
        $baseQuery = User::query()
            ->leftJoin($examUserTable, function (JoinClause $join) use ($examUserTable, $userTable) {
                $join->on("$userTable.id", "=", "$examUserTable.uid");
            })
            ->where("$userTable.enabled", User::ENABLED_YES)
            ->where("$userTable.status", User::STATUS_CONFIRMED)
            ->whereRaw("$examUserTable.id is null")
            ->selectRaw("$userTable.*")
            ->orderBy("$userTable.id", "asc");

        if (empty($filters->classes)) {
            do_log("{$exam->id} no classes.");
            return false;
        } else {
            $baseQuery->whereIn("$userTable.class", $filters->classes);
        }
        if (empty($filters->register_time_range) || empty($filters->register_time_range[0]) || empty($filters->register_time_range[1])) {
            do_log("{$exam->id} no register_time_range.");
            return false;
        } else {
            $baseQuery->where("$userTable.added", ">=", Carbon::parse($filters->register_time_range[0])->toDateTimeString())
                ->where("$userTable.added", '<=', Carbon::parse($filters->register_time_range[1])->toDateTimeString());
        }

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
            $insert = [];
            $now = Carbon::now()->toDateTimeString();
            foreach ($users as $user) {
                $minId = $user->id;
                $currentLogPrefix = sprintf("$logPrefix, user: %s", $user->id);
//                if (!$this->isExamMatchUser($exam, $user)) {
//                    do_log("$currentLogPrefix, exam not match this user.");
//                    continue;
//                }
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

    public function cronjobCheckout($ignoreTimeRange = false)
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
//                do {
//                    $deleted = ExamProgress::query()->whereIn('exam_user_id', $examUserIdArr)->limit(10000)->delete();
//                    do_log("$logPrefix, [DELETE_EXAM_PROGRESS], deleted: $deleted");
//                } while($deleted > 0);
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




}

<?php
namespace App\Repositories;

use App\Exceptions\NexusException;
use App\Models\Exam;
use App\Models\ExamProgress;
use App\Models\ExamUser;
use App\Models\Message;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\User;
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
        $valid = $this->listValid();
        if ($valid->isNotEmpty()) {
            throw new NexusException("Valid exam already exists.");
        }
        $exam = Exam::query()->create($params);
        return $exam;
    }

    public function update(array $params, $id)
    {
        $this->checkIndexes($params);
        $valid = $this->listValid($id);
        if ($valid->isNotEmpty()) {
            throw new NexusException("Valid exam already exists.");
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
        $validIndex = array_filter($params['indexes'], function ($value) {
            return isset($value['checked']) && $value['checked']
                && isset($value['require_value']) && $value['require_value'] > 0;
        });
        if (empty($validIndex)) {
            throw new \InvalidArgumentException("Require valid index.");
        }
        return true;
    }

    public function getDetail($id)
    {
        $exam = Exam::query()->findOrFail($id);
        return $exam;
    }

    public function delete($id)
    {
        $exam = Exam::query()->findOrFail($id);
        $result = $exam->delete();
        return $result;
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
    public function listValid($excludeId = null)
    {
        $now = Carbon::now();
        $query = Exam::query()
            ->where('begin', '<=', $now)
            ->where('end', '>=', $now)
            ->where('status', Exam::STATUS_ENABLED)
            ->orderBy('id', 'desc');
        if ($excludeId) {
            $query->whereNotIn('id', Arr::wrap($excludeId));
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
        if (empty($filters->classes)) {
            do_log("$logPrefix, exam: {$exam->id} no class");
            return false;
        }
        if (!in_array($user->class, $filters->classes)) {
            do_log("$logPrefix, user class: {$user->class} not in: " . json_encode($filters));
            return false;
        }
        if (!$user->added) {
            do_log("$logPrefix, user no added time", 'warning');
            return false;
        }

        $added = $user->added->toDateTimeString();
        $registerTimeBegin = $filters->register_time_range[0] ? Carbon::parse($filters->register_time_range[0])->toDateString() : '';
        $registerTimeEnd = $filters->register_time_range[1] ? Carbon::parse($filters->register_time_range[1])->toDateString() : '';
        if (empty($registerTimeBegin)) {
            do_log("$logPrefix, exam: {$exam->id} no register_time_begin");
            return false;
        }
        if ($added < $registerTimeBegin) {
            do_log("$logPrefix, added: $added not after: " . $registerTimeBegin);
            return false;
        }

        if (empty($registerTimeEnd)) {
            do_log("$logPrefix, exam: {$exam->id} no register_time_end");
            return false;
        }
        if ($added > $registerTimeEnd) {
            do_log("$logPrefix, added: $added not before: " . $registerTimeEnd);
            return false;
        }
        return true;
    }


    /**
     * assign exam to user
     *
     * @param int $uid
     * @param null $examId
     * @param null $begin
     * @param null $end
     * @return mixed
     */
    public function assignToUser(int $uid, $examId = null, $begin = null, $end = null)
    {
        $logPrefix = "uid: $uid, examId: $examId, begin: $begin, end: $end";
        if ($examId > 0) {
            $exam = Exam::query()->find($examId);
        } else {
            $exams = $this->listMatchExam($uid);
            if ($exams->count() > 1) {
                do_log(last_query());
                throw new NexusException("Match exam more than 1.");
            }
            $exam = $exams->first();
        }
        if (!$exam) {
            throw new NexusException("No valid exam.");
        }
        $user = User::query()->findOrFail($uid);
        $exists = $user->exams()->where('exam_id', $exam->id)->exists();
        if ($exists) {
            throw new NexusException("Exam: {$exam->id} already assign to user: {$user->id}");
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
        $result = $user->exams()->create($data);
        return $result;
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

    public function addProgress(int $examUserId, int $indexId, int $value, int $torrentId)
    {
        $logPrefix = "examUserId: $examUserId, indexId: $indexId, value: $value, torrentId: $torrentId";
        $examUser = ExamUser::query()->with(['exam', 'user'])->findOrFail($examUserId);
        if ($examUser->status != ExamUser::STATUS_NORMAL) {
            throw new \InvalidArgumentException("ExamUser: $examUserId is not normal.");
        }
        if (!isset(Exam::$indexes[$indexId])) {
            throw new \InvalidArgumentException("Invalid index id: $indexId.");
        }
        $exam = $examUser->exam;
        $indexes = collect($exam->indexes)->keyBy('index');
        if (!$indexes->has($indexId)) {
            throw new \InvalidArgumentException(sprintf('Exam: %s does not has index: %s', $exam->id, $indexId));
        }
        $index = $indexes->get($indexId);
        if (!isset($index['checked']) || !$index['checked']) {
            throw new \InvalidArgumentException(sprintf('Exam: %s index: %s is not checked', $exam->id, $indexId));
        }
        $torrentFields = ['id', 'visible', 'banned'];
        $torrent = Torrent::query()->findOrFail($torrentId, $torrentFields);
        $torrent->checkIsNormal($torrentFields);

        $user = $examUser->user;
        $user->checkIsNormal();

        $data = [
            'uid' => $user->id,
            'exam_id' => $exam->id,
            'torrent_id' => $torrentId,
            'index' => $indexId,
            'value' => $value,
        ];
        do_log("$logPrefix [addProgress] " . nexus_json_encode($data));
        $newProgress = $examUser->progresses()->create($data);
        $examProgress = $this->calculateProgress($examUser);
        $examProgressFormatted = $this->getProgressFormatted($exam, $examProgress);
        $examNotPassed = array_filter($examProgressFormatted, function ($item) {
            return !$item['passed'];
        });
        $update = [
            'progress' => $examProgress,
            'is_done' => count($examNotPassed) ? ExamUser::IS_DONE_NO : ExamUser::IS_DONE_YES,
        ];
        do_log("$logPrefix [updateProgress] " . nexus_json_encode($update));
        $examUser->update($update);
        return $newProgress;
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
            return null;
        }
        if ($examUsers->count() > 1) {
            do_log("$logPrefix, user exam more than 1.", 'warning');
        }
        $examUser = $examUsers->first();
        $exam = $examUser->exam;
        if (empty($examUser->begin) || empty($examUser->end)) {
            $examUser->begin = $exam->begin;
            $examUser->end = $exam->end;
        }
        $progress = $this->calculateProgress($examUser);
        do_log("$logPrefix, progress: " . nexus_json_encode($progress));
        $examUser->progress = $progress;
        $examUser->progress_formatted = $this->getProgressFormatted($exam, $progress);
        return $examUser;
    }

    private function calculateProgress(ExamUser $examUser)
    {
        $exam = $examUser->exam;
        $logPrefix = ", examUser: " . $examUser->id;
        if ($examUser->begin) {
            $logPrefix .= ", begin from examUser: " . $examUser->id;
            $begin = $examUser->begin;
        } elseif ($exam->begin) {
            $logPrefix .= ", begin from exam: " . $exam->id;
            $begin = $exam->begin;
        } else {
            do_log("$logPrefix, no begin");
            return null;
        }
        if ($examUser->end) {
            $logPrefix .= ", end from examUser: " . $examUser->id;
            $end = $examUser->end;
        } elseif ($exam->end) {
            $logPrefix .= ", end from exam: " . $exam->id;
            $end = $exam->end;
        } else {
            do_log("$logPrefix, no end");
            return null;
        }
        $progressSum = $examUser->progresses()
            ->where('created_at', '>=', $begin)
            ->where('created_at', '<=', $end)
            ->selectRaw("`index`, sum(`value`) as sum")
            ->groupBy(['index'])
            ->get();

        do_log("$logPrefix, " . last_query() . ", progressSum: " . $progressSum->toJson());

        return $progressSum->pluck('sum', 'index')->toArray();

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
                    $currentValueFormatted = mkprettytime($currentValue);
                    $requireValueAtomic = $requireValue * 3600;
                    break;
                default:
                    $currentValueFormatted = $currentValue;
                    $requireValueAtomic = $requireValue;
            }
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
            $examUser->progresses()->delete();
            return $examUser->delete();
        });
        return $result;
    }

    public function cronjonAssign()
    {
        $exams = $this->listValid();
        if ($exams->isEmpty()) {
            do_log("No valid exam.");
            return true;
        }
        if ($exams->count() > 1) {
            do_log("Valid exam more than 1.", "warning");
        }
        /** @var Exam $exam */
        $exam = $exams->first();
        $userTable = (new User())->getTable();
        $examUserTable = (new ExamUser())->getTable();
        User::query()
            ->leftJoin($examUserTable, function (JoinClause $join) use ($examUserTable, $userTable) {
                $join->on("$userTable.id", "=", "$examUserTable.uid")
                    ->on("$examUserTable.status", "=", DB::raw(ExamUser::STATUS_NORMAL));
            })
            ->whereRaw("$examUserTable.id is null")
            ->selectRaw("$userTable.*")
            ->chunk(100, function ($users) use ($exam) {
                do_log("user count: " . $users->count() . last_query());
                $insert = [];
                $now = Carbon::now()->toDateTimeString();
                foreach ($users as $user) {
                    $logPrefix = sprintf('[assignCronjob] user: %s, exam: %s', $user->id, $exam->id);
                    if (!$this->isExamMatchUser($exam, $user)) {
                        do_log("$logPrefix, exam not match user.");
                        continue;
                    }
                    $insert[] = [
                        'uid' => $user->id,
                        'exam_id' => $exam->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    do_log("$logPrefix, exam assign to user.");
                }
                ExamUser::query()->insert($insert);
            });
        return true;
    }

    public function cronjobCheckout()
    {
        $now = Carbon::now()->toDateTimeString();
        $examUserTable = (new ExamUser())->getTable();
        $examTable = (new Exam())->getTable();
        $perPage = 100;
        $page = 1;
        $query = ExamUser::query()
            ->join($examTable, "$examUserTable.exam_id", "=", "$examTable.id")
            ->where("$examUserTable.status", ExamUser::STATUS_NORMAL)
            ->whereRaw("if($examUserTable.begin is not null, $examUserTable.begin <= '$now', $examTable.begin <= '$now')")
            ->whereRaw("if($examUserTable.end is not null, $examUserTable.end >= '$now', $examTable.end >= '$now')")
            ->selectRaw("$examUserTable.*")
            ->with(['exam', 'user', 'user.language'])
            ->orderBy("$examUserTable.id", "asc");

        while (true) {
            $logPrefix = sprintf('[%s], page: %s', __FUNCTION__, $page);
            $examUsers = $query->forPage($page, $perPage)->get("$examUserTable.*");
            if ($examUsers->isEmpty()) {
                do_log("$logPrefix, no more data..." . last_query());
                break;
            } else {
                do_log("$logPrefix, fetch exam users: {$examUsers->count()}, " . last_query());
            }
            $now = Carbon::now()->toDateTimeString();
            $idArr = $uidToDisable = $messageToSend = [];
            foreach ($examUsers as $examUser) {
                $idArr[] = $examUser->id;
                $uid = $examUser->uid;
                $currentLogPrefix = sprintf("$logPrefix, user: %s, exam: %s, examUser: %s", $uid, $examUser->exam_id, $examUser->id);
                if ($examUser->is_done) {
                    do_log("$currentLogPrefix, [is_done]");
                    $messageToSend[] = [
                        'receiver' => $uid,
                        'added' => $now,
                        'subject' => 'Exam passed!',
                        'msg' => sprintf(
                            'Congratulations! You have complete the exam: %s in time(%s ~ %s)!',
                            $examUser->exam->name, $examUser->begin ?? $examUser->exam->begin, $examUser->end ?? $examUser->exam->end
                        ),
                    ];
                } else {
                    do_log("$currentLogPrefix, [will be banned]");
                    //ban user
                    $uidToDisable[] = $uid;
                    $messageToSend[] = [
                        'receiver' => $uid,
                        'added' => $now,
                        'subject' => 'Exam not passed! And your account is banned!',
                        'msg' => sprintf(
                            'You did not complete the exam: %s in time(%s ~ %s), so your account has been banned!',
                            $examUser->exam->name, $examUser->begin ?? $examUser->exam->begin, $examUser->end ?? $examUser->exam->end
                        ),
                    ];
                }
            }
            DB::transaction(function () use ($uidToDisable, $messageToSend, $idArr) {
                ExamUser::query()->whereIn('id', $idArr)->update(['status' => ExamUser::STATUS_FINISHED]);
                Message::query()->insert($messageToSend);
                if (!empty($uidToDisable)) {
                    User::query()->whereIn('id', $uidToDisable)->update(['enabled' => User::ENABLED_NO]);
                }
            });
            $page++;
        }
        return true;
    }




}

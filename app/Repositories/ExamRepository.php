<?php
namespace App\Repositories;

use App\Exceptions\NexusException;
use App\Models\Exam;
use App\Models\ExamProgress;
use App\Models\ExamUser;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\User;
use Carbon\Carbon;
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
        $exam = Exam::query()->create($params);
        return $exam;
    }

    public function update(array $params, $id)
    {
        $this->checkIndexes($params);
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
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function listValid()
    {
        $now = Carbon::now();
        return Exam::query()
            ->where('begin', '<=', $now)
            ->where('end', '>=', $now)
            ->where('status', Exam::STATUS_ENABLED)
            ->get();
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
        $user = User::query()->findOrFail($uid, ['id', 'username', 'added', 'class']);

        $filtered = $exams->filter(function (Exam $exam) use ($user, $logPrefix) {
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
        });

        return $filtered;
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
        do_log("$logPrefix [updateProgress] " . nexus_json_encode($examProgress));
        $examUser->update(['progress' => $examProgress]);
        return $newProgress;
    }

    public function getUserExamProgress($uid, $status = null, $with = ['exam', 'user'])
    {
        $logPrefix = "uid: $uid";
        $query = ExamUser::query()->where('uid', $uid)->orderBy('exam_id', 'desc');
        if ($status) {
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

        do_log("$logPrefix, query: " . last_query() . ", progressSum: " . $progressSum->toJson());

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
            $index['require_value_formatted'] = "$requireValue {$index['unit']}";
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




}

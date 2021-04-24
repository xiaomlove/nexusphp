<?php
namespace App\Repositories;

use App\Models\Exam;
use App\Models\ExamUser;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

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
            Log::info("$logPrefix, no valid exam.");
            return $exams;
        }
        $user = User::query()->findOrFail($uid, ['id', 'username', 'added', 'class']);

        $filtered = $exams->filter(function (Exam $exam) use ($user, $logPrefix) {
            $filters = $exam->filters;
            if (empty($filters->classes)) {
                Log::info("$logPrefix, exam: {$exam->id} no class");
                return false;
            }
            if (!in_array($user->class, $filters->classes)) {
                Log::info("$logPrefix, user class: {$user->class} not in: " . json_encode($filters));
                return false;
            }

            $added = $user->added->toDateTimeString();
            $registerTimeBegin = $filters->register_time_range[0] ? Carbon::parse($filters->register_time_range[0])->toDateString() : '';
            $registerTimeEnd = $filters->register_time_range[1] ? Carbon::parse($filters->register_time_range[1])->toDateString() : '';
            if (empty($registerTimeBegin)) {
                Log::info("$logPrefix, exam: {$exam->id} no register_time_begin");
                return false;
            }
            if ($added < $registerTimeBegin) {
                Log::info("$logPrefix, added: $added not after: " . $registerTimeBegin);
                return false;
            }

            if (empty($registerTimeEnd)) {
                Log::info("$logPrefix, exam: {$exam->id} no register_time_end");
                return false;
            }
            if ($added > $registerTimeEnd) {
                Log::info("$logPrefix, added: $added not before: " . $registerTimeEnd);
                return false;
            }

            return true;
        });

        return $filtered;
    }

    /**
     * assign exam to user
     *
     * @param $uid
     * @param int $examId
     * @return mixed
     */
    public function assignToUser($uid, $examId = 0)
    {
        if ($examId > 0) {
            $exam = Exam::query()->find($examId);
        } else {
            $exams = $this->listMatchExam($uid);
            if ($exams->count() > 1) {
                throw new \LogicException("Match exam more than 1.");
            }
            $exam = $exams->first();
        }
        if (!$exam) {
            throw new \LogicException("No valid exam.");
        }
        $user = User::query()->findOrFail($uid);
        $exists = $user->exams()->where('exam_id', $exam->id)->exists();
        if ($exists) {
            throw new \LogicException("exam: {$exam->id} already assign to user: {$user->id}");
        }
        $result = $user->exams()->save($exam);
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




}

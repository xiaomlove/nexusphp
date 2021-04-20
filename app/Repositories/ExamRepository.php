<?php
namespace App\Repositories;

use App\Models\Exam;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
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
        $exam = Exam::query()->create($params);
        return $exam;
    }

    public function update(array $params, $id)
    {
        $exam = Exam::query()->findOrFail($id);
        $exam->update($params);
        return $exam;
    }

    public function listIndexes()
    {
        $out = [];
        foreach(Exam::$indexes as $key => $value) {
            $out[$key] = $value['text'];
        }
        return $out;
    }

    public function listMatchExam($uid)
    {
        $now = Carbon::now();
        $user = User::query()->findOrFail($uid, ['id', 'username', 'added', 'class']);
        $exams = Exam::query()
            ->where('begin', '<=', $now)
            ->where('end', '>=', $now)
            ->where('status', Exam::STATUS_ENABLED)
            ->get();
        $result = [];
        $logPrefix = "uid: $uid";
        foreach ($exams as $exam) {
            $filters = $exam->filters;
            if (!in_array($user->class, $filters['classes'])) {
                Log::info("$logPrefix, class: {$user->class} not in: " . json_encode($filters));
                continue;
            }
            $added = $user->added->toDateTimeString();
            if (!empty($filters['register_time_begin']) && $added < $filters['register_time_begin']) {
                Log::info("$logPrefix, added: $added not after: " . $filters['register_time_begin']);
                continue;
            }
            if (!empty($filters['register_time_end']) && $added > $filters['register_time_end']) {
                Log::info("$logPrefix, added: $added not before: " . $filters['register_time_end']);
                continue;
            }
            $result[] = $exam;
        }
        return $result;
    }


}

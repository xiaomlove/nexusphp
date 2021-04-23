<?php
namespace App\Repositories;

use App\Models\Exam;
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
        $data = Arr::only($params, ['name', 'description', 'status', 'filters']);
        if (!empty($params['begin'])) {

        }
        $exam = Exam::query()->create($params);
        return $exam;
    }

    public function update(array $params, $id)
    {
        $exam = Exam::query()->findOrFail($id);
        $exam->update($params);
        return $exam;
    }

    public function getDetail($id)
    {
        $exam = Exam::query()->findOrFail($id);
        return $exam;
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

    public function listFilters()
    {
        $out = [];
        foreach(Exam::$filters as $key => $value) {
            $value['filter'] = $key;
            $out[] = $value;
        }
        return $out;
    }

    /**
     * list user match exams
     *
     * @param $uid
     * @return array
     */
    public function listMatchExam($uid): array
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

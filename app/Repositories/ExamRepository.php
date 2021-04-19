<?php
namespace App\Repositories;

use App\Models\Exam;
use App\Models\Setting;
use App\Models\User;

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

}

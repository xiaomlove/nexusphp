<?php
namespace App\Repositories;

use App\Models\News;

class NewsRepository extends BaseRepository
{
    public function getList(array $params)
    {
        $query = News::query()->with(['user']);
        if (!empty($params['userid'])) {
            $query->where('userid', $params['userid']);
        }
        list($sortField, $sortType) = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);
        return $query->paginate();
    }

    public function store(array $params)
    {
        $model = News::query()->create($params);
        return $model;
    }

    public function update(array $params, $id)
    {
        $model = News::query()->findOrFail($id);
        $model->update($params);
        return $model;
    }

    public function getDetail($id)
    {
        $model = News::query()->findOrFail($id);
        return $model;
    }

    public function delete($id)
    {
        $model = News::query()->findOrFail($id);
        $result = $model->delete();
        return $result;
    }
}

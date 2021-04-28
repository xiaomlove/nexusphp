<?php
namespace App\Repositories;

use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MessageRepository extends BaseRepository
{
    public function getList(array $params)
    {
        $query = Message::query();
        list($sortField, $sortType) = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);
        return $query->paginate();
    }

    public function store(array $params)
    {
        $model = Message::query()->create($params);
        return $model;
    }

    public function update(array $params, $id)
    {
        $model = Message::query()->findOrFail($id);
        $model->update($params);
        return $model;
    }

    public function getDetail($id)
    {
        $model = Message::query()->findOrFail($id);
        return $model;
    }

    public function delete($id)
    {
        $model = Message::query()->findOrFail($id);
        $result = $model->delete();
        return $result;
    }
}

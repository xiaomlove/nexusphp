<?php
namespace App\Repositories;

use App\Models\AgentDeny;

class AgentDenyRepository extends BaseRepository
{
    public function getList(array $params)
    {
        $query = AgentDeny::query()->with(['family']);
        if (!empty($params['family_id'])) {
            $query->where('family_id', $params['family_id']);
        }
        $query->orderBy('family_id', 'desc');
        return $query->paginate();
    }

    public function store(array $params)
    {
        $model = AgentDeny::query()->create($params);
        return $model;
    }

    public function update(array $params, $id)
    {
        $model = AgentDeny::query()->findOrFail($id);
        $model->update($params);
        return $model;
    }

    public function getDetail($id)
    {
        $model = AgentDeny::query()->findOrFail($id);
        return $model;
    }

    public function delete($id)
    {
        $model = AgentDeny::query()->findOrFail($id);
        $result = $model->delete();
        return $result;
    }
}

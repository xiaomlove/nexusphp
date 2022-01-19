<?php
namespace App\Repositories;

use App\Models\Medal;
use App\Models\UserMedal;
use Nexus\Database\NexusDB;

class MedalRepository extends BaseRepository
{
    public function getList(array $params): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Medal::query();
        list($sortField, $sortType) = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);
        return $query->paginate();
    }

    public function store(array $params)
    {
        return Medal::query()->create($params);
    }

    public function update(array $params, $id)
    {
        $medal = Medal::query()->findOrFail($id);
        $medal->update($params);
        return $medal;
    }


    public function getDetail($id)
    {
        return Medal::query()->findOrFail($id);
    }

    /**
     * delete a medal, also will delete all user medal.
     *
     * @param $id
     * @return bool
     */
    public function delete($id): bool
    {
        $medal = Medal::query()->findOrFail($id);
        NexusDB::transaction(function () use ($medal) {
            do {
                $deleted = UserMedal::query()->where('medal_id', $medal->id)->limit(10000)->delete();
            } while ($deleted > 0);
            $medal->delete();
        });
        return true;
    }

}

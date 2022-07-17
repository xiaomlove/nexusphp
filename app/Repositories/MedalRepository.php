<?php
namespace App\Repositories;

use App\Models\Medal;
use App\Models\User;
use App\Models\UserMedal;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
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

    public function  grantToUser(int $uid, int $medalId, $duration = null)
    {
        $user = User::query()->findOrFail($uid, User::$commonFields);
        if (Auth::user()->class <= $user->class) {
            throw new \LogicException("No permission!");
        }
        $medal = Medal::query()->findOrFail($medalId);
        $exists = $user->valid_medals()->where('medal_id', $medalId)->exists();
        do_log(last_query());
        if ($exists) {
            throw new \LogicException("user: $uid already own this medal: $medalId.");
        }
        $expireAt = null;
        if ($duration > 0) {
            $expireAt = Carbon::now()->addDays($duration)->toDateTimeString();
        }
        return $user->medals()->attach([$medal->id => ['expire_at' => $expireAt, 'status' => UserMedal::STATUS_NOT_WEARING]]);
    }

    function toggleUserMedalStatus($id, $userId)
    {
        $userMedal = UserMedal::query()->findOrFail($id);
        if ($userMedal->uid != $userId) {
            throw new \LogicException("no privilege");
        }
        $current = $userMedal->status;
        if ($current == UserMedal::STATUS_NOT_WEARING) {
            $userMedal->status = UserMedal::STATUS_WEARING;
        } elseif ($current == UserMedal::STATUS_WEARING) {
            $userMedal->status = UserMedal::STATUS_NOT_WEARING;
        }
        $userMedal->save();
        return $userMedal;
    }

}

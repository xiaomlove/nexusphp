<?php
namespace App\Repositories;

use App\Models\Poll;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PollRepository extends BaseRepository
{
    public function getList(array $params)
    {
        $query = Poll::query();
        list($sortField, $sortType) = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);
        return $query->paginate();
    }

    public function store($torrentId, $value, User $user)
    {
        if ($user->seedbonus < $value) {
            throw new \LogicException("user bonus not enough.");
        }
        if ($user->reward_torrent_logs()->where('torrentid', $torrentId)->exists()) {
            throw new \LogicException("user already reward this torrent.");
        }
        $torrent = Torrent::query()->findOrFail($torrentId, ['owner']);
        $torrentOwner = User::query()->findOrFail($torrent->owner, ['id', 'seedbonus']);
        return DB::transaction(function () use ($torrentId, $value, $user, $torrentOwner) {
            $model = $user->reward_torrent_logs()->create([
                'torrentid' => $torrentId,
                'value' => $value,
            ]);
            $affectedRows = $user->where('seedbonus', $user->seedbonus)->decrement('seedbonus', $value);
            if ($affectedRows != 1) {
                do_log("affectedRows: $affectedRows, query: " . last_query(), 'error');
                throw new \RuntimeException("decrement user bonus fail.");
            }
            $affectedRows = $torrentOwner->where('seedbonus', $torrentOwner->seedbonus)->increment('seedbonus', $value);
            if ($affectedRows != 1) {
                do_log("affectedRows: $affectedRows, query: " . last_query(), 'error');
                throw new \RuntimeException("increment owner bonus fail.");
            }
            return $model;
        });
    }

    public function update(array $params, $id)
    {
        $model = Poll::query()->findOrFail($id);
        $model->update($params);
        return $model;
    }

    public function getDetail($id)
    {
        $model = Poll::query()->findOrFail($id);
        return $model;
    }

    public function delete($id)
    {
        $model = Poll::query()->findOrFail($id);
        $result = $model->delete();
        return $result;
    }

    public function vote($selection, User $user)
    {

    }
}

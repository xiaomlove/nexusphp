<?php
namespace App\Repositories;

use App\Models\Reward;
use App\Models\Torrent;
use App\Models\User;
use Google\Service\ToolResults\StepLabelsEntry;
use Illuminate\Support\Facades\DB;

class RewardRepository extends BaseRepository
{
    public function getList(array $params)
    {
        $query = Reward::query()->with(['user']);
        if (!empty($params['torrent_id'])) {
            $query->where('torrentid', $params['torrent_id']);
        }
        list($sortField, $sortType) = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);
        return $query->paginate();
    }

    public function store($torrentId, $value, User $user)
    {
        if ($user->seedbonus < $value) {
            throw new \LogicException("your bonus not enough.");
        }
        if ($user->reward_torrent_logs()->where('torrentid', $torrentId)->exists()) {
            throw new \LogicException("you already reward this torrent.");
        }
        $torrent = Torrent::query()->findOrFail($torrentId, Torrent::$commentFields);
        $torrent->checkIsNormal();
        $torrentOwner = User::query()->findOrFail($torrent->owner);
        if ($user->id == $torrentOwner->id) {
            throw new \LogicException("you can't reward to yourself.");
        }
        $torrentOwner->checkIsNormal();
        return DB::transaction(function () use ($torrentId, $value, $user, $torrentOwner) {
            $model = $user->reward_torrent_logs()->create([
                'torrentid' => $torrentId,
                'value' => $value,
            ]);
            $affectedRows = User::query()
                ->where('id', $user->id)
                ->where('seedbonus', $user->seedbonus)
                ->decrement('seedbonus', $value);
            if ($affectedRows != 1) {
                do_log("affectedRows: $affectedRows, query: " . last_query(), 'error');
                throw new \RuntimeException("decrement user bonus fail.");
            }
            $affectedRows = User::query()
                ->where('id', $torrentOwner->id)
                ->where('seedbonus', $torrentOwner->seedbonus)
                ->increment('seedbonus', $value);
            if ($affectedRows != 1) {
                do_log("affectedRows: $affectedRows, query: " . last_query(), 'error');
                throw new \RuntimeException("increment owner bonus fail.");
            }
            return $model;
        });
    }

    public function update(array $params, $id)
    {
        $model = Reward::query()->findOrFail($id);
        $model->update($params);
        return $model;
    }

    public function getDetail($id)
    {
        $model = Reward::query()->findOrFail($id);
        return $model;
    }

    public function delete($id)
    {
        $model = Reward::query()->findOrFail($id);
        $result = $model->delete();
        return $result;
    }
}

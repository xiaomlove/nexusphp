<?php
namespace App\Repositories;

use App\Models\Poll;
use App\Models\SeedBoxRecord;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use PhpIP\IP;
use PhpIP\IPBlock;

class SeedBoxRepository extends BaseRepository
{
    public function getList(array $params)
    {
        $query = Poll::query();
        list($sortField, $sortType) = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);
        return $query->paginate();
    }

    public function store(array $params)
    {
        if (!empty($params['ip']) && empty($params['ip_begin']) && empty($params['ip_end'])) {
            if (str_contains($params['ip'], '/')) {
                $ipBlock = IPBlock::create($params['ip']);
                $params['ip_begin_numeric'] = $ipBlock->getFirstIp()->numeric();
                $params['ip_end_numeric'] = $ipBlock->getLastIp()->numeric();
            } else {
                $ip = IP::create($params['ip']);
                $params['ip_begin_numeric'] = $ip->numeric();
                $params['ip_end_numeric'] = $ip->numeric();
            }
        } elseif (empty($params['ip']) && !empty($params['ip_begin']) && !empty($params['ip_end'])) {
            $ipBegin = IP::create($params['ip_begin']);
            $params['ip_begin_numeric'] = $ipBegin->numeric();

            $ipEnd = IP::create($params['ip_end']);
            $params['ip_end_numeric'] = $ipEnd->numeric();
        } else {
            throw new \InvalidArgumentException("Require ip or ip_begin + ip_end");
        }

        return SeedBoxRecord::query()->create($params);
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

    public function delete($id, $uid)
    {
        return SeedBoxRecord::query()->whereIn('id', Arr::wrap($id))->where('uid', $uid)->delete();
    }

}

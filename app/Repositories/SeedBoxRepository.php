<?php
namespace App\Repositories;

use App\Events\SeedBoxRecordUpdated;
use App\Exceptions\InsufficientPermissionException;
use App\Models\Message;
use App\Models\Poll;
use App\Models\SeedBoxRecord;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;
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

    /**
     * @param array $params
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     */
    public function store(array $params)
    {
        $params = $this->formatParams($params);
        $seedBoxRecord = SeedBoxRecord::query()->create($params);
        $this->clearCache();
        return $seedBoxRecord;
    }

    private function formatParams(array $params): array
    {
        if (!empty($params['ip']) && empty($params['ip_begin']) && empty($params['ip_end'])) {
            try {
                $ipBlock = IPBlock::create($params['ip']);
                $params['ip_begin_numeric'] = $ipBlock->getFirstIp()->numeric();
                $params['ip_end_numeric'] = $ipBlock->getLastIp()->numeric();
                $params['version'] = $ipBlock->getVersion();
            } catch (\Exception $exception) {
                do_log("[NOT_IP_BLOCK], {$params['ip']}" . $exception->getMessage());
            }
            if (empty($params['version'])) {
                try {
                    $ip = IP::create($params['ip']);
                    $params['ip_begin_numeric'] = $ip->numeric();
                    $params['ip_end_numeric'] = $ip->numeric();
                    $params['version'] = $ip->getVersion();
                } catch (\Exception $exception) {
                    do_log("[NOT_IP], {$params['ip']}" . $exception->getMessage());
                }
            }
            if (empty($params['version'])) {
                throw new \InvalidArgumentException("Invalid IPBlock or IP: " . $params['ip']);
            }

        } elseif (empty($params['ip']) && !empty($params['ip_begin']) && !empty($params['ip_end'])) {
            $ipBegin = IP::create($params['ip_begin']);
            $params['ip_begin_numeric'] = $ipBegin->numeric();

            $ipEnd = IP::create($params['ip_end']);
            $params['ip_end_numeric'] = $ipEnd->numeric();
            if ($ipBegin->getVersion() != $ipEnd->getVersion()) {
                throw new \InvalidArgumentException("ip_begin/ip_end must be the same version");
            }
            $params['version'] = $ipEnd->getVersion();
        } else {
            throw new \InvalidArgumentException("Require ip or ip_begin + ip_end");
        }

        return $params;
    }

    public function update(array $params, $id)
    {
        $model = SeedBoxRecord::query()->findOrFail($id);
        $params = $this->formatParams($params);
        $model->update($params);
        $this->clearCache();
        return $model;
    }

    public function getDetail($id)
    {
        $model = Poll::query()->findOrFail($id);
        return $model;
    }

    public function delete($id, $uid)
    {
        $this->clearCache();
        return SeedBoxRecord::query()->whereIn('id', Arr::wrap($id))->where('uid', $uid)->delete();
    }

    public function updateStatus(SeedBoxRecord $seedBoxRecord, $status): bool
    {
        if (Auth::user()->class < User::CLASS_ADMINISTRATOR) {
            throw new InsufficientPermissionException();
        }
        if (!isset(SeedBoxRecord::$status[$status])) {
            throw new \InvalidArgumentException("Invalid status: $status");
        }
        if ($seedBoxRecord->status == $status) {
            return true;
        }
        $message = [
            'receiver' => $seedBoxRecord->uid,
            'subject' => nexus_trans('seed-box.status_change_message.subject'),
            'msg' => nexus_trans('seed-box.status_change_message.body', [
                'id' => $seedBoxRecord->id,
                'operator' => Auth::user()->username,
                'old_status' => $seedBoxRecord->statusText,
                'new_status' => nexus_trans('seed-box.status_text.' . $status),
            ]),
            'added' => now()
        ];
        return NexusDB::transaction(function () use ($seedBoxRecord, $status, $message) {
            $seedBoxRecord->status = $status;
            $seedBoxRecord->save();
            $this->clearCache();
            return Message::add($message);
        });
    }

    public function renderIcon($ip, $uid): string
    {
        $result = '';
        if ((isIPV4($ip) || isIPV6($ip)) && get_setting('seed_box.enabled') == 'yes' && isIPSeedBox($ip, $uid)) {
            $result = '<img src="pic/misc/seed-box.png" style="vertical-align: bottom; height: 16px; margin-left: 4px" title="SeedBox" />';
        }
        return $result;
    }

    private function clearCache()
    {
        return true;
//        SeedBoxRecordUpdated::dispatch();
    }



}

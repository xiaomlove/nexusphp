<?php
namespace App\Repositories;

use App\Models\Message;
use App\Models\Setting;
use App\Models\StaffMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;

class MessageRepository extends BaseRepository
{
    const STAFF_MESSAGE_TOTAL_CACHE_KEY = 'staff_message_count';

    const STAFF_MESSAGE_NEW_CACHE_KEY = 'staff_new_message_count';

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

    public static function countStaffMessage($uid, $answered = null): int
    {
        return self::buildStaffMessageQuery($uid, $answered)->count();
    }

    public static function buildStaffMessageQuery($uid, $answered = null): \Illuminate\Database\Eloquent\Builder
    {
        $query = StaffMessage::query();
        if ($answered !== null) {
            $query->where('answered', $answered);
        }
        if (!user_can('staffmem', false, $uid)) {
            //Not staff member only can see authorized
            $permissions = ToolRepository::listUserAllPermissions($uid);
            $query->whereIn('permission', $permissions);
        }
        return $query;
    }

    public static function updateStaffMessageCountCache($uid = 0, $type = '', $value = '')
    {
        if ($uid === false) {
            NexusDB::cache_del(self::STAFF_MESSAGE_NEW_CACHE_KEY);
            NexusDB::cache_del(self::STAFF_MESSAGE_TOTAL_CACHE_KEY);
        } else {
            $redis = NexusDB::redis();
            match ($type) {
                'total' => $redis->hSet(self::STAFF_MESSAGE_TOTAL_CACHE_KEY, $uid, $value),
                'new' => $redis->hSet(self::STAFF_MESSAGE_NEW_CACHE_KEY, $uid, $value),
                default => throw new \InvalidArgumentException("Invalid type: $type")
            };
        }
    }

    public static function getStaffMessageCountCache($uid = 0, $type = '')
    {
        $redis = NexusDB::redis();
        return match ($type) {
            'total' => $redis->hGet(self::STAFF_MESSAGE_TOTAL_CACHE_KEY, $uid),
            'new' => $redis->hGet(self::STAFF_MESSAGE_NEW_CACHE_KEY, $uid),
            default => throw new \InvalidArgumentException("Invalid type: $type")
        };
    }
}

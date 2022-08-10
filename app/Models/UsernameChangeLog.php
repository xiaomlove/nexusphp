<?php

namespace App\Models;

class UsernameChangeLog extends NexusModel
{
    protected $fillable = ['uid', 'username_old', 'username_new', 'operator', 'change_type'];

    public $timestamps = true;

    const CHANGE_TYPE_USER = 1;
    const CHANGE_TYPE_ADMIN = 2;

    public static array $changeTypes = [
        self::CHANGE_TYPE_USER => ['text' => 'User'],
        self::CHANGE_TYPE_ADMIN => ['text' => 'Administrator'],
    ];

    public function getChangeTypeTextAttribute()
    {
        return nexus_trans('username-change-log.change_type.' . $this->change_type);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'uid');
    }

    public static function listChangeType()
    {
        $result = [];
        foreach (self::$changeTypes as $type => $info) {
            $result[$type] = nexus_trans('username-change-log.change_type.' . $type);
        }
        return $result;
    }

}

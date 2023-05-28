<?php

namespace App\Models;

class UserMeta extends NexusModel
{
    protected $fillable = ['uid', 'meta_key', 'meta_value', 'status', 'deadline'];

    public $timestamps = true;

    const STATUS_NORMAL = 0;

    const META_KEY_PERSONALIZED_USERNAME = 'PERSONALIZED_USERNAME';

    const META_KEY_CHANGE_USERNAME = 'CHANGE_USERNAME';

    protected $appends = ['meta_key_text'];

    protected $casts = [
        'deadline' => 'datetime',
    ];

    public static array $metaKeys = [
        self::META_KEY_PERSONALIZED_USERNAME => ['text' => 'PERSONALIZED_USERNAME', 'multiple' => false],
        self::META_KEY_CHANGE_USERNAME => ['text' => 'CHANGE_USERNAME', 'multiple' => false],
    ];

    public static function listProps()
    {
        return [
            self::META_KEY_PERSONALIZED_USERNAME => nexus_trans('label.user_meta.meta_keys.' . self::META_KEY_PERSONALIZED_USERNAME),
            self::META_KEY_CHANGE_USERNAME => nexus_trans('label.user_meta.meta_keys.' . self::META_KEY_CHANGE_USERNAME),
        ];
    }

    public function getMetaKeyTextAttribute()
    {
        return nexus_trans('label.user_meta.meta_keys.' . $this->meta_key) ?? '';
    }

    public function isValid(): bool
    {
        return $this->status == self::STATUS_NORMAL && ($this->getRawOriginal('deadline') === null || ($this->deadline && $this->deadline->gte(now())));
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'uid');
    }

}

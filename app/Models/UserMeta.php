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

    public function getMetaKeyTextAttribute()
    {
        return nexus_trans('label.user_meta.meta_keys.' . $this->meta_key) ?? '';
    }

    public static function consumeBenefit($uid, $metaKey)
    {

    }

}

<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Builder;

class Comment extends NexusModel
{
    protected $casts = [
        'added' => 'datetime',
        'editdate' => 'datetime',
    ];

    protected $fillable = ['user', 'torrent', 'added', 'text', 'ori_text', 'editedby', 'editdate', 'offer', 'request', 'anonymous'];

    const TYPE_TORRENT = 'torrent';
    const TYPE_REQUEST = 'request';
    const TYPE_OFFER = 'offer';

    const TYPE_MAPS = [
        self::TYPE_TORRENT => [
            'model' => Torrent::class,
            'foreign_key' => 'torrent',
            'target_name_field' => 'name',
            'target_script' => 'details.php?id=%s'
        ],
        self::TYPE_REQUEST => [
            'model' => Request::class,
            'foreign_key' => 'request',
            'target_name_field' => 'request',
            'target_script' => 'viewrequests.php?id=%s&req_details=1'
        ],
        self::TYPE_OFFER => [
            'model' => Offer::class,
            'foreign_key' => 'offer',
            'target_name_field' => 'name',
            'target_script' => 'offers.php?id=%s&off_details=1'
        ],
    ];

    public function scopeType(Builder $query, string $type, int $typeValue)
    {
        foreach (self::TYPE_MAPS as $key => $value) {
            if ($type != $key) {
                $query->where($value['foreign_key'], 0);
            } else {
                $query->where($value['foreign_key'], $typeValue);
            }
        }
        return $query;
    }

    public function related_torrent()
    {
        return $this->belongsTo(Torrent::class, 'torrent');
    }

    public function create_user()
    {
        return $this->belongsTo(User::class, 'user')->withDefault(User::getDefaultUserAttributes());
    }

    public function update_user()
    {
        return $this->belongsTo(User::class, 'editedby')->withDefault(User::getDefaultUserAttributes());
    }
}

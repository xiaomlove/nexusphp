<?php

namespace App\Models;

use Carbon\Carbon;

class Medal extends NexusModel
{
    const GET_TYPE_EXCHANGE = 1;

    const GET_TYPE_GRANT = 2;

    public static array $getTypeText = [
        self::GET_TYPE_EXCHANGE => ['text' => 'Exchange'],
        self::GET_TYPE_GRANT => ['text' => 'Grant'],
    ];

    protected $fillable = [
        'name', 'description', 'image_large', 'image_small', 'price', 'duration', 'get_type',
        'display_on_medal_page', 'sale_begin_time', 'sale_end_time', 'inventory', 'bonus_addition_factor',
        'gift_fee_factor',
    ];

    public $timestamps = true;

    protected $casts = [
        'sale_begin_time' => 'datetime',
        'sale_end_time' => 'datetime',
    ];

    public static function listGetTypes($onlyKeyValues = false): array
    {
        $results = self::$getTypeText;
        $keyValues = [];
        foreach ($results as $type => &$info) {
            $text = nexus_trans("medal.get_types.$type");
            $keyValues[$type] = $text;
            $info['text'] = $text;
        }
        if ($onlyKeyValues) {
            return $keyValues;
        }
        return $results;
    }

    public function getGetTypeTextAttribute($value): string
    {
        return nexus_trans("medal.get_types." . $this->get_type);
    }

    public function getDurationTextAttribute($value): string
    {
        if ($this->duration > 0) {
            return $this->duration;
        }
        return nexus_trans("label.permanent");
    }

    public function checkCanBeBuy()
    {
        if ($this->get_type == self::GET_TYPE_GRANT) {
            throw new \RuntimeException(nexus_trans('medal.grant_only'));
        }
        $now = now();
        if ($this->sale_begin_time && $this->sale_begin_time->gt($now)) {
            throw new \RuntimeException(nexus_trans('medal.before_sale_begin_time'));
        }
        if ($this->sale_end_time && $this->sale_end_time->lt($now)) {
            throw new \RuntimeException(nexus_trans('medal.after_sale_end_time'));
        }
        if ($this->inventory !== null && $this->inventory <= 0) {
            throw new \RuntimeException(nexus_trans('medal.inventory_empty'));
        }
    }

    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_medals', 'medal_id', 'uid')->withTimestamps();
    }

    public function valid_users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->users()->where(function ($query) {
            $query->whereNull('user_medals.expire_at')->orWhere('user_medals.expire_at', '>=', Carbon::now());
        });
    }

}

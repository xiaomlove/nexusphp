<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;

class SeedBoxRecord extends NexusModel
{
    protected $fillable = ['type', 'uid', 'status', 'operator', 'bandwidth', 'ip', 'ip_begin', 'ip_end', 'ip_begin_numeric', 'ip_end_numeric', 'comment', 'version'];

    public $timestamps = true;

    const TYPE_USER = 1;
    const TYPE_ADMIN = 2;

    public static array $types = [
        self::TYPE_USER => ['text' => 'User'],
        self::TYPE_ADMIN => ['text' => 'Administrator'],
    ];

    const STATUS_UNAUDITED = 0;
    const STATUS_ALLOWED = 1;
    const STATUS_DENIED = 2;

    public static array $status = [
        self::STATUS_UNAUDITED => ['text' => 'Unaudited'],
        self::STATUS_ALLOWED => ['text' => 'Allowed'],
        self::STATUS_DENIED => ['text' => 'Denied'],
    ];

    protected function typeText(): Attribute
    {
        return new Attribute(
            get: fn($value, $attributes) => nexus_trans("seed-box.type_text." . $attributes['type'])
        );
    }

    protected function statusText(): Attribute
    {
        return new Attribute(
            get: fn($value, $attributes) => nexus_trans("seed-box.status_text." . $attributes['status'])
        );
    }

    public static function listTypes($key = null): array
    {
        $result = self::$types;
        $keyValues = [];
        foreach ($result as $type => &$info) {
            $info['text'] = nexus_trans("seed-box.type_text.$type");
            if ($key !== null) {
                $keyValues[$type] = $info[$key];
            }
        }
        return $key === null ? $result : $keyValues;
    }

    public static function listStatus($key = null): array
    {
        $result = self::$status;
        $keyValues = [];
        foreach ($result as $status => &$info) {
            $info['text'] = nexus_trans("seed-box.status_text.$status");
            if ($key !== null) {
                $keyValues[$status] = $info[$key];
            }
        }
        return $key === null ? $result : $keyValues;
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'uid');
    }


}

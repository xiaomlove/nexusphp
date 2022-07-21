<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;

class SeedBoxRecord extends NexusModel
{
    protected $table = 'seedbox_records';

    protected $fillable = ['type', 'uid', 'operator', 'bandwidth', 'ip', 'ip_begin', 'ip_end', 'ip_begin_numeric', 'ip_end_numeric', 'comment'];

    public $timestamps = true;

    const TYPE_USER = 1;
    const TYPE_ADMIN = 2;

    protected function typeText(): Attribute
    {
        return new Attribute(
            get: fn($value, $attributes) => __("seedbox.type_text." . $attributes['type'])
        );
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'uid');
    }


}

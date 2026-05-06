<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends NexusModel
{
    protected $table = 'push_subscriptions';

    protected $fillable = [
        'userid',
        'endpoint',
        'endpoint_hash',
        'p256dh',
        'auth',
        'content_encoding',
        'user_agent',
        'last_seen_at',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }

    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }
}

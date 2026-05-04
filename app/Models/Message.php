<?php

namespace App\Models;

use App\Enums\ModelEventEnum;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends NexusModel
{
    protected $table = 'messages';

    protected $fillable = [
        'sender', 'receiver', 'added', 'subject', 'msg', 'unread', 'location', 'saved',
    ];

    protected $casts = [
        'added' => 'datetime',
    ];

    public function send_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender')->withDefault(['id' => 0, 'username' => 'System']);
    }

    public function receive_user()
    {
        return $this->belongsTo(User::class, 'receiver');
    }

    public static function add(array $data): self
    {
        clear_inbox_count_cache($data['receiver']);
        $message = self::query()->create($data);
        fire_event(ModelEventEnum::MESSAGE_CREATED, $message);

        return $message;
    }
}

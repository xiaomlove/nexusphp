<?php

namespace App\Models;

use App\Enums\ModelEventEnum;
use App\Events\NotificationReceived;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nexus\Database\NexusDB;

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

        // Broadcast a per-user notification (Reverb). Best-effort — failures
        // here must never break the actual PM send. Same pattern as
        // ShoutSent in public/shoutbox.php.
        try {
            $receiverId = (int) $data['receiver'];
            if ($receiverId > 0) {
                $unreadCount = (int) NexusDB::table('messages')
                    ->where('receiver', $receiverId)
                    ->where('unread', 'yes')
                    ->count();
                event(new NotificationReceived(
                    user_id: $receiverId,
                    message_id: (int) $message->id,
                    sender_id: (int) ($data['sender'] ?? 0),
                    subject: (string) ($data['subject'] ?? ''),
                    unread_count: $unreadCount,
                    html: '',
                ));
            }
        } catch (\Throwable $e) {
            do_log('NotificationReceived broadcast failed: '.$e->getMessage(), 'error');
        }

        return $message;
    }
}

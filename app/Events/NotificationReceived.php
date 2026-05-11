<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired (and broadcast over Reverb) every time a new private message lands
 * for a specific user. Subscribers (Echo on the frontend) use this to
 * update the inbox badge / show a toast without polling.
 */
class NotificationReceived implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  string  $html  Optional pre-rendered HTML snippet for a toast
     *                        / badge update. If empty the listener falls back
     *                        to a plain badge increment.
     */
    public function __construct(
        public int $user_id,
        public int $message_id,
        public int $sender_id,
        public string $subject,
        public int $unread_count,
        public string $html = '',
    ) {}

    /**
     * Private channel: notifications.user.{id}. The auth callback in
     * routes/channels.php enforces that the subscriber's id matches.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("notifications.user.{$this->user_id}")];
    }

    /**
     * Stable event name so the JS listener doesn't depend on the FQCN.
     */
    public function broadcastAs(): string
    {
        return 'NotificationReceived';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user_id,
            'message_id' => $this->message_id,
            'sender_id' => $this->sender_id,
            'subject' => $this->subject,
            'unread_count' => $this->unread_count,
            'html' => $this->html,
        ];
    }
}

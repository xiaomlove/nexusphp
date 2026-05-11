<?php

namespace App\Listeners;

use App\Events\MessageCreated;
use App\Models\Message;
use App\Models\User;
use App\Services\PushDispatcher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;

/**
 * On Message::add(), push a notification to the recipient (if they
 * have any push subscriptions registered and the recipient opts in).
 *
 * Queued so a slow VAPID provider never delays the message-write
 * code path.
 */
class SendPushOnMessage implements ShouldQueue
{
    public string $queue = 'default';

    public function __construct(private readonly PushDispatcher $push) {}

    public function handle(MessageCreated $event): void
    {
        $message = $event->model;
        if (! $message instanceof Message) {
            return;
        }
        $receiverId = (int) ($message->receiver ?? 0);
        if ($receiverId <= 0) {
            return;
        }

        $receiver = User::query()->find($receiverId);
        if (! $receiver || ! $receiver->acceptNotification('msg_received')) {
            return;
        }

        $subject = (string) ($message->subject ?? 'New message');
        $preview = strip_tags((string) ($message->msg ?? ''));
        $preview = (string) Str::limit($preview, 140);

        $this->push->sendToUser(
            $receiverId,
            Str::limit($subject, 80),
            $preview !== '' ? $preview : 'You have a new message',
            '/messages.php',
            ['kind' => 'message', 'id' => (int) ($message->id ?? 0)],
        );
    }
}

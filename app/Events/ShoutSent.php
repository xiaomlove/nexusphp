<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired (and broadcast over Reverb) every time a new shoutbox/helpbox
 * message lands in the database. Subscribers (Echo on the frontend)
 * use this to refresh the UI without polling.
 */
class ShoutSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  string  $html  Pre-rendered <tr>…</tr> for the new shout row,
     *                        identical byte-for-byte to what the next page
     *                        load would render. Letting subscribers prepend
     *                        this directly avoids a full iframe reload on
     *                        every shout.
     */
    public function __construct(
        public int $id,
        public int $userid,
        public int $date,
        public string $text,
        public string $type = 'sb',
        public string $html = '',
    ) {}

    /**
     * Channel: shoutbox.sb (regular shoutbox) or shoutbox.hb (helpbox).
     * Public channel — anyone can subscribe; the existing /shoutbox.php
     * page already restricts visibility server-side.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel("shoutbox.{$this->type}")];
    }

    /**
     * Use a stable event name so the frontend listener doesn't depend on
     * the fully qualified class path.
     */
    public function broadcastAs(): string
    {
        return 'ShoutSent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->id,
            'userid' => $this->userid,
            'date' => $this->date,
            'text' => $this->text,
            'type' => $this->type,
            'html' => $this->html,
        ];
    }
}

<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TorrentUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ?Model $model = null;

    public ?Model $modelOld = null;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct(Model $model, Model $modelOld)
    {
        $this->model = $model;
        $this->modelOld = $modelOld;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}

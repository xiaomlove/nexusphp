<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired (and broadcast over Reverb) when a torrent's promotion state
 * changes — either via admin per-torrent setSpState() or via the
 * global freeleech action that updates torrents_state.global_sp_state.
 *
 * Subscribers (the Livewire TorrentBrowse component) listen on the
 * public `torrents.promotion` channel and re-render the affected card
 * (or the whole grid, in the global case) so badges update without a
 * page reload.
 */
class TorrentPromotionChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  int  $torrentId  Per-torrent change: the torrent id.
     *                          Global change: 0 (the global_sp_state update affects every torrent).
     * @param  int  $spState  The new sp_state value (per-torrent) or the new global_sp_state value (global).
     * @param  bool  $global  True when this is a global freeleech change.
     */
    public function __construct(
        public int $torrentId,
        public int $spState,
        public bool $global = false,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('torrents.promotion')];
    }

    public function broadcastAs(): string
    {
        return 'TorrentPromotionChanged';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'torrentId' => $this->torrentId,
            'spState' => $this->spState,
            'global' => $this->global,
        ];
    }
}

<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired (and broadcast over Reverb) every time the seeders / leechers /
 * comments counts for a torrent are recomputed by UpdateTorrentSeedersEtc.
 * Subscribers on details.php use this to refresh the peer count cell in
 * place without a full reload.
 */
class TorrentPeersUpdated implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  string  $html  Optional pre-rendered HTML for the peer count
     *                        cell. If empty the listener falls back to a
     *                        textual update.
     */
    public function __construct(
        public int $torrent_id,
        public int $seeders,
        public int $leechers,
        public int $comments,
        public string $html = '',
    ) {}

    /**
     * Public channel: torrent.{id}.peers — peer counts are public on the
     * details page anyway, no need for auth.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel("torrent.{$this->torrent_id}.peers")];
    }

    public function broadcastAs(): string
    {
        return 'TorrentPeersUpdated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'torrent_id' => $this->torrent_id,
            'seeders' => $this->seeders,
            'leechers' => $this->leechers,
            'comments' => $this->comments,
            'html' => $this->html,
        ];
    }
}

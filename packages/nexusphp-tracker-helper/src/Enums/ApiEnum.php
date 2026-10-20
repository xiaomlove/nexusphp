<?php

namespace NexusPlugin\Tracker\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Contracts\Support\Htmlable;
use NexusPlugin\Tracker\Tracker;

enum ApiEnum: string implements HasLabel
{
    case USER_INFO = '/users/get';
    case TORRENT_INFO = '/torrents/get';
    case TORRENT_PEER_LIST = '/list-peer';
    case TORRENT_PEER_COUNT = '/list-seeder-leecher-count';
    case PROMOTION_INFO = '/promotion/get';
    case PEER_INFO = '/peers/get';
//    case PEER_TTL = '/peers/ttl';
    case SEED_BOX_CHECK = '/seed-box/check';
    case SNATCHED_INFO = '/snatched/get';

    public function getLabel(): string|Htmlable|null
    {
        return match ($this) {
            self::USER_INFO => Tracker::trans("api.api.user_info") . " - " . $this->value,
            self::TORRENT_INFO => Tracker::trans("api.api.torrent_info") . " - " . $this->value,
            self::SNATCHED_INFO => Tracker::trans("api.api.snatched_info") . " - " . $this->value,
            self::PROMOTION_INFO => Tracker::trans("api.api.promotion_info") . " - " . $this->value,
            self::PEER_INFO => Tracker::trans("api.api.peer_info") . " - " . $this->value,
//            self::PEER_TTL => Tracker::trans("api.api.peer_ttl") . " - " . $this->value,
            self::TORRENT_PEER_LIST => Tracker::trans("api.api.torrent_peer_list") . " - " . $this->value,
            self::TORRENT_PEER_COUNT => Tracker::trans("api.api.torrent_peer_count") . " - " . $this->value,
            self::SEED_BOX_CHECK => Tracker::trans("api.api.seed_box_check") . " - " . $this->value,
            default => '',
        };
    }

    public function getRequireParameters(): array
    {
        return match ($this) {
            self::USER_INFO => ['user_id'],
            self::TORRENT_INFO => ['torrent_id'],
            self::SNATCHED_INFO => ['user_id', 'torrent_id'],
            self::PROMOTION_INFO => ['torrent_id'],
            self::PEER_INFO => ['user_id', 'torrent_id', 'peer_id'],
//            self::PEER_TTL => ['torrent_id'],
            self::TORRENT_PEER_LIST => ['torrent_id'],
            self::TORRENT_PEER_COUNT =>['torrent_id'],
            self::SEED_BOX_CHECK => ['ip'],
            default => '',
        };
    }

}

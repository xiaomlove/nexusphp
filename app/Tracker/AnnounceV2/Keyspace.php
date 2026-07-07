<?php

declare(strict_types=1);

namespace App\Tracker\AnnounceV2;

final class Keyspace
{
    public const SHARDS = 64;

    public static function keys(int $torrentId, int $userId, string $peerId): array
    {
        $shard = self::shard($torrentId);
        $tag = "{announce-v2:$shard}";
        $swarm = "av2:$tag:swarm:$torrentId";
        $member = $userId . ':' . bin2hex($peerId);

        return [
            'shard' => $shard,
            'member' => $member,
            'session' => "$swarm:session:$member",
            'data' => "$swarm:data",
            'active' => "$swarm:active",
            'seeders' => "$swarm:seeders",
            'leechers' => "$swarm:leechers",
            'meta' => "$swarm:meta",
            'stream' => "av2:$tag:accounting",
        ];
    }

    public static function stream(int $shard): string
    {
        return "av2:{announce-v2:$shard}:accounting";
    }

    private static function shard(int $torrentId): int
    {
        return $torrentId % self::SHARDS;
    }
}


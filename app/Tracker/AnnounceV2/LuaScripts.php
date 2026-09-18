<?php

declare(strict_types=1);

namespace App\Tracker\AnnounceV2;

final class LuaScripts
{
    /**
     * KEYS: session, peer-data hash, active zset, seeders zset, leechers zset,
     *       swarm meta hash, accounting stream.
     *
     * Keep Lua as the smallest atomic section: peer-session transition,
     * swarm counters, and the accounting outbox entry. Request parsing,
     * permission checks, promotion policy, and decimal uint64 conversion stay
     * in PHP where they are easier to test and debug.
     */
    public static function announce(): string
    {
        return implode("\n\n", [
            self::luaCounterHelpers(),
            self::luaBindArguments(),
            self::luaPrepareSwarm(),
            self::luaLoadSession(),
            self::luaTransitionAndOutbox(),
            self::luaPeerResponse(),
        ]);
    }

    public static function evalAnnounce(\Redis $redis, array $keys, array $arguments): array
    {
        static $sha = null;

        $payload = [...$keys, ...$arguments];
        $keyCount = count($keys);

        if ($sha === null) {
            $sha = (string) $redis->script('load', self::announce());
        }

        try {
            $result = $redis->evalSha($sha, $payload, $keyCount);
        } catch (\RedisException $e) {
            if (!str_contains($e->getMessage(), 'NOSCRIPT')) {
                throw $e;
            }
            $sha = (string) $redis->script('load', self::announce());
            $result = $redis->evalSha($sha, $payload, $keyCount);
        }

        return is_array($result) ? $result : [];
    }

    private static function luaCounterHelpers(): string
    {
        return <<<'LUA'
-- uint64 counters are passed as hi/lo 32-bit parts. Lua only needs comparison
-- and subtraction; decimal string conversion remains in PHP.
local BASE = 4294967296

local function before(ahi, alo, bhi, blo)
    return ahi < bhi or (ahi == bhi and alo < blo)
end

local function diff(ahi, alo, bhi, blo)
    if before(ahi, alo, bhi, blo) then return 0, 0 end
    local lo = alo - blo
    local hi = ahi - bhi
    if lo < 0 then lo = lo + BASE; hi = hi - 1 end
    return hi, lo
end

local function decr(field)
    local value = tonumber(redis.call('HINCRBY', KEYS[6], field, -1))
    if value < 0 then redis.call('HSET', KEYS[6], field, 0) end
end
LUA;
    }

    private static function luaBindArguments(): string
    {
        return <<<'LUA'
-- Hot-path inputs prepared by PHP. Business policy is already resolved before
-- entering Redis; Lua only applies the atomic state transition.
local member = ARGV[1]
local now = tonumber(ARGV[2])
local ttl = tonumber(ARGV[3])
local event = ARGV[4]
local upHi = tonumber(ARGV[5])
local upLo = tonumber(ARGV[6])
local uploaded = ARGV[7]
local downHi = tonumber(ARGV[8])
local downLo = tonumber(ARGV[9])
local downloaded = ARGV[10]
local left = ARGV[11]
local newSeeder = ARGV[12] == '1'
local peerData = ARGV[13]
local torrentId = ARGV[14]
local userId = ARGV[15]
local peerHex = ARGV[16]
local ip = ARGV[17]
local port = ARGV[18]
local agent = ARGV[19]
local numwant = tonumber(ARGV[20])
local cleanupLimit = tonumber(ARGV[21])
local minUpdate = tonumber(ARGV[22])
LUA;
    }

    private static function luaPrepareSwarm(): string
    {
        return <<<'LUA'
-- Redis is the source of truth for live swarm counts. Warm production state
-- with tracker:announce-v2-warmup before routing traffic here.
redis.call('HSETNX', KEYS[6], 'seeders', 0)
redis.call('HSETNX', KEYS[6], 'leechers', 0)
redis.call('HSETNX', KEYS[6], 'completed', ARGV[25])

-- Amortized stale-peer cleanup keeps normal announces near O(1) and bounds
-- Redis memory without a separate cleanup job in the critical path.
local stale = redis.call('ZRANGEBYSCORE', KEYS[3], '-inf', now - ttl, 'LIMIT', 0, cleanupLimit)
for _, staleMember in ipairs(stale) do
    if staleMember ~= member then
        if redis.call('ZREM', KEYS[4], staleMember) == 1 then decr('seeders') end
        if redis.call('ZREM', KEYS[5], staleMember) == 1 then decr('leechers') end
        redis.call('ZREM', KEYS[3], staleMember)
        redis.call('HDEL', KEYS[2], staleMember)
    end
end
LUA;
    }

    private static function luaLoadSession(): string
    {
        return <<<'LUA'
-- Load the previous announce accepted for this torrent/user/peer_id. It is the
-- baseline for delta accounting and completed idempotency.
local exists = redis.call('EXISTS', KEYS[1]) == 1
local oldUpHi = 0
local oldUpLo = 0
local oldUploaded = '0'
local oldDownHi = 0
local oldDownLo = 0
local oldDownloaded = '0'
local oldSeeder = false
local lastSeen = 0

if exists then
    local old = redis.call('HMGET', KEYS[1], 'up_hi', 'up_lo', 'uploaded',
        'down_hi', 'down_lo', 'downloaded', 'seeder', 'last_seen')
    oldUpHi = tonumber(old[1]) or 0
    oldUpLo = tonumber(old[2]) or 0
    oldUploaded = old[3] or '0'
    oldDownHi = tonumber(old[4]) or 0
    oldDownLo = tonumber(old[5]) or 0
    oldDownloaded = old[6] or '0'
    oldSeeder = old[7] == '1'
    lastSeen = tonumber(old[8]) or 0
end

local accepted = true
if exists and event == '' and now - lastSeen < minUpdate then accepted = false end
LUA;
    }

    private static function luaTransitionAndOutbox(): string
    {
        return <<<'LUA'
-- Apply the peer-state transition and emit the accounting outbox event in the
-- same Redis script, so state and accounting cannot diverge.
local deltaUpHi = 0
local deltaUpLo = 0
local deltaDownHi = 0
local deltaDownLo = 0
local duration = 0
local completed = 0
local streamId = ''

if accepted then
    if exists then
        deltaUpHi, deltaUpLo = diff(upHi, upLo, oldUpHi, oldUpLo)
        deltaDownHi, deltaDownLo = diff(downHi, downLo, oldDownHi, oldDownLo)
        if before(upHi, upLo, oldUpHi, oldUpLo) then
            upHi = oldUpHi; upLo = oldUpLo; uploaded = oldUploaded
        end
        if before(downHi, downLo, oldDownHi, oldDownLo) then
            downHi = oldDownHi; downLo = oldDownLo; downloaded = oldDownloaded
        end
        duration = math.max(0, math.min(ttl, now - lastSeen))
    end

    if event == 'stopped' then
        if exists then
            redis.call('DEL', KEYS[1])
            redis.call('HDEL', KEYS[2], member)
            redis.call('ZREM', KEYS[3], member)
            if redis.call('ZREM', KEYS[4], member) == 1 then decr('seeders') end
            if redis.call('ZREM', KEYS[5], member) == 1 then decr('leechers') end
        else
            accepted = false
        end
    else
        if not exists then
            redis.call('HINCRBY', KEYS[6], newSeeder and 'seeders' or 'leechers', 1)
        elseif oldSeeder ~= newSeeder then
            decr(oldSeeder and 'seeders' or 'leechers')
            redis.call('HINCRBY', KEYS[6], newSeeder and 'seeders' or 'leechers', 1)
        end

        if exists and event == 'completed' and not oldSeeder and newSeeder then
            completed = 1
            redis.call('HINCRBY', KEYS[6], 'completed', 1)
        end

        redis.call('HSET', KEYS[1],
            'up_hi', upHi, 'up_lo', upLo, 'uploaded', uploaded,
            'down_hi', downHi, 'down_lo', downLo, 'downloaded', downloaded, 'left', left,
            'seeder', newSeeder and '1' or '0', 'last_seen', now)
        redis.call('EXPIRE', KEYS[1], ttl + 60)
        redis.call('HSET', KEYS[2], member, peerData)
        redis.call('ZADD', KEYS[3], now, member)
        if newSeeder then
            redis.call('ZADD', KEYS[4], now, member); redis.call('ZREM', KEYS[5], member)
        else
            redis.call('ZADD', KEYS[5], now, member); redis.call('ZREM', KEYS[4], member)
        end
    end

    if accepted then
        local counts = redis.call('HMGET', KEYS[6], 'seeders', 'leechers', 'completed')
        -- Never length-trim the accounting stream here: MAXLEN can evict
        -- unconsumed entries during an outage. A maintenance task may trim by
        -- a consumer-group-safe MINID after persistence.
        streamId = redis.call('XADD', KEYS[7], '*',
            'torrent_id', torrentId, 'user_id', userId, 'peer_hex', peerHex,
            'event', event,
            'uploaded_delta_hi', deltaUpHi, 'uploaded_delta_lo', deltaUpLo,
            'downloaded_delta_hi', deltaDownHi, 'downloaded_delta_lo', deltaDownLo,
            'uploaded', uploaded, 'downloaded', downloaded, 'peer_data', peerData,
            'duration', duration, 'duration_was_seeder', oldSeeder and '1' or '0',
            'left', left, 'ip', ip, 'port', port, 'agent', agent,
            'completed', completed, 'seeders', counts[1], 'leechers', counts[2],
            'up_multiplier', ARGV[26], 'down_multiplier', ARGV[27],
            'credit_cap', ARGV[28], 'at', now)
    end
end
LUA;
    }

    private static function luaPeerResponse(): string
    {
        return <<<'LUA'
-- Return current swarm counts and compact peers. Seeders prefer leechers;
-- leechers sample active peers.
local counts = redis.call('HMGET', KEYS[6], 'seeders', 'leechers', 'completed')
local source = newSeeder and KEYS[5] or KEYS[3]
local result = {accepted and 0 or 1, counts[1], counts[2], counts[3], streamId}
if event ~= 'stopped' and numwant > 0 then
    local candidates = redis.call('ZRANDMEMBER', source, numwant + 8)
    for _, candidate in ipairs(candidates) do
        if candidate ~= member and #result < numwant + 5 then
            local data = redis.call('HGET', KEYS[2], candidate)
            if data then result[#result + 1] = data end
        end
    end
end
return result
LUA;
    }
}

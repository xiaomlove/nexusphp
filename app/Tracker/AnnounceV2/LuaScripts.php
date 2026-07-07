<?php

declare(strict_types=1);

namespace App\Tracker\AnnounceV2;

final class LuaScripts
{
    /**
     * KEYS: session, peer-data hash, active zset, seeders zset, leechers zset,
     *       swarm meta hash, accounting stream.
     *
     * Counter arithmetic is string based. BitTorrent counters are uint64 and
     * cannot safely pass through Redis Lua's double precision number type.
     */
    public static function announce(): string
    {
        return <<<'LUA'
local function norm(v)
    v = string.gsub(v or '0', '^0+', '')
    if v == '' then return '0' end
    return v
end

local function cmp(a, b)
    a = norm(a); b = norm(b)
    if #a ~= #b then return #a > #b and 1 or -1 end
    if a == b then return 0 end
    return a > b and 1 or -1
end

local function sub(a, b)
    a = norm(a); b = norm(b)
    if cmp(a, b) < 0 then return '0' end
    local out = {}
    local ai = #a
    local bi = #b
    local borrow = 0
    while ai > 0 do
        local av = string.byte(a, ai) - 48 - borrow
        local bv = bi > 0 and string.byte(b, bi) - 48 or 0
        if av < bv then av = av + 10; borrow = 1 else borrow = 0 end
        out[#out + 1] = string.char(48 + av - bv)
        ai = ai - 1; bi = bi - 1
    end
    local result = string.reverse(table.concat(out))
    return norm(result)
end

local function decr(field)
    local value = tonumber(redis.call('HINCRBY', KEYS[6], field, -1))
    if value < 0 then redis.call('HSET', KEYS[6], field, 0) end
end

local member = ARGV[1]
local now = tonumber(ARGV[2])
local ttl = tonumber(ARGV[3])
local event = ARGV[4]
local uploaded = norm(ARGV[5])
local downloaded = norm(ARGV[6])
local left = norm(ARGV[7])
local peerData = ARGV[8]
local torrentId = ARGV[9]
local userId = ARGV[10]
local peerHex = ARGV[11]
local ip = ARGV[12]
local port = ARGV[13]
local agent = ARGV[14]
local numwant = tonumber(ARGV[15])
local cleanupLimit = tonumber(ARGV[16])
local minUpdate = tonumber(ARGV[17])

-- Active counts are Redis-native. Existing production peers must be loaded by
-- tracker:announce-v2-warmup before traffic is switched to this endpoint.
redis.call('HSETNX', KEYS[6], 'seeders', 0)
redis.call('HSETNX', KEYS[6], 'leechers', 0)
redis.call('HSETNX', KEYS[6], 'completed', ARGV[20])

-- Amortized stale-peer cleanup keeps normal announces O(1) and bounds memory.
local stale = redis.call('ZRANGEBYSCORE', KEYS[3], '-inf', now - ttl, 'LIMIT', 0, cleanupLimit)
for _, staleMember in ipairs(stale) do
    if redis.call('ZREM', KEYS[4], staleMember) == 1 then decr('seeders') end
    if redis.call('ZREM', KEYS[5], staleMember) == 1 then decr('leechers') end
    redis.call('ZREM', KEYS[3], staleMember)
    redis.call('HDEL', KEYS[2], staleMember)
end

local exists = redis.call('EXISTS', KEYS[1]) == 1
local oldUploaded = '0'
local oldDownloaded = '0'
local oldLeft = '0'
local oldSeeder = false
local lastSeen = 0

if exists then
    local old = redis.call('HMGET', KEYS[1], 'uploaded', 'downloaded', 'left', 'seeder', 'last_seen')
    oldUploaded = norm(old[1]); oldDownloaded = norm(old[2]); oldLeft = norm(old[3])
    oldSeeder = old[4] == '1'; lastSeen = tonumber(old[5]) or 0
end

local accepted = true
if exists and event == '' and now - lastSeen < minUpdate then accepted = false end

local deltaUp = '0'
local deltaDown = '0'
local duration = 0
local completed = 0
local newSeeder = left == '0'
local streamId = ''

if accepted then
    if exists then
        deltaUp = sub(uploaded, oldUploaded)
        deltaDown = sub(downloaded, oldDownloaded)
        -- A client counter reset must never lower the accounting baseline;
        -- otherwise the same bytes can be credited again on the next report.
        if cmp(uploaded, oldUploaded) < 0 then uploaded = oldUploaded end
        if cmp(downloaded, oldDownloaded) < 0 then downloaded = oldDownloaded end
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

        if exists and event == 'completed' and cmp(oldLeft, '0') > 0 and left == '0' then
            completed = 1
            redis.call('HINCRBY', KEYS[6], 'completed', 1)
        end

        redis.call('HSET', KEYS[1],
            'uploaded', uploaded, 'downloaded', downloaded, 'left', left,
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
            'event', event, 'uploaded_delta', deltaUp, 'downloaded_delta', deltaDown,
            'uploaded', uploaded, 'downloaded', downloaded, 'peer_data', peerData,
            'duration', duration, 'duration_was_seeder', oldSeeder and '1' or '0',
            'left', left, 'ip', ip, 'port', port, 'agent', agent,
            'completed', completed, 'seeders', counts[1], 'leechers', counts[2],
            'up_multiplier', ARGV[21], 'down_multiplier', ARGV[22],
            'credit_cap', ARGV[23], 'at', now)
    end
end

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

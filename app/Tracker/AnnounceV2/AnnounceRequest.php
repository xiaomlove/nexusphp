<?php

declare(strict_types=1);

namespace App\Tracker\AnnounceV2;

use InvalidArgumentException;

final readonly class AnnounceRequest
{
    private const UINT64_MAX = '18446744073709551615';

    private const EVENTS = ['', 'started', 'completed', 'stopped', 'paused'];

    private const BLACKLISTED_PORTS = [411, 412, 413, 1214, 4662, 6346, 6347, 6699];

    public function __construct(
        public string $passkey,
        public string $infoHash,
        public string $peerId,
        public int $port,
        public string $uploaded,
        public string $downloaded,
        public string $left,
        public string $event,
        public int $numwant,
        public string $agent,
        public string $ip,
        public string $ipv4,
        public string $ipv6,
    ) {
    }

    public static function fromGlobals(array $query, array $server): self
    {
        foreach (['passkey', 'info_hash', 'peer_id', 'port', 'uploaded', 'downloaded', 'left'] as $field) {
            if (!array_key_exists($field, $query)) {
                throw new InvalidArgumentException("Missing key: $field");
            }
        }

        $passkey = (string) $query['passkey'];
        if (!preg_match('/\A[a-f0-9]{32}\z/iD', $passkey)) {
            throw new InvalidArgumentException('Invalid passkey');
        }

        $infoHash = (string) $query['info_hash'];
        $peerId = (string) $query['peer_id'];
        if (strlen($infoHash) !== 20) {
            throw new InvalidArgumentException('Invalid info_hash');
        }
        if (strlen($peerId) !== 20) {
            throw new InvalidArgumentException('Invalid peer_id');
        }

        $event = strtolower((string) ($query['event'] ?? ''));
        if (!in_array($event, self::EVENTS, true)) {
            throw new InvalidArgumentException('Invalid event');
        }

        $port = self::boundedInteger($query['port'], 0, 65535, 'port');
        if (($port === 0 && $event !== 'stopped') || in_array($port, self::BLACKLISTED_PORTS, true)
            || ($port >= 6881 && $port <= 6889)) {
            throw new InvalidArgumentException('Invalid port');
        }

        $uploaded = self::counter($query['uploaded'], 'uploaded');
        $downloaded = self::counter($query['downloaded'], 'downloaded');
        $left = self::counter($query['left'], 'left');
        $numwant = self::boundedInteger($query['numwant'] ?? $query['num_want'] ?? 50, 0, 50, 'numwant');

        $agent = (string) ($server['HTTP_USER_AGENT'] ?? '');
        if ($agent === '' || strlen($agent) > 60 || preg_match('/\A(?:Mozilla|Opera|Links|Lynx)/i', $agent)) {
            throw new InvalidArgumentException('Invalid user-agent');
        }

        $ip = self::requestIp($server);
        $ipv4 = isIPV4($ip) ? $ip : self::validIp((string) ($query['ipv4'] ?? ''), FILTER_FLAG_IPV4);
        $ipv6 = isIPV6($ip) ? $ip : self::validIp((string) ($query['ipv6'] ?? ''), FILTER_FLAG_IPV6);
        if ($ipv4 === '' && $ipv6 === '') {
            throw new InvalidArgumentException('Invalid IP address');
        }

        return new self(
            strtolower($passkey), $infoHash, $peerId, $port, $uploaded, $downloaded, $left,
            $event, $numwant, $agent, $ip, $ipv4, $ipv6
        );
    }

    public function isSeeder(): bool
    {
        return $this->left === '0';
    }

    private static function counter(mixed $value, string $name): string
    {
        $value = (string) $value;
        if (!preg_match('/\A(?:0|[1-9][0-9]{0,19})\z/D', $value)) {
            throw new InvalidArgumentException("Invalid $name");
        }
        $value = ltrim($value, '0') ?: '0';
        if (strlen($value) > strlen(self::UINT64_MAX)
            || (strlen($value) === strlen(self::UINT64_MAX) && $value > self::UINT64_MAX)) {
            throw new InvalidArgumentException("Invalid $name");
        }
        return $value;
    }

    private static function boundedInteger(mixed $value, int $min, int $max, string $name): int
    {
        $value = (string) $value;
        if (!preg_match('/\A[0-9]{1,10}\z/D', $value)) {
            throw new InvalidArgumentException("Invalid $name");
        }
        $integer = (int) $value;
        if ($integer < $min || $integer > $max) {
            throw new InvalidArgumentException("Invalid $name");
        }
        return $integer;
    }

    private static function validIp(string $ip, int $flag): string
    {
        return filter_var($ip, FILTER_VALIDATE_IP, $flag) === false ? '' : $ip;
    }

    private static function requestIp(array $server): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'] as $field) {
            if (empty($server[$field])) {
                continue;
            }
            $ip = trim(explode(',', (string) $server[$field], 2)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                return $ip;
            }
        }
        return '';
    }
}

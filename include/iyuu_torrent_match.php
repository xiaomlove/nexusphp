<?php

if (!defined('NOVA_IYUU_API_BASE')) {
    define('NOVA_IYUU_API_BASE', 'https://api.iyuu.cn');
    define('NOVA_IYUU_CLIENT_VERSION', '1.10.23');
    define('NOVA_IYUU_RESEED_API_BASE', 'http://2025.iyuu.cn');
    define('NOVA_IYUU_RESEED_VERSION', '8.2.0');
}

function nova_iyuu_match_config(): array
{
    $token = trim((string)get_setting('iyuu_match.token', ''));
    if ($token === '') {
        $token = trim((string)nexus_env('IYUU_RESEED_TOKEN', ''));
    }

    return [
        'enabled' => get_setting('iyuu_match.enabled', 'yes') === 'yes',
        'token' => $token,
        'cache_minutes' => max(5, min(1440, (int)get_setting('iyuu_match.cache_minutes', 60))),
        'timeout_seconds' => max(2, min(15, (int)get_setting('iyuu_match.timeout_seconds', 5))),
    ];
}

function nova_render_iyuu_torrent_match(array $torrent): void
{
    $config = nova_iyuu_match_config();
    if (!$config['enabled']) {
        return;
    }

    \Nexus\Nexus::css('styles/nova-iyuu-match.css?v=2026081603', 'header', true);
    \Nexus\Nexus::js('js/nova-iyuu-match.js?v=2026091201', 'footer', true);

    $torrentId = (int)($torrent['id'] ?? 0);
    $status = $config['token'] === '' ? '尚未配置 IYUU Token' : '正在查询其他站点...';
    $html = sprintf(
        '<div class="nova-iyuu-match" data-endpoint="iyuu-torrent-match.php?id=%d" data-configured="%s"><span class="nova-iyuu-match__status">%s</span></div>',
        $torrentId,
        $config['token'] === '' ? 'no' : 'yes',
        htmlspecialchars($status, ENT_QUOTES)
    );
    tr('其他站点', $html, 1);
}

function nova_iyuu_cache_remember(string $key, int $ttl, callable $callback)
{
    $directory = dirname(__DIR__) . '/storage/framework/cache/nova-iyuu';
    $path = $directory . '/' . hash('sha256', $key) . '.json';
    $cached = null;
    if (is_file($path)) {
        $decoded = json_decode((string)@file_get_contents($path), true);
        if (is_array($decoded) && array_key_exists('value', $decoded)) {
            $cached = $decoded;
            if ((int)($decoded['expires_at'] ?? 0) > time()) {
                return $decoded['value'];
            }
        }
    }

    if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
        return $callback();
    }

    $lock = @fopen($path . '.lock', 'c');
    if (is_resource($lock)) {
        @flock($lock, LOCK_EX);
        if (is_file($path)) {
            $decoded = json_decode((string)@file_get_contents($path), true);
            if (is_array($decoded) && array_key_exists('value', $decoded)) {
                $cached = $decoded;
                if ((int)($decoded['expires_at'] ?? 0) > time()) {
                    @flock($lock, LOCK_UN);
                    @fclose($lock);
                    return $decoded['value'];
                }
            }
        }
    }

    try {
        $value = $callback();
        $payload = json_encode([
            'expires_at' => time() + max(1, $ttl),
            'value' => $value,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($payload)) {
            $temporary = @tempnam($directory, 'write-');
            if (is_string($temporary)) {
                @file_put_contents($temporary, $payload, LOCK_EX);
                @chmod($temporary, 0640);
                @rename($temporary, $path);
            }
        }
    } catch (Throwable $exception) {
        if (is_array($cached) && array_key_exists('value', $cached)) {
            $value = $cached['value'];
        } else {
            throw $exception;
        }
    } finally {
        if (is_resource($lock)) {
            @flock($lock, LOCK_UN);
            @fclose($lock);
        }
    }

    return $value;
}

function nova_iyuu_find_torrent_matches(string $infoHash): array
{
    $config = nova_iyuu_match_config();
    if (!$config['enabled']) {
        return ['configured' => true, 'sites' => [], 'disabled' => true];
    }
    if ($config['token'] === '') {
        return ['configured' => false, 'sites' => []];
    }

    if (strlen($infoHash) === 20) {
        $infoHash = bin2hex($infoHash);
    } else {
        $infoHash = strtolower(trim($infoHash));
    }
    if (!preg_match('/^[a-f0-9]{40}$/', $infoHash)) {
        throw new InvalidArgumentException('种子 Hash 格式无效');
    }

    $tokenKey = substr(hash('sha256', $config['token']), 0, 16);
    $cacheKey = 'nova_iyuu_match_v5:' . $tokenKey . ':' . $infoHash;
    $ttl = $config['cache_minutes'] * 60;

    return nova_iyuu_cache_remember(
        $cacheKey,
        $ttl,
        function () use ($config, $infoHash, $tokenKey) {
            return nova_iyuu_get_current_matches($config, $infoHash, $tokenKey);
        }
    );

    return \Illuminate\Support\Facades\Cache::remember($cacheKey, $ttl, function () use ($config, $infoHash, $tokenKey) {
        $hashes = [$infoHash];
        $hashJson = json_encode($hashes, JSON_UNESCAPED_SLASHES);
        $response = nova_iyuu_request('/api/infohash', 'POST', [
            'hash' => $hashJson,
            'sha1' => sha1($hashJson),
            'sign' => $config['token'],
            'timestamp' => time(),
            'version' => NOVA_IYUU_CLIENT_VERSION,
        ], $config['timeout_seconds']);

        if ((int)($response['ret'] ?? 0) === 404 && str_contains((string)($response['msg'] ?? ''), '接口服务')) {
            return nova_iyuu_get_subject_summary($config, $infoHash);
        }
        if ((int)($response['ret'] ?? 0) !== 200) {
            throw new RuntimeException((string)($response['msg'] ?? 'IYUU 匹配服务暂时不可用'));
        }

        $sites = nova_iyuu_get_sites($config, $tokenKey);

        $matched = [];
        foreach (($response['data'] ?? []) as $hash => $group) {
            if (strtolower((string)$hash) === $infoHash) {
                $matched = is_array($group['torrent'] ?? null) ? $group['torrent'] : [];
                break;
            }
        }

        $result = [];
        $currentHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
        $currentHost = preg_replace('/:\d+$/', '', $currentHost);
        foreach ($matched as $match) {
            $sid = (int)($match['sid'] ?? 0);
            $torrentId = (int)($match['torrent_id'] ?? 0);
            if ($sid <= 0 || $torrentId <= 0 || empty($sites[$sid])) {
                continue;
            }

            $site = $sites[$sid];
            $url = nova_iyuu_build_details_url($site, $torrentId);
            if ($url === null) {
                continue;
            }
            $host = strtolower((string)parse_url($url, PHP_URL_HOST));
            if ($host === '' || $host === $currentHost) {
                continue;
            }

            $result[$sid . ':' . $torrentId] = [
                'name' => nova_iyuu_site_display_name((string)($site['site'] ?? ''), $host),
                'url' => $url,
                'icon' => nova_iyuu_site_icon_url($sid, $site, $host, (string)(parse_url($url, PHP_URL_SCHEME) ?: 'https')),
            ];
        }

        return [
            'configured' => true,
            'sites' => array_values($result),
            'updated_at' => date('Y-m-d H:i'),
        ];
    });
}

function nova_iyuu_get_current_matches(array $config, string $infoHash, string $tokenKey): array
{
    $sites = nova_iyuu_get_current_sites($config, $tokenKey);
    $sidList = array_keys($sites);
    sort($sidList, SORT_NUMERIC);

    $sidCacheKey = 'nova_iyuu_sid_sha1:' . $tokenKey . ':' . sha1(json_encode($sidList));
    $sidSha1 = nova_iyuu_cache_remember(
        $sidCacheKey,
        518400,
        function () use ($config, $sidList) {
            $response = nova_iyuu_current_request('/reseed/sites/reportExisting', 'POST', [
                'sid_list' => $sidList,
            ], $config);
            if ((int)($response['code'] ?? -1) !== 0 || empty($response['data']['sid_sha1'])) {
                throw new RuntimeException((string)($response['msg'] ?? 'IYUU site authorization failed'));
            }
            return (string)$response['data']['sid_sha1'];
        }
    );

    $hashJson = json_encode([$infoHash], JSON_UNESCAPED_SLASHES);
    $response = nova_iyuu_current_request('/reseed/index/index', 'POST', [
        'hash' => $hashJson,
        'sha1' => sha1($hashJson),
        'sid_sha1' => $sidSha1,
        'timestamp' => time(),
        'version' => NOVA_IYUU_RESEED_VERSION,
    ], $config);

    $code = (int)($response['code'] ?? -1);
    if ($code === 404) {
        return [
            'configured' => true,
            'sites' => [],
            'match_count' => 0,
            'limited' => false,
            'updated_at' => date('Y-m-d H:i'),
        ];
    }
    if ($code !== 0 || !is_array($response['data'] ?? null)) {
        throw new RuntimeException((string)($response['msg'] ?? 'IYUU reseed lookup failed'));
    }

    $result = [];
    $currentHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? 'pt.novahd.top'));
    $currentHost = preg_replace('/:\d+$/', '', $currentHost);
    foreach ($response['data'] as $group) {
        foreach (($group['torrent'] ?? []) as $match) {
            $sid = (int)($match['sid'] ?? 0);
            $torrentId = (int)($match['torrent_id'] ?? 0);
            if ($sid <= 0 || $torrentId <= 0 || empty($sites[$sid])) {
                continue;
            }

            $site = $sites[$sid];
            $url = nova_iyuu_build_details_url($site, $torrentId);
            if ($url === null) {
                continue;
            }
            $host = strtolower((string)parse_url($url, PHP_URL_HOST));
            if ($host === '' || $host === $currentHost) {
                continue;
            }

            $result[$sid . ':' . $torrentId] = [
                'name' => nova_iyuu_site_display_name((string)($site['site'] ?? ''), $host),
                'url' => $url,
                'icon' => nova_iyuu_site_icon_url($sid, $site, $host, (string)(parse_url($url, PHP_URL_SCHEME) ?: 'https')),
            ];
        }
    }

    return [
        'configured' => true,
        'sites' => array_values($result),
        'match_count' => count($result),
        'limited' => false,
        'updated_at' => date('Y-m-d H:i'),
    ];
}

function nova_iyuu_get_current_sites(array $config, string $tokenKey): array
{
    $cacheKey = 'nova_iyuu_current_sites:' . $tokenKey;
    return nova_iyuu_cache_remember($cacheKey, 86400, function () use ($config) {
        $response = nova_iyuu_current_request('/reseed/sites/index', 'GET', [], $config, 90);
        if ((int)($response['code'] ?? -1) !== 0 || !is_array($response['data']['sites'] ?? null)) {
            throw new RuntimeException((string)($response['msg'] ?? 'IYUU site list failed'));
        }

        $sites = [];
        foreach ($response['data']['sites'] as $site) {
            $sid = (int)($site['id'] ?? $site['sid'] ?? 0);
            if ($sid > 0) {
                $sites[$sid] = $site;
            }
        }
        if (!$sites) {
            throw new RuntimeException('IYUU returned an empty site list');
        }
        return $sites;
    });
}

function nova_iyuu_current_request(string $path, string $method, array $data, array $config, int $timeout = 30): array
{
    $url = NOVA_IYUU_RESEED_API_BASE . $path;
    if ($method === 'GET' && $data) {
        $url .= '?' . http_build_query($data, '', '&', PHP_QUERY_RFC3986);
    }

    $curl = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => max($timeout, (int)$config['timeout_seconds']),
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'token: ' . $config['token'],
        ],
        CURLOPT_USERAGENT => 'NovaHD-IYUU-Match/2.0',
        CURLOPT_ENCODING => '',
    ];
    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($data, '', '&', PHP_QUERY_RFC3986);
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
    }
    curl_setopt_array($curl, $options);
    $body = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if ($body === false || $error !== '' || $status < 200 || $status >= 300) {
        throw new RuntimeException('IYUU network request failed');
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('IYUU returned invalid data');
    }
    return $decoded;
}

function nova_iyuu_get_subject_summary(array $config, string $infoHash): array
{
    $response = nova_iyuu_request('/index.php', 'GET', [
        's' => 'App.Api.GetSubject',
        'info_hash' => $infoHash,
    ], max(15, $config['timeout_seconds']));
    $ret = (int)($response['ret'] ?? 0);
    if ($ret === 404 && str_contains((string)($response['msg'] ?? ''), '种子未收录')) {
        $matchCount = 0;
    } elseif ($ret === 200) {
        $matchCount = max(
            0,
            (int)($response['data']['pid_total'] ?? 0),
            (int)($response['data']['tid_total'] ?? 0)
        );
    } else {
        throw new RuntimeException((string)($response['msg'] ?? 'IYUU 收录查询暂时不可用'));
    }

    return [
        'configured' => true,
        'sites' => [],
        'match_count' => $matchCount,
        'limited' => true,
        'updated_at' => date('Y-m-d H:i'),
    ];
}

function nova_iyuu_get_sites(array $config, string $tokenKey): array
{
    $cacheKey = 'nova_iyuu_sites:' . $tokenKey;
    return \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400, function () use ($config) {
        $response = nova_iyuu_request('/api/sites', 'GET', [
            'sign' => $config['token'],
            'version' => NOVA_IYUU_CLIENT_VERSION,
        ], $config['timeout_seconds']);
        if ((int)($response['ret'] ?? 0) !== 200 || !is_array($response['data']['sites'] ?? null)) {
            throw new RuntimeException((string)($response['msg'] ?? '无法读取 IYUU 站点列表'));
        }

        $sites = [];
        foreach ($response['data']['sites'] as $site) {
            $sid = (int)($site['id'] ?? 0);
            if ($sid > 0) {
                $sites[$sid] = $site;
            }
        }
        return $sites;
    });
}

function nova_iyuu_request(string $path, string $method, array $data, int $timeout): array
{
    $url = NOVA_IYUU_API_BASE . $path;
    if ($method === 'GET') {
        $url .= '?' . http_build_query($data, '', '&', PHP_QUERY_RFC3986);
    }

    $curl = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => min(3, $timeout),
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'NovaHD-IYUU-Match/1.0',
    ];
    if ($method === 'POST') {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($data, '', '&', PHP_QUERY_RFC3986);
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
    }
    curl_setopt_array($curl, $options);
    $body = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);

    if ($body === false || $error !== '' || $status < 200 || $status >= 300) {
        throw new RuntimeException('IYUU 网络请求失败');
    }
    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('IYUU 返回了无效数据');
    }
    return $decoded;
}

function nova_iyuu_site_icon_url(int $sid, array $site, string $host, string $scheme): string
{
    static $mapping = null;
    if ($mapping === null) {
        $path = dirname(__DIR__) . '/storage/app/nova-iyuu-site-logos.json';
        $decoded = is_file($path) ? json_decode((string)@file_get_contents($path), true) : null;
        $mapping = is_array($decoded) ? $decoded : [];
    }

    $siteName = strtolower(trim((string)($site['site'] ?? '')));
    $entry = $mapping[$siteName] ?? null;
    if (!is_array($entry)) {
        foreach ($mapping as $candidate) {
            if (is_array($candidate) && (int)($candidate['sid'] ?? 0) === $sid) {
                $entry = $candidate;
                break;
            }
        }
    }
    $url = is_array($entry) ? trim((string)($entry['url'] ?? '')) : '';
    if (str_starts_with($url, 'https://') || str_starts_with($url, 'http://')) {
        return $url;
    }
    return $scheme . '://' . $host . '/favicon.ico';
}

function nova_iyuu_build_details_url(array $site, int $torrentId): ?string
{
    $siteName = strtolower(trim((string)($site['site'] ?? '')));
    $baseUrl = trim((string)($site['base_url'] ?? ''));
    $baseUrl = preg_replace('#^https?://#i', '', $baseUrl);
    $baseUrl = trim($baseUrl, '/');
    $isMTeam = in_array($siteName, ['m-team', 'mteam'], true)
        || preg_match('/(^|\.)m-team\.cc$/i', $baseUrl) === 1;
    if ($isMTeam) {
        $baseUrl = 'kp.m-team.cc';
    }
    if ($baseUrl === '' || preg_match('/[\s@?#]/', $baseUrl)) {
        return null;
    }

    $scheme = $isMTeam ? 'https' : ((int)($site['is_https'] ?? 1) === 0 ? 'http' : 'https');
    $detailsPage = trim((string)($site['details_page'] ?? ''));
    if ($isMTeam) {
        $path = 'detail/' . $torrentId;
    } elseif ($detailsPage !== '') {
        $path = str_replace(['{torrent_id}', '{}'], (string)$torrentId, $detailsPage);
    } else {
        $downloadPage = strtolower((string)($site['download_page'] ?? ''));
        if (str_contains($downloadPage, 'ajax.php?action=download') || str_contains($downloadPage, 'torrents.php?action=download')) {
            $path = 'torrents.php?id=' . $torrentId;
        } elseif (str_contains($downloadPage, '/download/') || str_starts_with($downloadPage, 'download/')) {
            $path = 'torrent/' . $torrentId;
        } else {
            $path = 'details.php?id=' . $torrentId . '&hit=1';
        }
    }

    $path = ltrim($path, '/');
    $url = $scheme . '://' . $baseUrl . '/' . $path;
    $parts = parse_url($url);
    if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true) || empty($parts['host'])) {
        return null;
    }
    if (filter_var($parts['host'], FILTER_VALIDATE_IP)) {
        return null;
    }
    return $url;
}

function nova_iyuu_site_display_name(string $siteName, string $host): string
{
    $names = [
        'keepfrds' => '朋友',
        'ourbits' => '我堡',
        'hdsky' => '天空',
        'hdhome' => '家园',
        'pterclub' => '猫站',
        'pttime' => '时光',
        'm-team' => '馒头',
        'mteam' => '馒头',
        'tjupt' => '北洋园',
        'npupt' => '蒲公英',
        'byr' => '北邮人',
    ];
    $key = strtolower(trim($siteName));
    if (isset($names[$key])) {
        return $names[$key];
    }
    if ($siteName !== '') {
        return strtoupper($siteName);
    }
    return $host;
}

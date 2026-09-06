<?php

namespace Nexus\Imdb;

use Imdb\Config;
use Psr\SimpleCache\CacheInterface;

class TitleCache implements CacheInterface
{
    private Config $config;

    private bool $readOnly = false;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function setReadOnly(bool $readOnly): void
    {
        $this->readOnly = $readOnly;
    }

    public function get($key, $default = null)
    {
        if (!$this->config->cacheUse) {
            return $this->miss($default);
        }
        $imdbId = $this->extractImdbId((string) $key);
        if ($imdbId === '') {
            return $this->miss($default);
        }
        $map = $this->readMap($imdbId);
        if (!array_key_exists($key, $map)) {
            return $this->miss($default);
        }
        $cached = $map[$key];
        return is_string($cached) ? $cached : json_encode($cached);
    }

    public function set($key, $value, $ttl = null)
    {
        if ($this->readOnly || !$this->config->cacheStore) {
            return false;
        }
        $imdbId = $this->extractImdbId((string) $key);
        if ($imdbId === '') {
            return false;
        }
        $path = $this->path($imdbId);
        $fp = fopen($path, 'c+');
        if ($fp === false) {
            return false;
        }
        flock($fp, LOCK_EX);
        $raw = stream_get_contents($fp);
        $map = json_decode((string) $raw, true);
        if (!is_array($map)) {
            $map = [];
        }
        $decoded = json_decode((string) $value, true);
        $map[$key] = $decoded === null && $value !== 'null' ? $value : $decoded;
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }

    public function delete($key)
    {
        $imdbId = $this->extractImdbId((string) $key);
        if ($imdbId === '') {
            return false;
        }
        $map = $this->readMap($imdbId);
        if (!array_key_exists($key, $map)) {
            return true;
        }
        unset($map[$key]);
        if (empty($map)) {
            $path = $this->path($imdbId);
            return !is_file($path) || unlink($path);
        }
        return $this->writeMap($imdbId, $map);
    }

    public function clear()
    {
        return false;
    }

    public function getMultiple($keys, $default = null)
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }
        return $result;
    }

    public function setMultiple($values, $ttl = null)
    {
        $ok = true;
        foreach ($values as $key => $value) {
            $ok = $this->set($key, $value, $ttl) && $ok;
        }
        return $ok;
    }

    public function deleteMultiple($keys)
    {
        $ok = true;
        foreach ($keys as $key) {
            $ok = $this->delete($key) && $ok;
        }
        return $ok;
    }

    public function has($key)
    {
        $imdbId = $this->extractImdbId((string) $key);
        if ($imdbId === '') {
            return false;
        }
        $map = $this->readMap($imdbId);
        return array_key_exists($key, $map);
    }

    private function miss($default)
    {
        if ($this->readOnly && $default === null) {
            return '{}';
        }
        return $default;
    }

    private function extractImdbId(string $key): string
    {
        if (preg_match('/tt(\d{7,10})/', $key, $matches)) {
            return $matches[1];
        }
        return '';
    }

    private function path(string $imdbId): string
    {
        return rtrim($this->config->cacheDir, '/') . '/tt' . $imdbId . '.json';
    }

    private function readMap(string $imdbId): array
    {
        $path = $this->path($imdbId);
        if (!is_file($path)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);
        return is_array($data) ? $data : [];
    }

    private function writeMap(string $imdbId, array $map): bool
    {
        $path = $this->path($imdbId);
        return file_put_contents($path, json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) !== false;
    }
}

<?php

use Illuminate\Cache\CacheManager;
use Illuminate\Cache\Repository;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Redis\RedisManager;

/**
 * Thin shim that adapts the procedural legacy `$Cache` API
 * (~440 call sites in `public/*.php` / `include/*.php`) onto
 * Laravel's `Illuminate\Cache\Repository`.
 *
 * Before the Phase 1 collapse this class owned a hand-rolled phpredis
 * client (`pconnect` / `auth` / `select` ceremony), a custom
 * `serialize`/`unserialize` pair, and direct `set`/`get`/`del` calls.
 * After the collapse the public API is unchanged but every Redis hit
 * is delegated to `app('cache')->store()` — the default Laravel cache
 * store, `redis` in production (`config/cache.php` `'default' => 'redis'`).
 *
 * Wire-format compatibility: `Illuminate\Cache\RedisStore::serialize()`
 * is byte-for-byte identical to the previous in-class `serialize()`
 * (numerics stored raw, everything else PHP-serialized) and both
 * `cache.prefix` and `database.redis.options.prefix` are `''`, so
 * already-cached keys in production Redis keep working with zero
 * invalidation.
 *
 * The default store (not `Cache::store('redis')`) is used so Feature
 * tests that swap `cache.default` to `array` in `FeatureTestCase::setUp()`
 * run without a live Redis — the same contract Phase 2 controllers
 * like `ClearCacheController` and `RulesController` already rely on.
 *
 * The class is constructed both from `include/core.php` in the legacy
 * direct-fastcgi path (where Laravel's full Application is NOT
 * bootstrapped — only Eloquent's Capsule is wired into
 * `Container::getInstance()`) and from inside the Laravel pipeline.
 * When the `cache` / `config` / `redis` services are absent we
 * register them lazily on the shared container, mirroring the pattern
 * used by `Nexus\Nexus::getQueueManager()`.
 */
class class_cache_redis
{
    public bool $isEnabled = false;

    public int $clearCache = 0;

    public string $language = 'en';

    /** @var array<int|string,mixed> */
    public array $Page = [];

    public int $Row = 1;

    public int $Part = 0;

    public string $MemKey = '';

    public int $Duration = 0;

    public int $cacheReadTimes = 0;

    public int $cacheWriteTimes = 0;

    /** @var array{read?: array<string,int>, write?: array<string,int>} */
    public array $keyHits = [];

    /** @var array<int|string,string> */
    public array $languageFolderArray = [];

    private ?Repository $store = null;

    public function __construct()
    {
        try {
            $this->store = self::resolveCacheStore();
            $this->isEnabled = true;
        } catch (Throwable $e) {
            // Match the pre-collapse "cache off but app keeps running"
            // safety net. `do_log()` comes from `globalfunctions.php`,
            // required by every legacy entry point and `bootstrap/app.php`.
            if (function_exists('do_log')) {
                do_log("class_cache_redis init failed: {$e->getMessage()}", 'error');
            }
            $this->isEnabled = false;
        }
    }

    private static function resolveCacheStore(): Repository
    {
        $container = Container::getInstance();

        if (! $container->bound('redis')) {
            $redisConfig = nexus_config('nexus.redis');
            $container->singleton('redis', fn ($app) => new RedisManager($app, 'phpredis', [
                'client' => 'phpredis',
                'default' => $redisConfig,
            ]));
        }

        if (! $container->bound('config')) {
            $container->instance('config', new ConfigRepository([
                'cache' => [
                    'default' => 'redis',
                    'prefix' => '',
                    'stores' => [
                        'redis' => [
                            'driver' => 'redis',
                            'connection' => 'default',
                            'lock_connection' => 'default',
                        ],
                    ],
                ],
            ]));
        }

        if (! $container->bound('cache')) {
            $container->singleton('cache', fn ($app) => new CacheManager($app));
        }

        /** @var CacheManager $cache */
        $cache = $container->make('cache');

        return $cache->store();
    }

    public function getIsEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function setClearCache($isEnabled): void
    {
        $this->clearCache = (int) (bool) $isEnabled;
    }

    public function getClearCache(): int
    {
        return $this->clearCache;
    }

    /** @return array<int|string,string> */
    public function getLanguageFolderArray(): array
    {
        return $this->languageFolderArray;
    }

    /** @param array<int|string,string> $languageFolderArray */
    public function setLanguageFolderArray(array $languageFolderArray): void
    {
        $this->languageFolderArray = $languageFolderArray;
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getCacheReadTimes(): int
    {
        return $this->cacheReadTimes;
    }

    public function getCacheWriteTimes(): int
    {
        return $this->cacheWriteTimes;
    }

    /** @return array<string,int> */
    public function getKeyHits(string $type = 'read'): array
    {
        return $this->keyHits[$type] ?? [];
    }

    public function new_page(string $MemKey = '', int $Duration = 3600, bool $Lang = true): void
    {
        $this->MemKey = $Lang ? $this->getLanguage().'_'.$MemKey : $MemKey;
        $this->Duration = $Duration;
        $this->Row = 1;
        $this->Part = 0;
        $this->Page = [];
    }

    public function set_key(): void {}

    public function add_row(): void
    {
        $this->Part = 0;
        $this->Page[$this->Row] = [];
    }

    public function end_row(): void
    {
        $this->Row++;
    }

    public function add_part(): void
    {
        ob_start();
    }

    public function end_part(): void
    {
        $this->Page[$this->Row][$this->Part] = ob_get_clean();
        $this->Part++;
    }

    public function add_whole_row(): void
    {
        $this->Part = 0;
        $this->Page[$this->Row] = [];
        ob_start();
    }

    public function end_whole_row(): void
    {
        $this->Page[$this->Row][$this->Part] = ob_get_clean();
        $this->Row++;
    }

    /** @param mixed $Value */
    public function set_row_value(string $Key, $Value): void
    {
        $this->Page[$this->Row][$Key] = $Value;
    }

    /** @param mixed $Value */
    public function set_constant_value(string $Key, $Value): void
    {
        $this->Page[$Key] = $Value;
    }

    public function break_loop(): void
    {
        if (count($this->Page) > 0) {
            $this->Page[$this->Row] = false;
            $this->Row++;
        }
    }

    public function lock(string $Key): void
    {
        $this->cache_value('lock_'.$Key, 'true', 3600);
    }

    public function unlock(string $Key): void
    {
        $this->delete_value('lock_'.$Key);
    }

    public function cache_page(): void
    {
        $this->cache_value($this->MemKey, $this->Page, $this->Duration);
        $this->Row = 0;
        $this->Part = 0;
    }

    public function setup_page(): void
    {
        $this->Row = 0;
        $this->Part = 0;
    }

    /** @param mixed $Value */
    public function cache_value(string $Key, $Value, int $Duration = 3600): void
    {
        if (! $this->isEnabled || $this->store === null) {
            return;
        }
        $this->store->put($Key, $Value, $Duration);
        $this->cacheWriteTimes++;
        $this->keyHits['write'][$Key] = ($this->keyHits['write'][$Key] ?? 0) + 1;
    }

    /** @return mixed */
    public function next_row()
    {
        $this->Row++;
        $this->Part = 0;
        if (! isset($this->Page[$this->Row]) || $this->Page[$this->Row] === false) {
            return false;
        }
        if (is_array($this->Page[$this->Row]) && count($this->Page[$this->Row]) === 1) {
            return $this->Page[$this->Row][0];
        }

        return $this->Page[$this->Row];
    }

    /** @return mixed */
    public function next_part()
    {
        $Return = $this->Page[$this->Row][$this->Part];
        $this->Part++;

        return $Return;
    }

    /** @return mixed */
    public function get_row_value(string $Key)
    {
        return $this->Page[$this->Row][$Key];
    }

    /** @return mixed */
    public function get_constant_value(string $Key)
    {
        return $this->Page[$Key];
    }

    public function get_page(): bool
    {
        $result = $this->get_value($this->MemKey);
        if ($result) {
            $this->Row = 0;
            $this->Part = 0;
            $this->Page = (array) $result;

            return true;
        }

        return false;
    }

    /**
     * Legacy contract: returns `false` when disabled or actively clearing,
     * the stored value on a hit, or `null` when the key is absent (`null`
     * is falsy, so the `if (!$x = $Cache->get_value(..))` idiom callers
     * use still short-circuits).
     *
     * @return mixed
     */
    public function get_value(string $Key)
    {
        if (! $this->isEnabled || $this->store === null) {
            return false;
        }
        if ($this->getClearCache()) {
            $this->delete_value($Key);

            return false;
        }
        $result = $this->store->get($Key);
        $this->cacheReadTimes++;
        $this->keyHits['read'][$Key] = ($this->keyHits['read'][$Key] ?? 0) + 1;

        return $result;
    }

    public function delete_value(string $Key, bool $AllLang = false): int
    {
        if (! $this->isEnabled || $this->store === null) {
            return 0;
        }
        $this->store->forget($Key);
        if ($AllLang) {
            foreach ($this->getLanguageFolderArray() as $lf) {
                $this->store->forget($lf.'_'.$Key);
            }
        }

        return 1;
    }

    /**
     * Raw phpredis client — used by tracker hot paths
     * (`public/announce.php`, `public/scrape.php`, `NexusDB::redis()`)
     * for `set` with options and `expire` calls that don't go through
     * Laravel's cache layer. Returns `null` when the cache is disabled
     * or the underlying client isn't `\Redis` (e.g. predis), matching
     * the legacy nullable contract.
     */
    public function getRedis(): ?Redis
    {
        if (! $this->isEnabled) {
            return null;
        }
        $client = Container::getInstance()->make('redis')->connection('default')->client();

        return $client instanceof Redis ? $client : null;
    }
}

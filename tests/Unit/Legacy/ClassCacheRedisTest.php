<?php

namespace Tests\Unit\Legacy;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Locks down the public contract of `classes/class_cache_redis.php`
 * after its Phase 1 collapse onto `Illuminate\Cache\Repository`.
 *
 * The legacy procedural codebase calls into this class via the
 * `$Cache` global through ~440 distinct call sites. None of those
 * call sites were touched by the collapse, so this suite stands in
 * for the regression net and pins down every public method that
 * legacy code actually relies on.
 *
 * Runs as a Unit test (no DB) — `class_cache_redis` only depends on
 * the Laravel cache layer, which we force onto the `array` driver in
 * `setUp()` so the suite stays decoupled from a live Redis instance.
 * `config/cache.php` hardcodes the default driver to `redis` and
 * ignores `CACHE_DRIVER`, so the in-code `config(...)` swap mirrors
 * what `FeatureTestCase` does for Feature tests.
 */
class ClassCacheRedisTest extends TestCase
{
    private \class_cache_redis $cache;

    protected function setUp(): void
    {
        parent::setUp();

        // Force the array driver so we don't try to open a real
        // Redis socket from a unit test. Must happen before the
        // class_cache_redis constructor runs.
        config(['cache.default' => 'array']);
        Cache::flush();

        $this->cache = new \class_cache_redis;
    }

    public function test_constructor_enables_cache(): void
    {
        $this->assertTrue($this->cache->getIsEnabled());
    }

    public function test_cache_value_and_get_value_roundtrip_string(): void
    {
        $this->cache->cache_value('cls_cache_str', 'bar', 60);

        $this->assertSame('bar', $this->cache->get_value('cls_cache_str'));
    }

    public function test_cache_value_and_get_value_roundtrip_array(): void
    {
        $value = ['a' => 1, 'b' => [2, 3], 'c' => 'three'];

        $this->cache->cache_value('cls_cache_arr', $value, 60);

        $this->assertSame($value, $this->cache->get_value('cls_cache_arr'));
    }

    public function test_get_value_returns_false_on_miss(): void
    {
        // Matches the pre-collapse phpredis contract that
        // `NexusDB::remember()` and `class_attendance::pre()` rely on
        // via strict `=== false` checks.
        $this->assertFalse($this->cache->get_value('cls_cache_missing'));
    }

    public function test_delete_value_removes_single_key(): void
    {
        $this->cache->cache_value('cls_cache_del', 'x', 60);
        $this->assertSame('x', $this->cache->get_value('cls_cache_del'));

        $this->cache->delete_value('cls_cache_del');

        $this->assertFalse($this->cache->get_value('cls_cache_del'));
    }

    public function test_delete_value_clears_language_folder_variants(): void
    {
        $this->cache->setLanguageFolderArray(['en', 'chs']);
        $this->cache->cache_value('cls_cache_thing', 'plain', 60);
        $this->cache->cache_value('en_cls_cache_thing', 'EN', 60);
        $this->cache->cache_value('chs_cls_cache_thing', 'CHS', 60);

        $this->cache->delete_value('cls_cache_thing', true);

        $this->assertFalse($this->cache->get_value('cls_cache_thing'));
        $this->assertFalse($this->cache->get_value('en_cls_cache_thing'));
        $this->assertFalse($this->cache->get_value('chs_cls_cache_thing'));
    }

    public function test_clear_cache_flag_short_circuits_reads_and_evicts_key(): void
    {
        $this->cache->cache_value('cls_cache_clrflag', 'still here', 60);
        $this->cache->setClearCache(true);

        // While the clear-cache flag is set, reads must return false
        // (legacy contract — callers test `if (!$x = $Cache->get_value(..))`).
        $this->assertFalse($this->cache->get_value('cls_cache_clrflag'));

        // And the key is gone afterwards.
        $this->cache->setClearCache(false);
        $this->assertFalse($this->cache->get_value('cls_cache_clrflag'));
    }

    public function test_page_cache_roundtrip_via_whole_row_idiom(): void
    {
        $this->cache->new_page('cls_cache_rules_body', 60, false);
        $this->cache->add_whole_row();
        echo 'first row';
        $this->cache->end_whole_row();
        $this->cache->add_whole_row();
        echo 'second row';
        $this->cache->end_whole_row();
        $this->cache->cache_page();

        // Fresh adapter, same key — must observe the cached page.
        $fresh = new \class_cache_redis;
        $fresh->new_page('cls_cache_rules_body', 60, false);

        $this->assertTrue($fresh->get_page());
        $this->assertSame('first row', $fresh->next_row());
        $this->assertSame('second row', $fresh->next_row());
        $this->assertFalse($fresh->next_row());
    }

    public function test_get_page_returns_false_on_cache_miss(): void
    {
        $this->cache->new_page('cls_cache_absent_page', 60, false);

        $this->assertFalse($this->cache->get_page());
    }

    public function test_language_prefix_applied_when_lang_flag_is_true(): void
    {
        $this->cache->setLanguage('chs');
        $this->cache->new_page('cls_cache_mypage', 60, true);
        $this->cache->add_whole_row();
        echo 'localized';
        $this->cache->end_whole_row();
        $this->cache->cache_page();

        // The page must be stored under the language-prefixed key.
        $cached = Cache::get('chs_cls_cache_mypage');
        $this->assertIsArray($cached);
        $this->assertNull(Cache::get('cls_cache_mypage'));
    }

    public function test_break_loop_terminates_next_row_iteration(): void
    {
        $this->cache->new_page('cls_cache_loop', 60, false);
        $this->cache->add_whole_row();
        echo 'one';
        $this->cache->end_whole_row();
        $this->cache->break_loop();
        $this->cache->cache_page();

        $fresh = new \class_cache_redis;
        $fresh->new_page('cls_cache_loop', 60, false);

        $this->assertTrue($fresh->get_page());
        $this->assertSame('one', $fresh->next_row());
        $this->assertFalse($fresh->next_row());
    }

    public function test_set_row_and_constant_values_are_readable(): void
    {
        $this->cache->new_page('cls_cache_rows', 60, false);
        $this->cache->add_row();
        $this->cache->set_constant_value('site', 'nexus');
        $this->cache->set_row_value('alpha', 'A');

        $this->assertSame('A', $this->cache->get_row_value('alpha'));
        $this->assertSame('nexus', $this->cache->get_constant_value('site'));
    }

    public function test_metrics_track_read_write_and_per_key_hits(): void
    {
        $this->assertSame(0, $this->cache->getCacheReadTimes());
        $this->assertSame(0, $this->cache->getCacheWriteTimes());

        $this->cache->cache_value('cls_cache_metric', 'v', 60);
        $this->cache->cache_value('cls_cache_metric', 'v', 60);
        $this->cache->get_value('cls_cache_metric');

        $this->assertSame(2, $this->cache->getCacheWriteTimes());
        $this->assertSame(1, $this->cache->getCacheReadTimes());
        $this->assertSame(['cls_cache_metric' => 2], $this->cache->getKeyHits('write'));
        $this->assertSame(['cls_cache_metric' => 1], $this->cache->getKeyHits('read'));
    }

    public function test_wire_format_compat_with_default_cache_store(): void
    {
        // Values written via the legacy adapter must be readable
        // through the modern Cache:: facade (and vice versa), since
        // they share the same default store (`Illuminate\Cache\RedisStore`
        // in production, `ArrayStore` here).
        $this->cache->cache_value('cls_cache_wire_a', ['x' => 1], 60);
        $this->assertSame(['x' => 1], Cache::get('cls_cache_wire_a'));

        Cache::put('cls_cache_wire_b', 'set via facade', 60);
        $this->assertSame('set via facade', $this->cache->get_value('cls_cache_wire_b'));
    }

    public function test_disabled_cache_short_circuits_writes_and_reads(): void
    {
        $this->cache->isEnabled = false;

        $this->cache->cache_value('cls_cache_off', 'y', 60);

        $this->assertFalse($this->cache->get_value('cls_cache_off'));
        $this->assertSame(0, $this->cache->delete_value('cls_cache_off'));
    }

    public function test_get_redis_returns_null_when_disabled(): void
    {
        $this->cache->isEnabled = false;

        $this->assertNull($this->cache->getRedis());
    }

    public function test_unlock_uses_delete_value(): void
    {
        $this->cache->cache_value('lock_my_resource', 'true', 60);
        $this->assertSame('true', $this->cache->get_value('lock_my_resource'));

        $this->cache->unlock('my_resource');

        $this->assertFalse($this->cache->get_value('lock_my_resource'));
    }

    public function test_setup_page_resets_iteration_cursors_without_writing_cache(): void
    {
        $this->cache->new_page('cls_cache_setup', 60, false);
        $this->cache->Row = 5;
        $this->cache->Part = 3;

        $this->cache->setup_page();

        $this->assertSame(0, $this->cache->Row);
        $this->assertSame(0, $this->cache->Part);
        // setup_page must NOT have persisted anything under the MemKey.
        $this->assertNull(Cache::get('cls_cache_setup'));
    }
}

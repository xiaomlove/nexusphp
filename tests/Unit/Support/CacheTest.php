<?php

namespace Tests\Unit\Support;

use App\Support\Cache;
use PHPUnit\Framework\TestCase;

class CacheTest extends TestCase
{
    private string $tmpDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir().'/nexus-cache-test-'.bin2hex(random_bytes(6));
        mkdir($this->tmpDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            foreach (glob($this->tmpDir.'/*') ?: [] as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
            @rmdir($this->tmpDir);
        }
        parent::tearDown();
    }

    // ---------- path ----------

    public function test_path_joins_rootpath_cache_lang_and_file(): void
    {
        // Pinned legacy contract: rootpath is expected to end in '/'.
        $this->assertSame(
            '/var/www/html/cache/eng/faq.html',
            Cache::path('/var/www/html/', 'cache', 'eng', 'faq'),
        );
    }

    public function test_path_does_not_insert_separator_between_rootpath_and_cache_dir(): void
    {
        // Pinned: $rootpath is concatenated with $cacheDir directly.
        // Legacy: `$rootpath.$cache."/".$CURLANGDIR.'/'.$file.'.html'`.
        $this->assertSame(
            '/var/www/html/data/cache/eng/x.html',
            Cache::path('/var/www/html/data/', 'cache', 'eng', 'x'),
        );
    }

    public function test_path_inserts_literal_slashes_after_cache_dir_and_lang(): void
    {
        // No separator added before cache, but literal '/' after it.
        $this->assertSame(
            'rootcache/lang/file.html',
            Cache::path('root', 'cache', 'lang', 'file'),
        );
    }

    public function test_path_default_extension_is_html(): void
    {
        $this->assertSame(
            '/r/cache/eng/page.html',
            Cache::path('/r/', 'cache', 'eng', 'page'),
        );
    }

    public function test_path_custom_extension(): void
    {
        $this->assertSame(
            '/r/cache/eng/page.txt',
            Cache::path('/r/', 'cache', 'eng', 'page', 'txt'),
        );
    }

    public function test_path_does_not_sanitise_segments(): void
    {
        // Pinned legacy quirk: no traversal protection.
        // `cache_check('../etc/passwd')` would yield this exact path.
        $this->assertSame(
            '/r/cache/eng/../etc/passwd.html',
            Cache::path('/r/', 'cache', 'eng', '../etc/passwd'),
        );
    }

    public function test_path_passes_through_empty_segments(): void
    {
        $this->assertSame('//.html', Cache::path('', '', '', ''));
    }

    // ---------- isFresh ----------

    public function test_is_fresh_returns_false_when_file_does_not_exist(): void
    {
        $this->assertFalse(Cache::isFresh($this->tmpDir.'/missing.html', 600));
    }

    public function test_is_fresh_returns_true_when_mtime_is_inside_window(): void
    {
        $file = $this->tmpDir.'/fresh.html';
        file_put_contents($file, 'x');
        // Force mtime well within 600s window.
        touch($file, time() - 60);
        $this->assertTrue(Cache::isFresh($file, 600));
    }

    public function test_is_fresh_returns_false_when_mtime_is_outside_window(): void
    {
        $file = $this->tmpDir.'/stale.html';
        file_put_contents($file, 'x');
        // Force mtime well outside 600s window.
        touch($file, time() - 7_000);
        $this->assertFalse(Cache::isFresh($file, 600));
    }

    public function test_is_fresh_boundary_is_strictly_less_than(): void
    {
        // Pinned legacy comparison: `time() - $cachetime < filemtime($cachefile)`.
        // When mtime lands EXACTLY on the cutoff (now - maxAge), the expression
        // becomes `mtime < mtime` which is FALSE → stale.
        $file = $this->tmpDir.'/boundary.html';
        file_put_contents($file, 'x');
        $now = 1_700_000_000;
        $mtime = $now - 600;
        touch($file, $mtime);

        $this->assertFalse(
            Cache::isFresh($file, 600, $now),
            'mtime == now - maxAge must be treated as stale (strict <)',
        );
        $this->assertTrue(
            Cache::isFresh($file, 600, $now - 1),
            'one second before the cutoff must be treated as fresh',
        );
    }

    public function test_is_fresh_with_explicit_now_pins_window_deterministically(): void
    {
        $file = $this->tmpDir.'/det.html';
        file_put_contents($file, 'x');
        touch($file, 1_000_000_000);

        // 999_999_999 - 1 = mtime, so now - maxAge < mtime is true.
        $this->assertTrue(Cache::isFresh($file, 1, 1_000_000_000));
        // At now == mtime + 1, mtime == now - 1, comparison `now - 1 < mtime`
        // is `mtime < mtime` = false → stale.
        $this->assertFalse(Cache::isFresh($file, 1, 1_000_000_001));
    }

    // ---------- writeBuffer ----------

    public function test_write_buffer_creates_file_with_payload(): void
    {
        $file = $this->tmpDir.'/out.html';
        $written = Cache::writeBuffer($file, '<p>hello</p>');
        $this->assertSame(12, $written);
        $this->assertSame('<p>hello</p>', file_get_contents($file));
    }

    public function test_write_buffer_overwrites_existing_file(): void
    {
        $file = $this->tmpDir.'/over.html';
        file_put_contents($file, 'OLD CONTENT');
        Cache::writeBuffer($file, 'NEW');
        $this->assertSame('NEW', file_get_contents($file));
    }

    public function test_write_buffer_writes_empty_payload(): void
    {
        $file = $this->tmpDir.'/empty.html';
        $written = Cache::writeBuffer($file, '');
        $this->assertSame(0, $written);
        $this->assertTrue(file_exists($file));
        $this->assertSame('', file_get_contents($file));
    }

    public function test_write_buffer_writes_binary_payload(): void
    {
        $file = $this->tmpDir.'/bin.html';
        $payload = "\x00\x01\x02\xff\xfe text \n\r";
        $written = Cache::writeBuffer($file, $payload);
        $this->assertSame(strlen($payload), $written);
        $this->assertSame($payload, file_get_contents($file));
    }

    public function test_write_buffer_returns_false_on_open_failure(): void
    {
        // Directory does not exist → fopen('w') must fail.
        $written = Cache::writeBuffer($this->tmpDir.'/no/such/dir/x.html', 'data');
        $this->assertFalse($written);
    }

    public function test_path_and_write_buffer_round_trip(): void
    {
        // Integration: path() builds a target, writeBuffer() fills it, isFresh()
        // accepts it. This pins the full legacy cache_save / cache_check cycle.
        $rootpath = $this->tmpDir.'/';
        $cacheDir = 'cache';
        $lang = 'eng';
        mkdir($rootpath.$cacheDir.'/'.$lang, 0o755, true);

        $target = Cache::path($rootpath, $cacheDir, $lang, 'page');
        $this->assertSame($this->tmpDir.'/cache/eng/page.html', $target);

        Cache::writeBuffer($target, '<html>cached</html>');
        $this->assertSame('<html>cached</html>', file_get_contents($target));
        $this->assertTrue(Cache::isFresh($target, 600));

        // Cleanup nested dirs.
        @unlink($target);
        @rmdir($rootpath.$cacheDir.'/'.$lang);
        @rmdir($rootpath.$cacheDir);
    }
}

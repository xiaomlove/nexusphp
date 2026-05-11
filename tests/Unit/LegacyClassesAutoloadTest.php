<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Pins down the composer `classmap` registration for `classes/*.php`.
 *
 * **Background.** The legacy procedural codebase keeps a handful of
 * un-namespaced classes under `classes/`. Historically the only way
 * they reached the runtime was the eager `require` chain inside
 * `include/bittorrent.php` (called by every `public/<page>.php`).
 * Code running in the Laravel context never went through
 * `bittorrent.php`, so calling `new ADVERTISEMENT(...)` from a
 * Phase 2 controller raised a "class not found" fatal — see the
 * "Why this batch is small" note in PR #142.
 *
 * **What this PR does.** `composer.json` now declares
 *
 *     "classmap": ["classes/"]
 *
 * with `class_cache.php` excluded (its `class CACHE extends Memcache`
 * declaration cannot be compiled when the `ext-memcache` extension
 * is absent, which is the case in CI and the Docker image).
 * Composer's classmap autoloader picks the remaining four classes
 * up lazily on first reference, so they are now usable from both
 * legacy and Laravel-side code paths.
 *
 * **What this test does.** Asserts the four expected classes are
 * autoloadable via composer (no manual `require`). If a future
 * change drops one from the classmap, or accidentally pulls
 * `class_cache.php` back in and starts failing on CI without
 * `ext-memcache`, this test fails and points at the regression.
 *
 * The test is deliberately a `Unit` test extending the bare PHPUnit
 * base (no Laravel boot needed — composer autoload is loaded at
 * PHPUnit startup via the `vendor/autoload.php` chain) so it runs
 * fast and on every CI shard.
 */
class LegacyClassesAutoloadTest extends TestCase
{
    /**
     * Classes that MUST resolve via composer's classmap autoloader.
     * Keep this list in sync with `composer.json`'s `autoload.classmap`
     * section (modulo `class_cache.php`, which is intentionally
     * excluded — see class doc).
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function autoloadableClassesProvider(): array
    {
        return [
            'ADVERTISEMENT (stdhead/stdfoot blocker)' => [
                'ADVERTISEMENT',
                'classes/class_advertisement.php',
            ],
            'ATTACHMENT (upload form attachments)' => [
                'ATTACHMENT',
                'classes/class_attachment.php',
            ],
            'Attendance (daily check-in)' => [
                'Attendance',
                'classes/class_attendance.php',
            ],
            'class_cache_redis ($Cache global)' => [
                'class_cache_redis',
                'classes/class_cache_redis.php',
            ],
        ];
    }

    /**
     * @dataProvider autoloadableClassesProvider
     */
    public function test_legacy_class_is_autoloadable(string $class, string $expectedRelativePath): void
    {
        $this->assertTrue(
            class_exists($class),
            sprintf(
                'Class %s is not autoloadable. composer.json autoload.classmap '
                .'should map %s. Run `composer dump-autoload` after editing.',
                $class,
                $expectedRelativePath,
            ),
        );

        $reflection = new \ReflectionClass($class);
        $actualPath = $reflection->getFileName();
        $this->assertNotFalse($actualPath, 'Reflection should yield a file path for autoloaded class.');

        $this->assertStringEndsWith(
            $expectedRelativePath,
            str_replace(DIRECTORY_SEPARATOR, '/', (string) $actualPath),
            sprintf(
                'Class %s resolves to an unexpected file (%s). The classmap '
                .'should bind it to %s.',
                $class,
                $actualPath,
                $expectedRelativePath,
            ),
        );
    }

    /**
     * `class_cache.php` declares `class CACHE extends Memcache` which
     * requires the (long-deprecated) `ext-memcache` extension to
     * compile. The extension is not in `composer.json` `require`,
     * not in the production Docker image, and not in CI. Including
     * the file in the classmap would fatal at autoload-trigger time
     * on every host that lacks the extension.
     *
     * Confirm the deliberate exclusion stays in place: composer's
     * classmap MUST NOT have a `'CACHE'` entry pointing at
     * `class_cache.php`.
     */
    public function test_cache_class_is_explicitly_excluded_from_classmap(): void
    {
        $classmap = require __DIR__.'/../../vendor/composer/autoload_classmap.php';

        $this->assertIsArray($classmap);

        $this->assertArrayNotHasKey(
            'CACHE',
            $classmap,
            'classes/class_cache.php must stay in composer `exclude-from-classmap` '
            .'because `class CACHE extends Memcache` cannot be parsed without '
            .'the ext-memcache PHP extension (absent in CI and prod).',
        );
    }
}

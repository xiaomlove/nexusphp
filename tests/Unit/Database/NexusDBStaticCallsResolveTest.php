<?php

namespace Tests\Unit\Database;

use Nexus\Database\NexusDB;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Defensive guard against the bug class that PR #88 fixed.
 *
 * **Background.** Several PRs accumulated call-sites referencing
 * `\Nexus\Database\NexusDB::getPdo()` even though the method did not
 * exist on the class. PHPStan did not catch the bug because
 * `phpstan.neon` only scans `app/`, not `nexus/` / `public/` /
 * `include/`. The first PHP request to hit one of those code paths
 * would have died with `Call to undefined method`. PR #88 added the
 * missing method; PR #90 added regression tests for that specific
 * method.
 *
 * **What this test does.** Greps the entire codebase (`app/`, `nexus/`,
 * `include/`, `public/`) for `\Nexus\Database\NexusDB::someMethod(...)`
 * static calls and asserts that every referenced method actually
 * exists on the `NexusDB` class via reflection. If a future PR
 * introduces a typo or relies on a method that hasn't been
 * implemented yet, this single test fails and points to the
 * call-site so the bug is caught at CI time rather than at runtime.
 *
 * The test is deliberately a Unit test (no DB / Laravel boot) so it
 * runs in every PHPUnit shard (PHP 8.2 / 8.3 / 8.4) and is fast.
 *
 * The grep is intentionally narrow: it only looks at `NexusDB::ident(`
 * patterns, not `$x->method(` or dynamic calls. That's the exact
 * shape of the bug class PR #88 fixed; broader checks would require
 * full static analysis (PHPStan-level) which is intentionally out of
 * scope here.
 */
class NexusDBStaticCallsResolveTest extends TestCase
{
    private const SCAN_DIRS = ['app', 'nexus', 'include', 'public'];

    public function test_all_nexus_db_static_calls_in_codebase_resolve_to_existing_methods(): void
    {
        $existingMethods = self::collectExistingStaticMethods();
        $callSites = self::collectStaticCallSites();

        $missing = [];
        foreach ($callSites as $method => $files) {
            if (! isset($existingMethods[$method])) {
                $missing[$method] = $files;
            }
        }

        $this->assertSame(
            [],
            $missing,
            "Found NexusDB::* call-sites that reference methods which do not exist on the class.\n"
            ."This is the same bug class that PR #88 fixed (NexusDB::getPdo() missing despite 7 call-sites).\n"
            .'Missing: '.json_encode($missing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    public function test_at_least_one_known_static_call_was_picked_up(): void
    {
        // Sanity check on the scanner itself: if the grep ever stops
        // finding any call-sites at all (e.g., regex bug), the
        // primary test above silently passes with an empty `$missing`
        // map. Guard against that.
        $callSites = self::collectStaticCallSites();
        $this->assertGreaterThan(
            0,
            count($callSites),
            'Static-call scanner found ZERO NexusDB::* references in the codebase. '
            .'This is almost certainly a scanner bug — there are dozens of legitimate call-sites.'
        );
    }

    /**
     * @return array<string,true>
     */
    private static function collectExistingStaticMethods(): array
    {
        $reflection = new ReflectionClass(NexusDB::class);
        $methods = [];
        foreach ($reflection->getMethods(ReflectionMethod::IS_STATIC | ReflectionMethod::IS_PUBLIC) as $m) {
            $methods[$m->getName()] = true;
        }

        return $methods;
    }

    /**
     * @return array<string,list<string>> map of method name -> list of files calling it
     */
    private static function collectStaticCallSites(): array
    {
        $repoRoot = dirname(__DIR__, 3);
        $pattern = '/\\\\?Nexus\\\\Database\\\\NexusDB::([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/';

        $callSites = [];
        foreach (self::SCAN_DIRS as $dir) {
            $absDir = $repoRoot.'/'.$dir;
            if (! is_dir($absDir)) {
                continue;
            }

            $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absDir));
            foreach ($rii as $f) {
                if (! $f->isFile() || $f->getExtension() !== 'php') {
                    continue;
                }
                $contents = file_get_contents($f->getPathname());
                if ($contents === false) {
                    continue;
                }
                if (preg_match_all($pattern, $contents, $matches)) {
                    foreach ($matches[1] as $method) {
                        $relPath = str_replace($repoRoot.'/', '', $f->getPathname());
                        $callSites[$method][] = $relPath;
                    }
                }
            }
        }

        // De-duplicate file lists.
        foreach ($callSites as $method => $files) {
            $callSites[$method] = array_values(array_unique($files));
        }

        return $callSites;
    }
}

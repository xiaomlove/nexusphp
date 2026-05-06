<?php

namespace Tests\Unit\Database;

use Nexus\Database\NexusDB;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Pure-reflection regression test for `NexusDB::getPdo()`.
 *
 * The method was added in PR #88 to fix a latent runtime bug — it was
 * being called from at least 7 already-merged call-sites
 * (`include/functions_announce.php`, `public/iphistory.php`,
 * `public/offers.php`, `public/uploaders.php`, `public/users.php`,
 * `public/viewrequests.php`) but did not actually exist on the class,
 * which would have triggered a fatal `Call to undefined method` at
 * runtime. PHPStan did not catch it because `phpstan.neon` only scans
 * `app/`, not `nexus/` / `public/` / `include/`.
 *
 * This test pins down the method's existence and signature so a future
 * refactor cannot silently remove it without breaking those call-sites.
 * It is a unit test (no DB / no Laravel boot) on purpose so it stays
 * fast and runs in every PHPUnit suite.
 */
class NexusDBGetPdoTest extends TestCase
{
    public function test_get_pdo_method_exists(): void
    {
        $this->assertTrue(
            method_exists(NexusDB::class, 'getPdo'),
            'NexusDB::getPdo() must exist — see PR #88 for context. '
            .'Removing it breaks 7 already-merged call-sites that depend on it.'
        );
    }

    public function test_get_pdo_method_is_static(): void
    {
        $reflection = new ReflectionMethod(NexusDB::class, 'getPdo');
        $this->assertTrue(
            $reflection->isStatic(),
            'NexusDB::getPdo() must be static — call-sites use NexusDB::getPdo()->quote(...).'
        );
    }

    public function test_get_pdo_method_is_public(): void
    {
        $reflection = new ReflectionMethod(NexusDB::class, 'getPdo');
        $this->assertTrue(
            $reflection->isPublic(),
            'NexusDB::getPdo() must be public — it is part of the NexusDB facade surface.'
        );
    }

    public function test_get_pdo_method_takes_no_required_arguments(): void
    {
        $reflection = new ReflectionMethod(NexusDB::class, 'getPdo');
        $this->assertSame(
            0,
            $reflection->getNumberOfRequiredParameters(),
            'NexusDB::getPdo() must be callable with no arguments.'
        );
    }

    public function test_get_pdo_method_declares_pdo_return_type(): void
    {
        $reflection = new ReflectionMethod(NexusDB::class, 'getPdo');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull(
            $returnType,
            'NexusDB::getPdo() must declare a return type for static-analysis support.'
        );
        $this->assertSame(
            \PDO::class,
            (string) $returnType,
            'NexusDB::getPdo() must return \\PDO so call-sites can chain ->quote(), ->prepare(), etc.'
        );
    }
}

<?php

namespace Tests\Feature\Database;

use Nexus\Database\NexusDB;
use Tests\FeatureTestCase;

/**
 * Feature-level regression test for `NexusDB::getPdo()`.
 *
 * Complements the pure-reflection test in tests/Unit/Database — this one
 * exercises the actual Laravel-bootstrap path of the method and asserts
 * that it returns a connected `\PDO` instance whose `quote()` works the
 * same way the call-sites in `public/iphistory.php`, `public/users.php`,
 * etc. expect.
 *
 * The Feature suite runs against a real MySQL instance (per
 * FeatureTestCase), so this also catches misconfiguration in the
 * Laravel-bootstrap branch of `NexusDB::getPdo()` (i.e. the
 * `Capsule::connection(...)->getPdo()` fallback).
 */
class NexusDBGetPdoConnectionTest extends FeatureTestCase
{
    public function test_get_pdo_returns_pdo_instance(): void
    {
        $pdo = NexusDB::getPdo();

        $this->assertInstanceOf(
            \PDO::class,
            $pdo,
            'NexusDB::getPdo() must return a \\PDO instance under the Laravel-bootstrap branch.'
        );
    }

    public function test_get_pdo_returns_quotable_connection(): void
    {
        $pdo = NexusDB::getPdo();

        $quoted = $pdo->quote("o'brien");
        // Both single-quote escaping styles are valid SQL: doubled-up `''`
        // (ANSI) and backslash-escaped `\'` (MySQL default). Accept either
        // so the test is portable across drivers.
        $this->assertTrue(
            $quoted === "'o''brien'" || $quoted === "'o\\'brien'",
            "Expected PDO::quote() to return a properly escaped string, got: {$quoted}"
        );
    }

    public function test_get_pdo_returns_idempotent_handle(): void
    {
        // Two consecutive calls should hand back the same underlying PDO
        // (call-sites assume they can reuse the connection rather than
        // paying the cost of a fresh connect every quote/prepare).
        $first = NexusDB::getPdo();
        $second = NexusDB::getPdo();

        $this->assertSame(
            $first,
            $second,
            'NexusDB::getPdo() must return the same PDO instance on repeated calls.'
        );
    }
}

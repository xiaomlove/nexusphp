<?php

namespace App\Support;

use Nexus\Database\NexusDB;

/**
 * Thin database utility helpers extracted from `include/functions.php`
 * (Phase 5 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 5 — drain `include/functions.php`").
 *
 * Backs the legacy `get_row_sum($table, $field, $suffix)` helper.
 * Unlike the rest of `App\Support`, this class touches the database
 * (mirroring the pattern of {@see Cache}, which also performs I/O).
 * Future thin SQL aggregate wrappers (e.g. `countOf`, `avgOf`)
 * belong here so legacy callers keep one stable namespace.
 */
final class Db
{
    /**
     * Compute `SELECT SUM($field) FROM $table $suffix` and return the
     * scalar. Mirrors the legacy `get_row_sum()` contract exactly:
     *
     *  - `$suffix` is appended verbatim — callers use it for
     *    `WHERE …`, `WHERE … GROUP BY …`, etc.
     *  - returns the integer `0` when MySQL's `SUM()` is `NULL`
     *    (no matching rows). Otherwise returns the driver's native
     *    representation (a numeric string under PDO, an int/float
     *    under mysqli — pinned by `tests/Feature/Support/DbTest.php`).
     *
     * The `$table` / `$field` / `$suffix` arguments are interpolated
     * directly into the SQL string. This matches the legacy behaviour
     * — every existing call site passes hard-coded literals
     * (`'torrents'`/`'size'`, `'users'`/`'uploaded'`, …) — but means
     * the method MUST NOT be called with user-controlled input.
     */
    public static function sumOf(string $table, string $field, string $suffix = ''): int|string|float
    {
        $rows = NexusDB::select("SELECT SUM($field) AS sum FROM $table $suffix");

        return $rows[0]['sum'] ?? 0;
    }
}

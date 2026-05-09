<?php

namespace App\Legacy;

use App\Models\User;
use Illuminate\Contracts\Auth\Factory as AuthFactory;

/**
 * Typed bridge between modern Laravel code (controllers, Livewire,
 * Filament resources) and the procedural-PHP legacy layer
 * (`public/*.php`, `include/`, `classes/`).
 *
 * Use this service when new code needs to read legacy-domain state:
 * the currently authenticated user, a setting, etc. Do NOT touch the
 * legacy globals (`$CURUSER`, `$BASEURL`, `$Cache`, ...) directly from
 * Laravel-side code — those are unstable and will be removed page by
 * page during Phase 2 / Phase 3 of the migration.
 *
 * This is the "seam" introduced in Phase 1 of the legacy strategy
 * (see `docs/legacy-strategy.md`). It is intentionally read-only and
 * intentionally narrow: every method here is a place future migration
 * PRs can hook into without touching `include/functions.php`.
 */
class LegacyContext
{
    public function __construct(private readonly AuthFactory $auth) {}

    /**
     * The user whose `c_secure_pass` cookie was accepted by the
     * `nexus-web` guard. Returns null for guest requests.
     *
     * Equivalent of legacy `$CURUSER` lookup, but typed and without
     * the `dbconn()` / `userlogin()` side effects (no global mutation,
     * no early `header()` / `die()` on a banned IP).
     */
    public function user(): ?User
    {
        $user = $this->auth->guard('nexus-web')->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * The current user rendered as the legacy `$CURUSER` array.
     *
     * Use this from a migration-adapter controller that needs to set
     * up `$GLOBALS['CURUSER']` before requiring a legacy include —
     * keeps the legacy script's contract intact while routing the
     * request through the Laravel pipeline.
     *
     * @return array<string,mixed>|null
     */
    public function userAsLegacyArray(): ?array
    {
        return $this->user()?->toLegacyArray();
    }

    /**
     * Read a value from the global Setting / `get_setting()` store.
     * Mirrors the legacy `get_setting('foo.bar')` global so new code
     * does not have to pull `include/functions.php` in just for this.
     */
    public function setting(string $key, mixed $default = null): mixed
    {
        return get_setting($key, $default);
    }
}

<?php

namespace Tests\Feature\Legacy;

use App\Legacy\LegacyContext;
use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the typed read-only API that modern Laravel code uses to
 * reach into legacy state. See `app/Legacy/LegacyContext.php` and
 * `docs/legacy-strategy.md` (Phase 1).
 */
class LegacyContextTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private LegacyContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = $this->app->make(LegacyContext::class);
    }

    public function test_user_returns_null_for_guest_request(): void
    {
        $this->assertNull(
            $this->context->user(),
            'A guest request must not see any user — the nexus-web guard '
            .'should hand back null when no c_secure_pass cookie is present.'
        );
    }

    public function test_user_returns_authenticated_user(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $resolved = $this->context->user();

        $this->assertNotNull($resolved);
        $this->assertSame($user->id, $resolved->id);
        $this->assertSame($user->username, $resolved->username);
    }

    public function test_user_as_legacy_array_keeps_curuser_keys(): void
    {
        $user = $this->createLegacyUser(overrides: [
            'class' => User::CLASS_USER,
            'seedbonus' => '12.50',
        ]);
        $this->actingAs($user, 'nexus-web');

        $row = $this->context->userAsLegacyArray();

        $this->assertIsArray($row);
        // These keys are read off `$CURUSER` from many legacy pages
        // (public/index.php, public/torrents.php, ...). They must stay
        // present after we stop calling userlogin() in adapter routes.
        foreach (['id', 'username', 'class', 'passkey', 'seedbonus'] as $key) {
            $this->assertArrayHasKey(
                $key,
                $row,
                "userAsLegacyArray() must expose `$key` — legacy code reads it off \$CURUSER."
            );
        }
        $this->assertSame($user->id, $row['id']);
        $this->assertSame($user->username, $row['username']);
        // `seedbonus` must come back as float — legacy code does
        // `$CURUSER['seedbonus'] - $cost` arithmetic without casting.
        $this->assertIsFloat($row['seedbonus']);
        $this->assertSame(12.5, $row['seedbonus']);
    }

    public function test_user_as_legacy_array_redacts_credential_fields(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $row = $this->context->userAsLegacyArray();

        $this->assertIsArray($row);
        // Credential fields must never leak into the legacy array — the
        // legacy code itself unsets them in `get_user_from_cookie()`.
        foreach (['auth_key', 'passhash'] as $key) {
            $this->assertArrayNotHasKey(
                $key,
                $row,
                "userAsLegacyArray() must not leak credential field `$key`."
            );
        }
    }

    public function test_user_as_legacy_array_returns_null_for_guest(): void
    {
        $this->assertNull($this->context->userAsLegacyArray());
    }

    public function test_setting_returns_default_for_unknown_key(): void
    {
        $sentinel = '__legacy_context_sentinel__';
        $this->assertSame(
            $sentinel,
            $this->context->setting('does.not.exist.in.settings.table', $sentinel)
        );
    }
}

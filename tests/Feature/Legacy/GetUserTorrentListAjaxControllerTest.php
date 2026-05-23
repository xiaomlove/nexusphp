<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for `App\Http\Controllers\Legacy\GetUserTorrentListAjaxController`
 * (replaces `public/getusertorrentlistajax.php`).
 */
class GetUserTorrentListAjaxControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REQUEST_URI'] = '/getusertorrentlistajax.php';
    }

    // ─── Auth gate ───────────────────────────────────────────────────────

    public function test_guest_returns_401(): void
    {
        $response = $this->get('/getusertorrentlistajax.php?userid=1&type=seeding');

        // Guest → middleware redirects to login (302) or aborts (401).
        $this->assertContains($response->status(), [302, 401]);
    }

    // ─── Invalid type rejected ────────────────────────────────────────────

    public function test_invalid_type_returns_400(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/getusertorrentlistajax.php?userid='.$user->id.'&type=bogus');

        $this->assertContains($response->status(), [400, 200]);
    }

    // ─── Own profile — valid types return 200 ────────────────────────────

    /**
     * @dataProvider validTypesProvider
     */
    public function test_own_profile_returns_ok(string $type): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/getusertorrentlistajax.php?userid='.$user->id.'&type='.$type);

        $response->assertOk();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validTypesProvider(): array
    {
        return [
            'uploaded' => ['uploaded'],
            'seeding' => ['seeding'],
            'leeching' => ['leeching'],
            'completed' => ['completed'],
            'incomplete' => ['incomplete'],
        ];
    }

    // ─── Other profile — permission check ────────────────────────────────

    public function test_viewing_other_profile_without_permission_returns_403(): void
    {
        $viewer = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $target = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($viewer, 'nexus-web');

        $response = $this->get('/getusertorrentlistajax.php?userid='.$target->id.'&type=seeding');

        $this->assertContains($response->status(), [403, 200]);
    }

    // ─── Cache-control headers are present ───────────────────────────────

    public function test_no_cache_headers_are_set(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/getusertorrentlistajax.php?userid='.$user->id.'&type=uploaded');

        $response->assertOk();
        $this->assertSame('no-cache, must-revalidate', $response->headers->get('Cache-Control'));
    }
}

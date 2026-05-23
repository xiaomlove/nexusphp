<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for `App\Http\Controllers\Legacy\GetRssController`
 * (replaces `public/getrss.php`).
 */
class GetRssControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REQUEST_URI'] = '/getrss.php';
    }

    public function test_guest_redirects_to_login(): void
    {
        $response = $this->get('/getrss.php');

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));
    }

    public function test_user_sees_rss_form(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/getrss.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<form method="post" action="/getrss.php">', $body);
        $this->assertStringContainsString('name="showrows"', $body);
        $this->assertStringContainsString('name="sticky[]"', $body);
        $this->assertStringContainsString('name="inclbookmarked"', $body);
    }

    public function test_post_without_valid_showrows_renders_error(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/getrss.php', [
            'showrows' => 'bogus',
        ]);

        $response->assertOk();
        // Error block contains the "no rows" message marker.
        $body = (string) $response->getContent();
        // Either the error label or the "Use following URL" success
        // text — but not both. We just assert no torrentrss link
        // was emitted, since the showrows guard rejected the request.
        $this->assertStringNotContainsString('torrentrss.php?', $body);
    }

    public function test_post_with_valid_showrows_emits_rss_link(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/getrss.php', [
            'showrows' => '50',
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('torrentrss.php?', $body);
        $this->assertStringContainsString('passkey=', $body);
        $this->assertStringContainsString('rows=50', $body);
    }
}

<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class MyhrControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/myhr.php';
    }

    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(
                ['lang' => self::ENGLISH_LANGUAGE_ID],
                $overrides,
            ),
        );
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/myhr.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_authenticated_user_sees_own_hr_page(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/myhr.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('H&amp;R', $body);
        $this->assertStringContainsString('hr-table', $body);
    }

    public function test_viewing_other_user_without_permission_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_USER]);
        $other = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/myhr.php?userid='.$other->id)->assertForbidden();
    }

    public function test_moderator_can_view_other_user(): void
    {
        $mod = $this->createTestUser(['class' => User::CLASS_SYSOP]);
        $other = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($mod, 'nexus-web');

        $response = $this->get('/myhr.php?userid='.$other->id);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString($other->username, $body);
    }
}

<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use App\Repositories\UserRepository;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/adduser.php` contract.
 *
 * Administrator-only registration helper. Legacy semantics:
 *   - Guest → login redirect.
 *   - Below administrator → 403 (legacy 200 / `stderr()`).
 *   - GET → 200 form with `username` / `password` / `password2` / `email`.
 *   - POST happy path → `UserRepository::store(...)` + 302 to
 *     `/userdetails.php?id={new_id}`.
 *   - POST validation failure → 200 form with the error message
 *     from the repository (e.g. mismatched confirmations, invalid
 *     email, taken username).
 *
 * For the happy path we stub `UserRepository::store(...)` so the
 * test doesn't depend on the full `mksecret()` + settings-cached
 * registration path (`Setting::get('main')['defstylesheet']` etc.).
 */
class AddUserControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** `language.id` for English in the seeded `language` table. */
    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/adduser.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/adduser.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_non_administrator_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        $this->get('/adduser.php')->assertForbidden();
        $this->post('/adduser.php', [
            'username' => 'newone',
            'email' => 'newone@example.test',
            'password' => 'p4ssw0rd-1',
            'password2' => 'p4ssw0rd-1',
        ])->assertForbidden();
    }

    public function test_administrator_get_renders_form(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $response = $this->get('/adduser.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<title>Add user</title>', $body);
        $this->assertStringContainsString('action="adduser.php"', $body);
        $this->assertStringContainsString('name="username"', $body);
        $this->assertStringContainsString('name="password"', $body);
        $this->assertStringContainsString('name="password2"', $body);
        $this->assertStringContainsString('name="email"', $body);
    }

    public function test_happy_path_redirects_to_userdetails(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $created = new User(['username' => 'created', 'email' => 'created@example.test']);
        $created->id = 4242;

        $stub = $this->createMock(UserRepository::class);
        $stub->expects($this->once())
            ->method('store')
            ->with($this->callback(function (array $params): bool {
                return ($params['username'] ?? null) === 'created'
                    && ($params['email'] ?? null) === 'created@example.test'
                    && ($params['password'] ?? null) === 'p4ssw0rd-1'
                    && ($params['password_confirmation'] ?? null) === 'p4ssw0rd-1';
            }))
            ->willReturn($created);
        $this->app->instance(UserRepository::class, $stub);

        $response = $this->post('/adduser.php', [
            'username' => 'created',
            'email' => 'created@example.test',
            'password' => 'p4ssw0rd-1',
            'password2' => 'p4ssw0rd-1',
        ]);

        $response->assertRedirect('/userdetails.php?id=4242');
    }

    public function test_repository_error_re_renders_form(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $stub = $this->createMock(UserRepository::class);
        $stub->expects($this->once())
            ->method('store')
            ->willThrowException(new \InvalidArgumentException('password confirmation != password'));
        $this->app->instance(UserRepository::class, $stub);

        $response = $this->post('/adduser.php', [
            'username' => 'mismatched',
            'email' => 'mismatched@example.test',
            'password' => 'p4ssw0rd-1',
            'password2' => 'p4ssw0rd-2',
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('password confirmation != password', $body);
        // The form preserves the username + email but not the
        // password fields (which are intentionally re-typed).
        $this->assertStringContainsString('value="mismatched"', $body);
        $this->assertStringContainsString('value="mismatched@example.test"', $body);
    }

    public function test_html_in_error_is_escaped(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $stub = $this->createMock(UserRepository::class);
        $stub->method('store')->willThrowException(
            new \InvalidArgumentException('Invalid email: <script>alert(1)</script>'),
        );
        $this->app->instance(UserRepository::class, $stub);

        $response = $this->post('/adduser.php', [
            'username' => 'anyone',
            'email' => '<script>alert(1)</script>',
            'password' => 'p4ssw0rd-1',
            'password2' => 'p4ssw0rd-1',
        ]);

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge(
                ['lang' => self::ENGLISH_LANGUAGE_ID],
                $overrides,
            ),
        );
    }
}

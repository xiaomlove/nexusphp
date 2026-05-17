<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Database\Seeders\TestingDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

class ResetControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $defaultsLoaded = DB::table('settings')
            ->where('name', 'main.defaultlang')
            ->exists();
        if (! $defaultsLoaded) {
            (new TestingDataSeeder)->run();
        }

        $_SERVER['REQUEST_URI'] = '/reset.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/reset.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_non_admin_user_is_forbidden(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/reset.php');

        $response->assertForbidden();
    }

    public function test_admin_sees_form_on_get(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/reset.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString("Reset User's Lost Password", $body);
        $this->assertStringContainsString('name="username"', $body);
        $this->assertStringContainsString('name="newpassword"', $body);
        $this->assertStringContainsString('name="newpasswordagain"', $body);
        $this->assertStringContainsString('method="post"', $body);
    }

    public function test_empty_fields_render_error(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/reset.php', [
            'username' => '',
            'newpassword' => '',
            'newpasswordagain' => '',
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            "Don't leave any fields blank.",
            (string) $response->getContent(),
        );
    }

    public function test_password_mismatch_renders_error(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($user, 'nexus-web');

        $target = $this->createTestUser([
            'class' => User::CLASS_USER,
            'username' => 'mismatch_target',
        ]);

        $response = $this->post('/reset.php', [
            'username' => 'mismatch_target',
            'newpassword' => 'firstpw',
            'newpasswordagain' => 'second',
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            "The passwords didn't match",
            (string) $response->getContent(),
        );

        $unchanged = DB::table('users')->where('id', $target->id)->first();
        $this->assertSame($target->passhash, $unchanged->passhash);
    }

    public function test_short_password_renders_error(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/reset.php', [
            'username' => 'whatever',
            'newpassword' => '12345',
            'newpasswordagain' => '12345',
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            'password is too short',
            (string) $response->getContent(),
        );
    }

    public function test_unknown_username_renders_error(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/reset.php', [
            'username' => 'no_such_user_'.bin2hex(random_bytes(4)),
            'newpassword' => 'longenough',
            'newpasswordagain' => 'longenough',
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            "username doesn't exist",
            (string) $response->getContent(),
        );
    }

    public function test_cannot_reset_password_of_equal_or_higher_class(): void
    {
        $operator = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($operator, 'nexus-web');

        $target = $this->createTestUser([
            'class' => User::CLASS_ADMINISTRATOR,
            'username' => 'peer_admin_'.bin2hex(random_bytes(4)),
        ]);

        $response = $this->post('/reset.php', [
            'username' => $target->username,
            'newpassword' => 'longenough',
            'newpasswordagain' => 'longenough',
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            "don't have enough permission",
            (string) $response->getContent(),
        );

        $unchanged = DB::table('users')->where('id', $target->id)->first();
        $this->assertSame($target->passhash, $unchanged->passhash);
    }

    public function test_happy_path_resets_password_and_renders_success(): void
    {
        $operator = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($operator, 'nexus-web');

        $target = $this->createTestUser([
            'class' => User::CLASS_USER,
            'username' => 'happy_target',
        ]);
        $originalHash = $target->passhash;
        $originalAuthKey = $target->auth_key;

        $response = $this->post('/reset.php', [
            'username' => 'happy_target',
            'newpassword' => 'brand-new-strong',
            'newpasswordagain' => 'brand-new-strong',
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('Success', $body);
        $this->assertStringContainsString('happy_target', $body);

        $updated = DB::table('users')->where('id', $target->id)->first();
        $this->assertNotSame($originalHash, $updated->passhash);
        $this->assertNotSame($originalAuthKey, $updated->auth_key);
    }

    public function test_target_username_is_html_escaped_in_success_message(): void
    {
        $operator = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($operator, 'nexus-web');

        $target = $this->createTestUser([
            'class' => User::CLASS_USER,
            'username' => 'xss<svg>'.bin2hex(random_bytes(2)),
        ]);

        $response = $this->post('/reset.php', [
            'username' => $target->username,
            'newpassword' => 'brand-new-strong',
            'newpasswordagain' => 'brand-new-strong',
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('xss&lt;svg&gt;', $body);
        $this->assertStringNotContainsString('xss<svg>', $body);
    }

    public function test_chromeless_envelope_has_no_legacy_chrome(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/reset.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('stdhead', $body);
        $this->assertStringNotContainsString('stdfoot', $body);
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

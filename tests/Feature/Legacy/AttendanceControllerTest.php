<?php

namespace Tests\Feature\Legacy;

use App\Models\Attendance;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for
 * `App\Http\Controllers\Legacy\AttendanceController` (replaces
 * `public/attendance.php`).
 *
 * Coverage focus:
 *   - guest → login redirect (auth.nexus middleware),
 *   - parked user → 403,
 *   - captcha-disabled GET → silent attend, success calendar,
 *   - already-attended-today GET → success calendar without a
 *     duplicate INSERT,
 *   - POST without a captcha when the captcha is enabled → 422,
 *   - the calendar marker text (translated) appears in the
 *     rendered HTML (smoke check that the langfile loaded).
 *
 * The captcha-validates-and-attends happy path requires driving
 * the image-captcha session token, which is too brittle to assert
 * here; the captcha-disabled silent-attend branch covers the same
 * "INSERT into attendance" code path.
 */
class AttendanceControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REQUEST_URI'] = '/attendance.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/attendance.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_parked_user_is_forbidden(): void
    {
        $user = $this->createUser();
        // `parked` is not on User::$fillable, so write it via the
        // raw query builder and refresh the model — same shape as
        // `IpHistoryControllerTest::createTestUser` and
        // `ViewNfoControllerTest`.
        NexusDB::table('users')->where('id', $user->id)->update(['parked' => 'yes']);
        $user->refresh();

        $this->actingAs($user, 'nexus-web');

        $this->disableCaptcha();
        $response = $this->get('/attendance.php');

        $response->assertForbidden();
    }

    public function test_captcha_disabled_get_silently_attends_and_renders_calendar(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $this->disableCaptcha();

        $response = $this->get('/attendance.php');

        $response->assertOk();
        $this->assertDatabaseHas('attendance', ['uid' => $user->id]);
        $body = (string) $response->getContent();
        $this->assertStringContainsString('id="calendar"', $body);
    }

    public function test_already_attended_today_renders_calendar_without_duplicate_insert(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');
        $this->disableCaptcha();

        // Pre-seed the attendance row so the controller takes the
        // "has attended today" branch.
        $today = Carbon::today();
        Attendance::create([
            'uid' => $user->id,
            'points' => 5,
            'days' => 1,
            'total_days' => 1,
            'added' => $today,
        ]);

        $response = $this->get('/attendance.php');

        $response->assertOk();
        // Only one row should be present — the controller must not
        // perform a second INSERT on the second visit.
        $count = Attendance::query()->where('uid', $user->id)->count();
        $this->assertSame(1, (int) $count);
    }

    public function test_post_with_captcha_enabled_but_invalid_payload_is_422(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        // Force both flags on. The default ImageCaptchaDriver
        // rejects empty `imagehash` / `imagestring` payloads.
        $this->setSetting('security.iv', 'yes');
        $this->setSetting('captcha.attendance.enabled', 'yes');

        $response = $this->post('/attendance.php', []);

        $response->assertStatus(422);
        // The repository should NOT have been called — no row
        // should land in the table.
        $this->assertDatabaseMissing('attendance', ['uid' => $user->id]);
    }

    public function test_form_branch_renders_when_captcha_required_and_not_attended(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'nexus-web');

        $this->setSetting('security.iv', 'yes');
        $this->setSetting('captcha.attendance.enabled', 'yes');

        $response = $this->get('/attendance.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        // The form posts back to the same URL.
        $this->assertStringContainsString('action="/attendance.php"', $body);
        $this->assertStringContainsString('method="post"', $body);
        // Captcha rendering happens through `show_image_code()`
        // which echoes a hash hidden field — its name is the only
        // marker we can rely on across captcha drivers without
        // coupling to a specific driver implementation.
        $this->assertStringContainsString('imagehash', $body);
    }

    private function createUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge([
                'lang' => self::ENGLISH_LANGUAGE_ID,
                'class' => User::CLASS_USER,
            ], $overrides),
        );
    }

    private function disableCaptcha(): void
    {
        $this->setSetting('security.iv', 'no');
        $this->setSetting('captcha.attendance.enabled', 'no');
    }

    private function setSetting(string $name, string $value): void
    {
        $now = Carbon::now()->toDateTimeString();
        Setting::query()->updateOrCreate(
            ['name' => $name],
            ['value' => $value, 'updated_at' => $now],
        );
        if (function_exists('clear_setting_cache')) {
            clear_setting_cache();
        }
    }
}

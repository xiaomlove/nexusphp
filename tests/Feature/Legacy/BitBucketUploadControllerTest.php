<?php

namespace Tests\Feature\Legacy;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/bitbucket-upload.php` contract.
 *
 * The endpoint is the user-facing avatar-upload tool. Three gates:
 *   - logged in (auth.nexus middleware),
 *   - not parked (in-controller),
 *   - `main.enablebitbucket = 'yes'` (in-controller).
 *
 * Tests use a per-test temporary bucket directory under the
 * project's `public/` tree so the GD-write path exercises real
 * filesystem behaviour without polluting `public/bitbucket/`.
 */
class BitBucketUploadControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    private string $bucketDir = '';

    private string $bucketPath = '';

    /** @var array<int,string> */
    private array $createdBitbucketRows = [];

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/bitbucket-upload.php';

        $this->bucketDir = 'bitbucket-test-'.bin2hex(random_bytes(4));
        $this->bucketPath = public_path($this->bucketDir);
        if (! is_dir($this->bucketPath)) {
            mkdir($this->bucketPath, 0o755, true);
        }

        Setting::query()->updateOrCreate(
            ['name' => 'main', 'arg' => 'enablebitbucket'],
            ['value' => 'yes', 'type' => 'string'],
        );
        Setting::query()->updateOrCreate(
            ['name' => 'main', 'arg' => 'bitbucket'],
            ['value' => $this->bucketDir, 'type' => 'string'],
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->createdBitbucketRows as $name) {
            NexusDB::table('bitbucket')->where('name', $name)->delete();
        }
        $this->createdBitbucketRows = [];

        if (is_dir($this->bucketPath)) {
            foreach (glob($this->bucketPath.'/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($this->bucketPath);
        }

        parent::tearDown();
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/bitbucket-upload.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_parked_user_is_forbidden(): void
    {
        $user = $this->createTestUser(['parked' => 'yes']);
        $this->actingAs($user, 'nexus-web');

        $this->get('/bitbucket-upload.php')->assertForbidden();
    }

    public function test_disabled_bitbucket_setting_is_forbidden(): void
    {
        Setting::query()->updateOrCreate(
            ['name' => 'main', 'arg' => 'enablebitbucket'],
            ['value' => 'no', 'type' => 'string'],
        );

        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $this->get('/bitbucket-upload.php')->assertForbidden();
    }

    public function test_get_renders_upload_form(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/bitbucket-upload.php');
        $response->assertOk();
        $body = (string) $response->getContent();

        $this->assertStringContainsString('<title>Avatar upload</title>', $body);
        $this->assertStringContainsString(
            '<form method="post" action="bitbucket-upload.php" enctype="multipart/form-data">',
            $body,
        );
        $this->assertStringContainsString('<input type="file" name="file"', $body);
        $this->assertStringContainsString('name="public" value="yes"', $body);
        $this->assertStringContainsString('Maximum file size: 262,144 bytes', $body);
    }

    public function test_get_warns_when_bucket_directory_is_unwritable(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        chmod($this->bucketPath, 0o555);
        try {
            $body = (string) $this->get('/bitbucket-upload.php')->getContent();
            $this->assertStringContainsString('Upload directory is not writable', $body);
        } finally {
            chmod($this->bucketPath, 0o755);
        }
    }

    public function test_post_without_file_returns_422(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/bitbucket-upload.php');
        $response->assertStatus(422);
        $this->assertStringContainsString('Nothing received.', (string) $response->getContent());
    }

    public function test_post_with_oversize_file_returns_422(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $file = UploadedFile::fake()->image('big.png', 800, 600)->size(512);

        $response = $this->post('/bitbucket-upload.php', ['file' => $file]);
        $response->assertStatus(422);
        $this->assertStringContainsString('File too large.', (string) $response->getContent());
    }

    public function test_post_with_non_image_returns_422(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $file = UploadedFile::fake()->create('notes.txt', 4, 'text/plain');

        $response = $this->post('/bitbucket-upload.php', ['file' => $file]);
        $response->assertStatus(422);
        $this->assertStringContainsString('Invalid image format.', (string) $response->getContent());
    }

    public function test_post_with_bad_filename_returns_422(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $file = UploadedFile::fake()->image('../sneaky.png', 80, 60);

        $response = $this->post('/bitbucket-upload.php', ['file' => $file]);
        $response->assertStatus(422);
    }

    public function test_post_with_existing_filename_returns_422(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $existingName = 'occupied-'.bin2hex(random_bytes(3)).'.png';
        file_put_contents($this->bucketPath.'/'.$existingName, 'placeholder');

        $file = UploadedFile::fake()->image($existingName, 80, 60);

        $response = $this->post('/bitbucket-upload.php', ['file' => $file]);
        $response->assertStatus(422);
        $this->assertStringContainsString('File already exists', (string) $response->getContent());
    }

    public function test_post_with_valid_png_writes_file_and_updates_user(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $name = 'avatar-'.bin2hex(random_bytes(3)).'.png';
        $this->createdBitbucketRows[] = $name;

        $file = UploadedFile::fake()->image($name, 400, 300);

        $response = $this->post('/bitbucket-upload.php', [
            'file' => $file,
            'public' => 'yes',
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();

        $this->assertStringContainsString('<title>Avatar upload</title>', $body);
        $this->assertStringContainsString('Profile updated.', $body);
        $this->assertStringContainsString($this->bucketDir.'/'.$name, $body);

        $this->assertFileExists($this->bucketPath.'/'.$name);

        $row = NexusDB::table('bitbucket')->where('name', $name)->first();
        $this->assertNotNull($row);
        $this->assertSame((int) $user->id, (int) ((array) $row)['owner']);
        $this->assertSame('1', (string) ((array) $row)['public']);

        $avatar = (string) NexusDB::table('users')->where('id', $user->id)->value('avatar');
        $this->assertStringContainsString($this->bucketDir.'/'.$name, $avatar);
    }

    public function test_post_with_image_smaller_than_target_is_not_upscaled(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $name = 'tiny-'.bin2hex(random_bytes(3)).'.png';
        $this->createdBitbucketRows[] = $name;

        $file = UploadedFile::fake()->image($name, 80, 60);

        $this->post('/bitbucket-upload.php', ['file' => $file])->assertOk();

        $size = getimagesize($this->bucketPath.'/'.$name);
        $this->assertNotFalse($size);
        $this->assertSame(80, (int) $size[0]);
        $this->assertSame(60, (int) $size[1]);
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

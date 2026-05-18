<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/bitbucketlog.php` contract.
 */
class BitBucketLogControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/bitbucketlog.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/bitbucketlog.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_user_below_administrator_is_forbidden(): void
    {
        $mod = $this->createTestUser(['class' => User::CLASS_MODERATOR]);
        $this->actingAs($mod, 'nexus-web');

        $this->get('/bitbucketlog.php')->assertForbidden();
    }

    public function test_administrator_get_renders_listing(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $rowId = $this->insertRow([
            'owner' => $admin->id,
            'name' => 'fixture_'.bin2hex(random_bytes(3)).'.png',
        ]);

        try {
            $response = $this->get('/bitbucketlog.php');

            $response->assertOk();
            $body = (string) $response->getContent();

            $this->assertStringContainsString('<title>BitBucket Log</title>', $body);
            $this->assertStringContainsString('Total Images Stored:', $body);
            $this->assertStringContainsString('(#'.$rowId.') Filename:', $body);
            $this->assertStringContainsString('[Delete]', $body);
        } finally {
            NexusDB::table('bitbucket')->where('id', $rowId)->delete();
        }
    }

    public function test_empty_listing_shows_placeholder(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        NexusDB::table('bitbucket')->truncate();

        $response = $this->get('/bitbucketlog.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('BitBucket Log is empty', $body);
    }

    public function test_delete_query_param_removes_row_and_redirects(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $rowId = $this->insertRow([
            'owner' => $admin->id,
            'name' => 'delete_me_'.bin2hex(random_bytes(3)).'.png',
        ]);

        $response = $this->get('/bitbucketlog.php?delete='.$rowId);

        $response->assertRedirect('/bitbucketlog.php');
        $this->assertNull(
            NexusDB::table('bitbucket')->where('id', $rowId)->first(),
        );
    }

    public function test_filename_in_listing_is_escaped(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $rowId = $this->insertRow([
            'owner' => $admin->id,
            'name' => '<script>alert(1)</script>.png',
        ]);

        try {
            $response = $this->get('/bitbucketlog.php');

            $response->assertOk();
            $body = (string) $response->getContent();
            $this->assertStringNotContainsString('<script>alert(1)</script>.png', $body);
            $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;.png', $body);
        } finally {
            NexusDB::table('bitbucket')->where('id', $rowId)->delete();
        }
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function insertRow(array $row): int
    {
        return (int) NexusDB::table('bitbucket')->insertGetId(array_merge([
            'added' => Carbon::now()->toDateTimeString(),
            'public' => '1',
        ], $row));
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

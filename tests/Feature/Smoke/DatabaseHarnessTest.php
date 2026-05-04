<?php

namespace Tests\Feature\Smoke;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\FeatureTestCase;

/**
 * Smoke test for the Feature-suite database harness.
 *
 * Confirms that the test database has all expected tracker tables,
 * that we can insert/query a row inside a transaction, and that the
 * transaction is rolled back at teardown. This pins down the harness
 * itself — actual announce/login/upload Feature tests build on this.
 */
class DatabaseHarnessTest extends FeatureTestCase
{
    public function test_core_tracker_tables_exist(): void
    {
        foreach (['users', 'torrents', 'peers', 'snatched', 'settings'] as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Expected tracker table `{$table}` to exist after migrations.",
            );
        }
    }

    public function test_can_create_user_and_query_back(): void
    {
        $username = 'smoke_'.bin2hex(random_bytes(4));

        $user = User::create([
            'username' => $username,
            'passhash' => str_repeat('a', 32),
            'secret' => 'secret-bytes',
            'editsecret' => '',
            'email' => $username.'@example.test',
            'status' => User::STATUS_CONFIRMED,
            'class' => User::CLASS_USER,
            'added' => now()->toDateTimeString(),
            'passkey' => bin2hex(random_bytes(16)),
        ]);

        $this->assertNotNull($user->id);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => $username,
            'status' => User::STATUS_CONFIRMED,
        ]);
    }

    public function test_database_transaction_isolates_writes_between_tests(): void
    {
        // The previous test inserted a user but the DatabaseTransactions trait
        // rolled it back at teardown, so its row must not be visible here.
        $count = DB::table('users')->where('username', 'like', 'smoke_%')->count();

        $this->assertSame(
            0,
            $count,
            'DatabaseTransactions did not roll back the previous test cleanly.',
        );
    }
}

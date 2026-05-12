<?php

namespace Tests\Feature\Repositories;

use App\Models\News;
use App\Repositories\ToolRepository;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down `ToolRepository::getNotificationCount()` against the same
 * regression class that PR #150 fixed in `include/functions.php`.
 *
 * Historical context: `users.last_home` is nullable. Users that never
 * visited `/index.php` (and never hit `NewsController@markAsRead`) carry
 * NULL there. The legacy SQL `added > sqlesc(null)` rendered as
 * `added > null` and returned 0 rows under SQL's 3-valued logic. The
 * Eloquent equivalent `News::query()->where('added', '>', null)->count()`
 * throws `InvalidArgumentException: Illegal operator and value combination`
 * — i.e. a 500 the moment any caller forwards a freshly-registered user
 * to the notification badge endpoint.
 *
 * The endpoint that consumed this (`ToolController::notifications` →
 * route in `routes/api.php:44`) is currently commented out, but the bug
 * is one route un-comment away from a public 500. These tests guard
 * against that.
 */
class ToolRepositoryNotificationCountTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private ToolRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = new ToolRepository;

        // Make sure each test starts from a known news state. We do not
        // wrap this in DatabaseTransactions::beginDatabaseTransaction()
        // because FeatureTestCase already uses DatabaseTransactions —
        // the truncate is just to defeat any seeder rows that might
        // have been inserted by other Feature tests sharing the
        // database within the same outer transaction.
        NexusDB::table('news')->delete();
    }

    public function test_null_last_home_does_not_throw_and_returns_zero_news(): void
    {
        $user = $this->createLegacyUser(overrides: ['last_home' => null]);

        // Seed one news row in the (recent) past. Without the NULL
        // guard the query builder rejects `where('added', '>', null)`
        // and this test would never reach the assertion below — it
        // would die with `InvalidArgumentException` at line 457.
        $this->seedNews(Carbon::now()->subDay());

        $result = $this->repo->getNotificationCount($user);

        $this->assertSame(
            0,
            $result['news'],
            'A user with NULL last_home must see news=0, not a 500.',
        );
    }

    public function test_last_home_before_news_added_returns_one(): void
    {
        $newsAddedAt = Carbon::create(2025, 6, 1, 12, 0, 0);
        $this->seedNews($newsAddedAt);

        $user = $this->createLegacyUser(overrides: [
            'last_home' => $newsAddedAt->copy()->subDay()->toDateTimeString(),
        ]);

        $result = $this->repo->getNotificationCount($user);

        $this->assertSame(
            1,
            $result['news'],
            'A user whose last_home predates the news row should see it as unread.',
        );
    }

    public function test_last_home_after_news_added_returns_zero(): void
    {
        $newsAddedAt = Carbon::create(2025, 6, 1, 12, 0, 0);
        $this->seedNews($newsAddedAt);

        $user = $this->createLegacyUser(overrides: [
            'last_home' => $newsAddedAt->copy()->addDay()->toDateTimeString(),
        ]);

        $result = $this->repo->getNotificationCount($user);

        $this->assertSame(
            0,
            $result['news'],
            'A user whose last_home is newer than the news row should see no unread news.',
        );
    }

    /**
     * Insert a single news row with the given `added` timestamp,
     * `notify=yes` so it counts under the legacy `unread_news` query
     * (mirrored at `include/functions.php:2944-2947`).
     */
    private function seedNews(Carbon $addedAt): void
    {
        News::query()->create([
            'subject' => 'unit-test news',
            'body' => 'fixture for ToolRepositoryNotificationCountTest',
            'added' => $addedAt->toDateTimeString(),
            'userid' => 0,
        ]);
    }
}

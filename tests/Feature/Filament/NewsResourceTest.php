<?php

namespace Tests\Feature\Filament;

use App\Events\NewsCreated;
use App\Filament\Resources\News\NewsResource;
use App\Filament\Resources\News\NewsResource\Pages\CreateNews;
use App\Filament\Resources\News\NewsResource\Pages\EditNews;
use App\Filament\Resources\News\NewsResource\Pages\ListNews;
use App\Models\Language;
use App\Models\News;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for the Filament `NewsResource` (replaces
 * `public/news.php` — see `app/Filament/Resources/News/NewsResource.php`).
 *
 * Coverage focus:
 *   - `NewsResource::canAccess()` honours the configurable
 *     `$AUTHORITY['newsmanage']` threshold via {@see user_can()},
 *     mirroring the legacy `user_can('newsmanage', true)` gate.
 *   - The `creating` boot hook on {@see News} auto-fills `userid`
 *     (current admin) and `added` (current time) when the caller did
 *     not supply them.
 *   - The `created` boot hook fires the `news_created` plugin event,
 *     so the public-side notification pipeline keeps running when an
 *     admin posts a news item through Filament.
 *   - The `saved` / `deleted` boot hooks invalidate the per-locale
 *     `recent_news` page cache that `public/index.php` reads through.
 *   - Filament Livewire shells (List/Create/Edit) render and submit.
 *
 * The legacy URL `/news.php` 302-redirects to `/nexusphp/news`; the
 * redirect contract is covered separately by `LegacyNewsRedirectTest`.
 */
class NewsResourceTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** @var array<int,int> News IDs to clean up. */
    private array $createdNewsIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/nexusphp/news';
    }

    protected function tearDown(): void
    {
        if (! empty($this->createdNewsIds)) {
            NexusDB::table('news')->whereIn('id', $this->createdNewsIds)->delete();
        }
        $this->createdNewsIds = [];

        parent::tearDown();
    }

    public function test_can_access_returns_false_for_user_without_permission(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $this->assertFalse(NewsResource::canAccess());
    }

    public function test_can_access_returns_true_for_administrator(): void
    {
        // Staff Leader short-circuits `user_can()` to true regardless
        // of the configurable AUTHORITY threshold — matches the
        // pattern used by `DelAcctAdminControllerTest` and
        // `RetriverControllerTest` to avoid coupling the test to the
        // current `$AUTHORITY['newsmanage']` value.
        $admin = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($admin, 'nexus-web');

        $this->assertTrue(NewsResource::canAccess());
    }

    public function test_creating_a_news_item_auto_fills_userid_and_added(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($admin, 'nexus-web');

        $news = News::create([
            'title' => 'auto-fill-news-'.bin2hex(random_bytes(3)),
            'body' => 'Body of the news item',
            'notify' => 'no',
        ]);
        $this->createdNewsIds[] = $news->id;

        $reloaded = News::query()->find($news->id);
        $this->assertSame((int) $admin->id, (int) $reloaded->userid);
        $this->assertNotNull($reloaded->added);
    }

    public function test_creating_a_news_item_respects_explicit_userid_and_added(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($admin, 'nexus-web');

        $author = $this->createTestUser(['class' => User::CLASS_USER]);
        $explicitDate = '2024-01-15 10:30:00';

        $news = News::create([
            'userid' => $author->id,
            'added' => $explicitDate,
            'title' => 'historical-import-'.bin2hex(random_bytes(3)),
            'body' => 'Body',
            'notify' => 'no',
        ]);
        $this->createdNewsIds[] = $news->id;

        $reloaded = News::query()->find($news->id);
        $this->assertSame((int) $author->id, (int) $reloaded->userid);
        $this->assertSame($explicitDate, $reloaded->added->format('Y-m-d H:i:s'));
    }

    public function test_creating_a_news_item_fires_news_created_event(): void
    {
        // The legacy `news.php?action=add` handler ran
        // `fire_event('news_created', News::find($newsId))` directly
        // after the INSERT. The model's `created` boot hook now does
        // the same thing — pin that contract so a future change to
        // the Filament create flow can't silently drop the
        // notification fan-out.
        Event::fake([NewsCreated::class]);

        $admin = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($admin, 'nexus-web');

        $news = News::create([
            'title' => 'event-fire-test-'.bin2hex(random_bytes(3)),
            'body' => 'body',
            'notify' => 'yes',
        ]);
        $this->createdNewsIds[] = $news->id;

        Event::assertDispatched(
            NewsCreated::class,
            fn (NewsCreated $event): bool => $event->model !== null
                && (int) $event->model->getKey() === (int) $news->id
        );
    }

    public function test_saving_a_news_item_invalidates_recent_news_cache_for_every_locale(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($admin, 'nexus-web');

        $folders = Language::listAvailable();
        Cache::put('recent_news', 'cached-from-index-php', 86400);
        foreach ($folders as $folder) {
            Cache::put($folder.'_recent_news', "cached-{$folder}", 86400);
        }

        $news = News::create([
            'title' => 'cache-bust-'.bin2hex(random_bytes(3)),
            'body' => 'body',
            'notify' => 'no',
        ]);
        $this->createdNewsIds[] = $news->id;

        $this->assertNull(
            Cache::get('recent_news'),
            'Saving a News row should drop the bare `recent_news` cache key.',
        );
        foreach ($folders as $folder) {
            $this->assertNull(
                Cache::get($folder.'_recent_news'),
                "Saving a News row should drop the per-locale `{$folder}_recent_news` key.",
            );
        }
    }

    public function test_deleting_a_news_item_invalidates_recent_news_cache(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($admin, 'nexus-web');

        $news = News::create([
            'title' => 'cache-bust-on-delete-'.bin2hex(random_bytes(3)),
            'body' => 'body',
            'notify' => 'no',
        ]);
        $this->createdNewsIds[] = $news->id;

        Cache::put('recent_news', 'cached', 86400);
        $news->delete();

        $this->assertNull(Cache::get('recent_news'));
    }

    public function test_administrator_can_render_the_list_page(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($admin, 'nexus-web');

        $news = News::create([
            'title' => 'list-page-news-'.bin2hex(random_bytes(3)),
            'body' => 'body',
            'notify' => 'no',
        ]);
        $this->createdNewsIds[] = $news->id;

        Livewire::test(ListNews::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$news]);
    }

    public function test_administrator_can_create_a_news_item_through_the_form(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($admin, 'nexus-web');

        $title = 'filament-created-news-'.bin2hex(random_bytes(3));

        Livewire::test(CreateNews::class)
            ->fillForm([
                'title' => $title,
                'body' => 'Body created via Filament',
                'notify' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $row = News::query()->where('title', $title)->first();
        $this->assertNotNull($row);
        $this->createdNewsIds[] = $row->id;

        $this->assertSame('Body created via Filament', $row->body);
        $this->assertSame('yes', $row->notify);
        $this->assertSame((int) $admin->id, (int) $row->userid);
        $this->assertNotNull($row->added);
    }

    public function test_administrator_can_edit_an_existing_news_item(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_STAFF_LEADER]);
        $this->actingAs($admin, 'nexus-web');

        $news = News::create([
            'title' => 'before-edit',
            'body' => 'before-body',
            'notify' => 'no',
        ]);
        $this->createdNewsIds[] = $news->id;

        Livewire::test(EditNews::class, ['record' => $news->id])
            ->fillForm([
                'title' => 'after-edit',
                'body' => 'after-body',
                'notify' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $reloaded = News::query()->find($news->id);
        $this->assertSame('after-edit', $reloaded->title);
        $this->assertSame('after-body', $reloaded->body);
        $this->assertSame('yes', $reloaded->notify);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTestUser(array $overrides = []): User
    {
        $username = $overrides['username'] ?? 'news-admin-'.bin2hex(random_bytes(3));
        unset($overrides['username']);

        return $this->createLegacyUser(
            overrides: array_merge(['username' => $username], $overrides),
        );
    }
}

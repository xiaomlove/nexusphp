<?php

namespace App\Models;

use App\Models\Traits\NexusActivityLogTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Nexus\Database\NexusDB;

class News extends NexusModel
{
    use NexusActivityLogTrait;

    protected $table = 'news';

    protected $fillable = [
        'userid', 'added', 'title', 'body', 'notify',
    ];

    protected $casts = [
        'added' => 'datetime',
    ];

    /**
     * Boot model events to mirror the side effects the legacy
     * `public/news.php` script ran inline:
     *
     *   1. Fill `userid` and `added` on create when the caller did
     *      not provide them. Mirrors `userid => $CURUSER['id']` and
     *      the `$addedDate ? : date(...)` clause from the legacy
     *      `?action=add` handler. The Filament create form does not
     *      bind `userid`, so this hook makes the column reflect the
     *      current admin without us needing a separate
     *      `mutateFormDataBeforeCreate` override.
     *   2. Fire the `news_created` plugin / listener event. The
     *      legacy script ran `fire_event('news_created', ...)`
     *      directly after the INSERT; the public-side notification
     *      pipeline (`App\Events\NewsCreated`) hooks off this event
     *      to PM every user when `notify='yes'` is set.
     *   3. Drop the public `recent_news` page cache for every
     *      configured locale. Mirrors the legacy
     *      `$Cache->delete_value('recent_news', true)` calls in
     *      every write branch of `news.php` (and the matching call
     *      in `settings.php` after a settings edit). Without this,
     *      an admin's edit would not become visible on
     *      `public/index.php` until the 24-hour TTL expired.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $news): void {
            $news->fillUseridAndAdded();
        });

        static::created(function (self $news): void {
            // The legacy script ran
            //   `fire_event('news_created', News::query()->find($newsId))`
            // *after* the INSERT. Inside Eloquent's `created` event the
            // model already has its primary key populated and the row
            // is visible inside the current transaction, so we can pass
            // `$news` directly without a redundant re-fetch.
            fire_event('news_created', $news);
        });

        static::saved(function (self $news): void {
            self::forgetPublicCache();
        });

        static::deleted(function (self $news): void {
            self::forgetPublicCache();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }

    /**
     * Resolve the current writer's user id (Filament admin or
     * legacy `$CURUSER`) and stamp `added` if the caller did not
     * provide it. Both fields are intentionally left untouched if
     * the caller already populated them — a CLI seeder or a
     * historical-import script can still pass explicit values.
     */
    private function fillUseridAndAdded(): void
    {
        if (empty($this->userid)) {
            $userId = self::resolveCurrentUserId();
            if ($userId > 0) {
                $this->userid = $userId;
            }
        }

        if (empty($this->added)) {
            $this->added = Carbon::now();
        }
    }

    /**
     * Read the writer's user id from Auth (Filament panel or any
     * other Laravel-side caller) and fall back to the legacy
     * `$CURUSER` global. Returns 0 when no user is available, which
     * matches the column's schema default.
     */
    private static function resolveCurrentUserId(): int
    {
        $user = Auth::guard('nexus-web')->user() ?? Auth::user();
        if ($user instanceof User) {
            return (int) $user->id;
        }

        if (isset($GLOBALS['CURUSER']['id'])) {
            return (int) $GLOBALS['CURUSER']['id'];
        }

        return 0;
    }

    /**
     * Forget the public `recent_news` page-cache key for every
     * locale `class_cache_redis::delete_value(..., true)` would
     * have walked. The legacy helper is still bound on the
     * `$Cache` global, so this method delegates to it when
     * available; otherwise it falls back to plain
     * `Cache::forget(...)` calls so a callsite that runs before
     * `include/core.php` still gets the same observable effect.
     *
     * Public consumers:
     *   - `public/index.php` reads through
     *     `$Cache->new_page('recent_news', 86400, true)` (the
     *     `true` flag makes the key per-language).
     */
    public static function forgetPublicCache(): void
    {
        $cache = $GLOBALS['Cache'] ?? null;
        if (is_object($cache) && method_exists($cache, 'delete_value')) {
            $cache->delete_value('recent_news', true);

            return;
        }

        // Defensive fallback: bypass the page-cache helper and drop
        // every key shape `delete_value('recent_news', true)` would
        // have written. Keeps a CLI / queue-worker callsite that has
        // not bootstrapped `include/core.php` yet from leaving stale
        // entries behind.
        NexusDB::cache_del('recent_news');
        foreach (Language::listAvailable() as $folder) {
            NexusDB::cache_del($folder.'_recent_news');
        }
    }
}

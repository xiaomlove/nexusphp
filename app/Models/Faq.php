<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Faq extends NexusModel
{
    public const TYPE_CATEG = 'categ';

    public const TYPE_ITEM = 'item';

    public const FLAG_HIDDEN = 0;

    public const FLAG_NORMAL = 1;

    public const FLAG_UPDATED = 2;

    public const FLAG_NEW = 3;

    /**
     * Section flags. Items can be all four; sections can only be hidden
     * or normal (matches the legacy `faqactions.php?action=editsect` form).
     *
     * @var array<int,string>
     */
    public const FLAGS_CATEG = [
        self::FLAG_HIDDEN => 'Hidden',
        self::FLAG_NORMAL => 'Normal',
    ];

    /**
     * Item flags including the "Updated" / "New" markers that the
     * public FAQ rendering pipeline recognises (see
     * `App\Http\Controllers\Legacy\FaqController::renderBody`).
     *
     * @var array<int,string>
     */
    public const FLAGS_ITEM = [
        self::FLAG_HIDDEN => 'Hidden',
        self::FLAG_NORMAL => 'Normal',
        self::FLAG_UPDATED => 'Updated',
        self::FLAG_NEW => 'New',
    ];

    protected $table = 'faq';

    protected $fillable = [
        'link_id',
        'type',
        'lang_id',
        'question',
        'answer',
        'flag',
        'categ',
        'order',
    ];

    protected $casts = [
        'lang_id' => 'integer',
        'flag' => 'integer',
        'categ' => 'integer',
        'order' => 'integer',
        'link_id' => 'integer',
    ];

    /**
     * Boot model events to:
     *   1. Auto-fill `link_id` and `order` on create. The legacy
     *      `faqactions.php?action=addnewsect` / `addnewitem` handlers
     *      both ran a `MAX(...)+1` SQL probe scoped by
     *      `(lang_id, type)` for sections and `(lang_id, type, categ)`
     *      for items; reproduced verbatim here so a row created
     *      through Filament gets the same defaults a legacy form
     *      would have produced.
     *   2. Forget the public FAQ body cache for the row's language
     *      after every save / delete. The public-facing
     *      `FaqController` reads through `Cache::remember('faq:body:'
     *      .$langId, 900, ...)` so without an invalidation hook an
     *      admin edit would not become visible until the 15-minute
     *      TTL expired.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $faq): void {
            $faq->fillLinkIdAndOrder();
        });

        static::saved(function (self $faq): void {
            $faq->forgetPublicCache();
        });

        static::deleted(function (self $faq): void {
            $faq->forgetPublicCache();
        });
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'lang_id');
    }

    /**
     * Convenience accessor — the legacy admin shows the parent
     * section's title in the "Edit Item" select. Items reference
     * their parent through `categ` → parent's `link_id` (not its `id`),
     * because `link_id` is stable across edits.
     */
    protected function categSection(): Attribute
    {
        return Attribute::make(
            get: function (): ?self {
                if ($this->type !== self::TYPE_ITEM || $this->categ === 0) {
                    return null;
                }

                return self::query()
                    ->where('type', self::TYPE_CATEG)
                    ->where('lang_id', $this->lang_id)
                    ->where('link_id', $this->categ)
                    ->first();
            }
        );
    }

    /**
     * Look up the next free `link_id` and `order` for this row's
     * `(lang_id, type, categ)` scope and write them onto the model
     * unless the caller already provided values. Mirrors the legacy
     * `MAX(...)+1` SQL probe used by `faqactions.php`.
     */
    private function fillLinkIdAndOrder(): void
    {
        $query = self::query()
            ->where('type', $this->type)
            ->where('lang_id', $this->lang_id);

        if ($this->type === self::TYPE_ITEM) {
            $query->where('categ', (int) $this->categ);
        }

        $maxRow = $query
            ->selectRaw('MAX(`link_id`) AS max_link_id, MAX(`order`) AS max_order')
            ->first();

        if (empty($this->link_id)) {
            $this->link_id = (int) ($maxRow->max_link_id ?? 0) + 1;
        }

        if (empty($this->order)) {
            $this->order = (int) ($maxRow->max_order ?? 0) + 1;
        }
    }

    /**
     * Forget every public-facing `faq:body:*` cache key the
     * `FaqController` uses, so the admin's edit becomes visible on
     * the next request without waiting for the 15-minute TTL to
     * expire.
     *
     * The active locales aren't enumerated by the legacy code — we
     * iterate the `language` table itself, which is typically ~17
     * rows. Each `Cache::forget` is a single round-trip; total cost
     * stays well under a millisecond on a colocated Redis.
     */
    public function forgetPublicCache(): void
    {
        Language::query()
            ->pluck('id')
            ->each(fn ($id): bool => Cache::forget('faq:body:'.(int) $id));
    }
}

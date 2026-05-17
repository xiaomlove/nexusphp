<?php

namespace Tests\Unit\Services;

use App\Models\BonusLogs;
use App\Repositories\BonusRepository;
use App\Services\BonusLogPage;
use App\Services\BonusLogService;
use InvalidArgumentException;
use Tests\FeatureTestCase;

/**
 * Pure (no-DB) parts of {@see BonusLogService}:
 *
 *   - `normaliseCategory()` accepts `common`, accepts `seeding` only
 *     when the caller signals the seeding feature is on, and rejects
 *     every other value.
 *   - `normaliseBusinessType()` casts the input to int and rejects
 *     anything that isn't a known `BonusLogs::BUSINESS_TYPE_*` key.
 *   - `BonusLogPage::totalPages()` math.
 *
 * The DB-touching methods (`findUser()`, `page()`) are pinned
 * separately in `Tests\Feature\Legacy\BonusLogControllerTest`.
 */
class BonusLogServiceTest extends FeatureTestCase
{
    public function test_normalise_category_defaults_to_common_when_null(): void
    {
        $service = new BonusLogService(new BonusRepository);

        $this->assertSame(BonusLogs::CATEGORY_COMMON, $service->normaliseCategory(null, false));
        $this->assertSame(BonusLogs::CATEGORY_COMMON, $service->normaliseCategory(null, true));
    }

    public function test_normalise_category_accepts_common(): void
    {
        $service = new BonusLogService(new BonusRepository);

        $this->assertSame(
            BonusLogs::CATEGORY_COMMON,
            $service->normaliseCategory(BonusLogs::CATEGORY_COMMON, false),
        );
    }

    public function test_normalise_category_rejects_seeding_when_disabled(): void
    {
        $service = new BonusLogService(new BonusRepository);

        $this->expectException(InvalidArgumentException::class);
        $service->normaliseCategory(BonusLogs::CATEGORY_SEEDING, false);
    }

    public function test_normalise_category_accepts_seeding_when_enabled(): void
    {
        $service = new BonusLogService(new BonusRepository);

        $this->assertSame(
            BonusLogs::CATEGORY_SEEDING,
            $service->normaliseCategory(BonusLogs::CATEGORY_SEEDING, true),
        );
    }

    public function test_normalise_category_rejects_unknown_value(): void
    {
        $service = new BonusLogService(new BonusRepository);

        $this->expectException(InvalidArgumentException::class);
        $service->normaliseCategory('totally-not-a-category', true);
    }

    public function test_normalise_business_type_treats_empty_as_no_filter(): void
    {
        $service = new BonusLogService(new BonusRepository);

        $this->assertSame(0, $service->normaliseBusinessType(null, true));
        $this->assertSame(0, $service->normaliseBusinessType('', true));
        $this->assertSame(0, $service->normaliseBusinessType('0', true));
    }

    public function test_normalise_business_type_accepts_known_id(): void
    {
        $service = new BonusLogService(new BonusRepository);

        $this->assertSame(
            BonusLogs::BUSINESS_TYPE_REWARD_TORRENT,
            $service->normaliseBusinessType(
                (string) BonusLogs::BUSINESS_TYPE_REWARD_TORRENT,
                true,
            ),
        );
    }

    public function test_normalise_business_type_rejects_unknown_id(): void
    {
        $service = new BonusLogService(new BonusRepository);

        $this->expectException(InvalidArgumentException::class);
        $service->normaliseBusinessType('999999', true);
    }

    public function test_total_pages_rounds_up(): void
    {
        $page = new BonusLogPage(total: 101, page: 0, perPage: 50, rows: []);

        $this->assertSame(3, $page->totalPages());
    }

    public function test_total_pages_is_one_for_a_partial_page(): void
    {
        $page = new BonusLogPage(total: 7, page: 0, perPage: 50, rows: []);

        $this->assertSame(1, $page->totalPages());
    }

    public function test_total_pages_is_zero_when_empty(): void
    {
        $page = new BonusLogPage(total: 0, page: 0, perPage: 50, rows: []);

        $this->assertSame(0, $page->totalPages());
    }
}

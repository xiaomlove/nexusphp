<?php

namespace App\Services;

use App\Models\BonusLogs;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\BonusRepository;
use InvalidArgumentException;

/**
 * Typed read-only view of the data that `public/bonus-log.php` used to
 * assemble inline. Phase 3 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 3 — big user pages".
 *
 * Owns input validation (category / business_type) and pagination
 * dispatch; storage backend lives in {@see BonusRepository} so the
 * "common" branch hits the `bonus_logs` table and the "seeding"
 * branch hits ClickHouse, exactly as the legacy script did.
 */
class BonusLogService
{
    public const DEFAULT_PER_PAGE = 50;

    public function __construct(private readonly BonusRepository $repository) {}

    public function findUser(int $uid): ?User
    {
        if ($uid <= 0) {
            return null;
        }

        /** @var User|null $user */
        $user = User::query()->where('id', $uid)->first(User::$commonFields);

        return $user;
    }

    public function isSeedingEnabled(): bool
    {
        return Setting::getIsRecordSeedingBonusLog();
    }

    /**
     * @return array<string,string>
     */
    public function categoryOptions(bool $seedingEnabled): array
    {
        return BonusLogs::listCategoryOptions($seedingEnabled);
    }

    /**
     * @return array<int,string>
     */
    public function businessTypeOptions(bool $seedingEnabled): array
    {
        return BonusLogs::listBusinessTypeOptions(
            $seedingEnabled ? '' : BonusLogs::CATEGORY_COMMON,
        );
    }

    public function normaliseCategory(?string $category, bool $seedingEnabled): string
    {
        $value = $category ?? BonusLogs::CATEGORY_COMMON;
        $allowed = $this->categoryOptions($seedingEnabled);
        if (! isset($allowed[$value])) {
            throw new InvalidArgumentException("Invalid category: $value");
        }

        return $value;
    }

    public function normaliseBusinessType(?string $businessType, bool $seedingEnabled): int
    {
        $value = (int) ($businessType ?? 0);
        if ($value === 0) {
            return 0;
        }
        $allowed = $this->businessTypeOptions($seedingEnabled);
        if (! isset($allowed[$value])) {
            throw new InvalidArgumentException("Invalid business_type: $value");
        }

        return $value;
    }

    public function page(
        string $category,
        int $uid,
        int $businessType,
        int $page = 0,
        int $perPage = self::DEFAULT_PER_PAGE,
    ): BonusLogPage {
        if ($uid <= 0) {
            throw new InvalidArgumentException("Invalid uid: $uid");
        }
        if ($perPage <= 0) {
            $perPage = self::DEFAULT_PER_PAGE;
        }
        $page = max(0, $page);

        $total = $this->repository->getCount($category, $uid, $businessType);
        $rows = $this->repository->getList(
            $category,
            $uid,
            $businessType,
            $page + 1,
            $perPage,
        );

        return new BonusLogPage(
            total: $total,
            page: $page,
            perPage: $perPage,
            rows: $rows,
        );
    }
}

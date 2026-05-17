<?php

namespace App\Http\Controllers\Legacy;

use App\Enums\Permission\PermissionEnum;
use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use App\Services\BonusLogService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

/**
 * Replacement for `public/bonus-log.php` (deleted in the same PR).
 *
 * Phase 3 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 3 — big user pages". The legacy script was a 109-LOC
 * procedural blob that mixed input validation, permission gating,
 * pager rendering, and inline SQL via `BonusRepository`. The rewrite
 * splits responsibilities:
 *
 *   - {@see BonusLogService} owns input validation, category /
 *     business-type normalisation, and pagination dispatch into
 *     `BonusRepository` (which handles both the MySQL `bonus_logs`
 *     branch and the ClickHouse `seeding` branch).
 *   - `resources/views/legacy/bonus-log.blade.php` owns rendering
 *     (Blade `{{ }}` autoescape closes a latent XSS sink on the
 *     `comment` column which the legacy `echo $row->comment` exposed).
 *   - This controller wires the request into the service and renders
 *     the chrome-less HTML envelope every Phase 2/3 controller uses.
 *
 * Wire-level contract preserved:
 *   - URL stays `/bonus-log.php` (linked from `usercp.php` /
 *     `userdetails.php` / `myhr.php`).
 *   - Optional `?uid` query (defaults to the viewer); cross-user view
 *     requires the legacy `viewhistory` permission, matching the
 *     `user_can(PermissionEnum::VIEW_USER_HISTORY, true)` call.
 *   - Optional `?category=common|seeding` (default `common`); `seeding`
 *     is only honoured if `Setting::getIsRecordSeedingBonusLog()` is
 *     true.
 *   - Optional `?business_type=<n>` (default `0` = all).
 *   - Pager uses `?page=<n>` (0-indexed).
 */
class BonusLogController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
        private readonly BonusLogService $service,
    ) {}

    public function __invoke(Request $request): Response
    {
        $viewer = $this->context->user();
        if ($viewer === null) {
            abort(401);
        }

        $uid = (int) $request->input('uid', $viewer->id);
        if ($uid <= 0) {
            abort(422, "Invalid uid: $uid");
        }

        $target = $this->service->findUser($uid);
        if ($target === null) {
            abort(404, "Invalid uid: $uid");
        }

        if ($target->id !== $viewer->id && ! $this->canViewOther($viewer)) {
            abort(403);
        }

        $seedingEnabled = $this->service->isSeedingEnabled();

        try {
            $category = $this->service->normaliseCategory(
                $request->input('category'),
                $seedingEnabled,
            );
            $businessType = $this->service->normaliseBusinessType(
                $request->input('business_type'),
                $seedingEnabled,
            );
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        $page = max(0, (int) $request->input('page', 0));
        $bonusPage = $this->service->page(
            category: $category,
            uid: $uid,
            businessType: $businessType,
            page: $page,
        );

        $body = view('legacy.bonus-log', [
            'target' => $target,
            'category' => $category,
            'businessType' => $businessType,
            'categoryOptions' => $this->service->categoryOptions($seedingEnabled),
            'businessTypeOptions' => $this->service->businessTypeOptions($seedingEnabled),
            'pageData' => $bonusPage,
            'requestUri' => (string) $request->getRequestUri(),
        ])->render();

        $title = htmlspecialchars(
            'Bonus log — '.$target->username,
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$title}</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }

    private function canViewOther(User $viewer): bool
    {
        $permission = PermissionEnum::VIEW_USER_HISTORY->value;

        return (bool) user_can($permission, false, (int) $viewer->id);
    }
}

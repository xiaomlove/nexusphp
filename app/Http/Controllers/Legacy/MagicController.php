<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Legacy\MagicRewardRequest;
use App\Legacy\LegacyContext;
use App\Services\BonusRewardService;
use Illuminate\Http\JsonResponse;

/**
 * Replacement for `public/magic.php` (deleted in the same PR).
 *
 * Phase 3 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 3 — big user pages". The legacy script was a 67-LOC
 * procedural blob that mixed input parsing, balance checks,
 * idempotency, daily-limit enforcement, two `KPS()` calls and two
 * `BonusLogs::add()` calls — all without a transaction. This
 * controller is now a single dispatch into
 * {@see BonusRewardService}, which owns the domain semantics and
 * wraps the 5 mutations in `NexusDB::transaction()`.
 *
 * Wire-level contract is unchanged: POST `/magic.php` with
 * `id` (torrent id) + `value` (bonus amount); responses are the
 * legacy `{ret, msg, data}` envelope so
 * `public/js/common.js#saveMagicValue` keeps working without a JS
 * change. All responses are HTTP 200 (matching the legacy
 * `exit(json_encode(fail(...)))` shape).
 */
class MagicController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
        private readonly BonusRewardService $rewards,
    ) {}

    public function __invoke(MagicRewardRequest $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            // The auth.nexus middleware on the route should keep us
            // from ever reaching this branch, but the explicit
            // 401 is cheaper than relying on a redirect for an
            // XHR caller.
            return $this->failResponse('Permission denied.', status: 401);
        }

        $outcome = $this->rewards->attempt(
            rewarder: $user,
            torrentId: (int) $request->validated('id'),
            value: (int) $request->validated('value'),
        );

        if (! $outcome->success) {
            return $this->failResponse($outcome->message);
        }

        return $this->okResponse();
    }

    private function okResponse(): JsonResponse
    {
        return response()->json([
            'ret' => 0,
            'msg' => 'OK',
            'data' => [],
        ]);
    }

    /**
     * Build the legacy `{ret, msg, data}` envelope for a failure.
     *
     * Renamed from `fail()` to avoid colliding with the parent
     * `App\Http\Controllers\Controller::fail()` helper — PHP enforces
     * "child can't tighten visibility", and the base controller
     * declares `fail()` as `public`.
     */
    private function failResponse(string $msg, int $status = 200): JsonResponse
    {
        return response()->json([
            'ret' => -1,
            'msg' => $msg,
            'data' => [],
        ], $status);
    }
}

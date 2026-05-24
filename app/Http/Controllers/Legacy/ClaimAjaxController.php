<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Repositories\BonusRepository;
use App\Repositories\ClaimRepository;
use App\Repositories\ExamRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Phase 2.5 (this PR / batch C of the `public/ajax.php` cleanup):
 * replaces 5 of the remaining `AjaxInterface::*` static methods that
 * the legacy reflection-dispatcher in `public/ajax.php` exposes —
 * a mixed batch of user-side write actions:
 *
 *   - claim sub-API:        addClaim, removeClaim
 *   - hit-and-run sub-API:  removeHitAndRun
 *   - exam sub-API:         claimTask
 *   - benefit sub-API:      consumeBenefit
 *
 * The five wire-level contracts are preserved verbatim — same
 * accepted POST keys (`params[*]`), same `{ret, msg, data}` JSON
 * envelope at HTTP 200 (including on error), so the four
 * first-party JS callers keep working after a single one-line
 * `apiUrl` flip in each:
 *
 *   - `public/details.php:315` (the "claim torrent" button on
 *     `/details.php` — POST `addClaim`),
 *   - `app/Http/Controllers/Legacy/MyhrController.php:133` (the
 *     "Remove H&R" button on `/myhr.php` — POST `removeHitAndRun`),
 *   - `app/Http/Controllers/Legacy/TaskController.php:224` (the
 *     "claim exam" button on `/task.php` — POST `claimTask`),
 *   - `public/userdetails.php:286` (the "consume change-username
 *     card" form on `/userdetails.php?id=<self>` — POST
 *     `consumeBenefit`).
 *
 * Plus `public/js/nexus.js:140-157` — the shared
 * `claimAction(action, …)` helper that is the actual call-site
 * for `addClaim` / `removeClaim` (invoked from
 * `public/details.php:315` via `data-action="addClaim"` /
 * `data-action="removeClaim"` attributes).
 *
 * Action map (legacy → Laravel route, all authed):
 *   - `addClaim`         → POST /claim/add
 *   - `removeClaim`      → POST /claim/remove
 *   - `removeHitAndRun`  → POST /hit-and-run/remove
 *   - `claimTask`        → POST /exam/claim-task
 *   - `consumeBenefit`   → POST /benefit/consume
 *
 * Auth posture: all five routes sit behind `auth.nexus:nexus-web`,
 * mirroring the `loggedinorreturn()` gate in the legacy dispatcher.
 *
 * CSRF carve-out lives in `App\Http\Middleware\VerifyCsrfToken::$except`
 * (`'claim/*'`, `'hit-and-run/*'`, `'exam/claim-task'`,
 * `'benefit/consume'`) — the legacy XHRs post bare
 * `application/x-www-form-urlencoded` bodies with no `_token`.
 * Adding CSRF plumbing to those legacy inline scripts is a
 * separate, larger change.
 *
 * Error shape: every catch path returns HTTP 200 with `{ret: -1,
 * msg, data: []}`, mirroring `public/ajax.php`'s
 * `exit(json_encode(fail($exception->getMessage(), $_POST)))`.
 *
 * Naming: the controller is named after the "claim" sub-API
 * because that has the most actions (2 of 5); the other three
 * sub-APIs piggy-back on the same controller because they share
 * the same JSON envelope contract and CSRF posture, and splitting
 * them into per-domain controllers would multiply file boilerplate
 * without improving clarity for a 5-action batch.
 */
class ClaimAjaxController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
        private readonly ClaimRepository $claims,
        private readonly BonusRepository $bonus,
        private readonly ExamRepository $exams,
        private readonly UserRepository $users,
    ) {}

    /**
     * `addClaim` — record a torrent-claim for the current user.
     */
    public function addClaim(Request $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        $params = $this->params($request);

        try {
            $result = $this->claims->store(
                $user->id,
                $params['torrent_id'] ?? null,
            );
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse($result);
    }

    /**
     * `removeClaim` — drop one of the current user's torrent-claims
     * by claim id.
     */
    public function removeClaim(Request $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        $params = $this->params($request);

        try {
            $result = $this->claims->delete(
                $params['id'] ?? null,
                $user->id,
            );
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse($result);
    }

    /**
     * `removeHitAndRun` — consume the configured bonus amount to
     * cancel a `hit_and_runs` row owned by the current user.
     */
    public function removeHitAndRun(Request $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        $params = $this->params($request);

        try {
            $result = $this->bonus->consumeToCancelHitAndRun(
                $user->id,
                $params['id'] ?? null,
            );
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse($result);
    }

    /**
     * `claimTask` — assign an exam (task) to the current user.
     */
    public function claimTask(Request $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        $params = $this->params($request);

        try {
            $examId = (int) ($params['exam_id'] ?? 0);
            $result = $this->exams->assignToUser($user->id, $examId);
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse($result);
    }

    /**
     * `consumeBenefit` — spend a one-shot benefit card on the
     * current user (e.g. the change-username card on
     * `/userdetails.php`). The repository inspects
     * `params.meta_key` to dispatch to the right handler.
     */
    public function consumeBenefit(Request $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        $params = $this->params($request);

        try {
            $result = $this->users->consumeBenefit($user->id, $params);
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse($result);
    }

    /**
     * Pull the legacy `params[*]` POST array, normalised to a plain
     * `string => mixed` map. Mirrors `public/ajax.php`:
     *   `$params = $_POST['params'] ?? [];`
     *
     * @return array<int|string,mixed>
     */
    private function params(Request $request): array
    {
        $raw = $request->input('params', []);

        return is_array($raw) ? $raw : [];
    }

    /**
     * Build the legacy `{ret, msg, data}` envelope for a success.
     */
    private function okResponse(mixed $data): JsonResponse
    {
        return response()->json([
            'ret' => 0,
            'msg' => 'OK',
            'data' => $data,
        ]);
    }

    /**
     * Build the legacy `{ret, msg, data}` envelope for a failure.
     *
     * Renamed from `fail()` to avoid colliding with the parent
     * `App\Http\Controllers\Controller::fail()` helper.
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

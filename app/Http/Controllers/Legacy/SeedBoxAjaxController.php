<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\SeedBoxRecord;
use App\Models\User;
use App\Repositories\SeedBoxRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

/**
 * Phase 2.5 (this PR / batch D of the `public/ajax.php` cleanup):
 * replaces 4 of the remaining `AjaxInterface::*` static methods that
 * the legacy reflection-dispatcher in `public/ajax.php` exposes —
 * a mixed batch of user-side write actions across two sub-APIs:
 *
 *   - seed-box sub-API:  addSeedBoxRecord, removeSeedBoxRecord
 *   - user-token sub-API: addToken, removeToken
 *
 * The four wire-level contracts are preserved verbatim — same
 * accepted POST keys (`params[*]`), same `{ret, msg, data}` JSON
 * envelope at HTTP 200 (including on error).
 *
 * Action map (legacy → Laravel route, all authed):
 *   - `addSeedBoxRecord`     → POST /seed-box/add
 *   - `removeSeedBoxRecord`  → POST /seed-box/remove
 *   - `addToken`             → POST /user/token/add
 *   - `removeToken`          → POST /user/token/remove
 *
 * First-party JS callers, all flipped in this PR:
 *   - `public/usercp.php:1149` (the "Add seed-box record" modal
 *     submit on `/usercp.php` — POST /seed-box/add),
 *   - `public/usercp.php:1161` (the per-row "Remove seed-box
 *     record" button on `/usercp.php` — POST /seed-box/remove).
 *
 * `addToken` / `removeToken` have no first-party JS caller in the
 * tree — those happen via the dedicated
 * `App\Http\Controllers\TokenController` mounted at
 * `Route::post('web/token/add'|'web/token/del', ...)` (see
 * `routes/web.php`). The two ajax.php actions are a parallel,
 * unused HTTP fasade. They are migrated for parity in case
 * third-party integrations rely on the legacy URLs; the new URLs
 * (`/user/token/add` and `/user/token/remove`) are deliberately
 * different from the modern `/web/token/*` group so the two
 * lifecycles stay independent.
 *
 * Auth posture: all four routes sit behind `auth.nexus:nexus-web`,
 * mirroring the `loggedinorreturn()` gate in the legacy dispatcher.
 *
 * CSRF carve-out lives in `App\Http\Middleware\VerifyCsrfToken::$except`
 * (`'seed-box/*'`, `'user/token/*'`) — the legacy XHR posts a bare
 * `application/x-www-form-urlencoded` body with no `_token`.
 *
 * Naming: the controller is named after the seed-box sub-API
 * because its two actions carry the actual JS callers in the
 * tree; the token actions piggy-back because they share the same
 * envelope contract and CSRF posture, and creating a separate
 * one-method controller for an unused-on-the-front-end endpoint
 * would multiply boilerplate without adding clarity. Same
 * trade-off as the PR-C `ClaimAjaxController` batch.
 */
class SeedBoxAjaxController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
        private readonly SeedBoxRepository $seedBoxes,
    ) {}

    /**
     * `addSeedBoxRecord` — INSERT a new `seed_box_records` row owned
     * by the current user. The legacy dispatcher unconditionally
     * forced `uid=$CURUSER['id']` / `type=TYPE_USER` /
     * `status=STATUS_UNAUDITED`; preserved verbatim so users can't
     * spoof a different owner via the form payload.
     */
    public function addSeedBoxRecord(Request $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        $params = $this->params($request);
        $params['uid'] = $user->id;
        $params['type'] = SeedBoxRecord::TYPE_USER;
        $params['status'] = SeedBoxRecord::STATUS_UNAUDITED;

        try {
            $result = $this->seedBoxes->store($params);
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse($result);
    }

    /**
     * `removeSeedBoxRecord` — DELETE one of the current user's
     * `seed_box_records` rows by id. The repository enforces
     * ownership via the second argument.
     */
    public function removeSeedBoxRecord(Request $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        $params = $this->params($request);

        try {
            $result = $this->seedBoxes->delete(
                $params['id'] ?? null,
                $user->id,
            );
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse($result);
    }

    /**
     * `addToken` — mint a Sanctum personal access token under a
     * user-supplied label. Mirrors the legacy dispatcher exactly:
     *
     *   - `params.name` is required (legacy threw
     *     `\InvalidArgumentException("Name is required")`),
     *   - the User model is loaded via the
     *     `User::$commonFields` slim projection,
     *   - the token is created via `User::createToken($name)`,
     *   - on success the envelope's `data` is `true` (not the
     *     plain-text token — the legacy dispatcher did not surface
     *     it either; the modern `TokenController` route at
     *     `/web/token/add` is the canonical caller for that flow).
     */
    public function addToken(Request $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        $params = $this->params($request);

        try {
            $name = (string) ($params['name'] ?? '');
            if ($name === '') {
                throw new InvalidArgumentException('Name is required');
            }
            $userModel = User::query()->findOrFail($user->id, User::$commonFields);
            $userModel->createToken($name);
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse(true);
    }

    /**
     * `removeToken` — revoke one of the current user's Sanctum
     * personal access tokens by `personal_access_tokens.id`.
     */
    public function removeToken(Request $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        $params = $this->params($request);

        try {
            $id = $params['id'] ?? null;
            if (empty($id)) {
                throw new InvalidArgumentException('id is required');
            }
            $userModel = User::query()->findOrFail($user->id, User::$commonFields);
            $userModel->tokens()->where('id', $id)->delete();
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse(true);
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

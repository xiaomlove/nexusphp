<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Repositories\UserPasskeyRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Phase 2.5 (this PR / batch A of the `public/ajax.php` cleanup):
 * replaces 6 of the 26 `AjaxInterface::*` static methods that the
 * legacy reflection-dispatcher in `public/ajax.php` exposes — the
 * Passkey / WebAuthn sub-API.
 *
 * The six wire-level contracts are preserved verbatim — same accepted
 * POST keys (`params[*]`), same `{ret, msg, data}` JSON envelope, same
 * HTTP-200-on-error shape — so `public/js/passkey.js` (the only
 * first-party caller) keeps working after a single one-line `apiUrl`
 * change inside it.
 *
 * Action map (legacy → Laravel route):
 *   - `getPasskeyCreateArgs`  → POST /passkey/create-args  (authed)
 *   - `processPasskeyCreate`  → POST /passkey/create       (authed)
 *   - `deletePasskey`         → POST /passkey/delete       (authed)
 *   - `getPasskeyList`        → POST /passkey/list         (authed)
 *   - `getPasskeyGetArgs`     → POST /passkey/get-args     (login flow, no auth)
 *   - `processPasskeyGet`     → POST /passkey/get          (login flow, no auth)
 *
 * Auth posture matches the legacy gate inside `public/ajax.php`:
 *   `if ($action != 'getPasskeyGetArgs' && $action != 'processPasskeyGet') loggedinorreturn();`
 * The four management endpoints sit behind `auth.nexus:nexus-web`;
 * the two login-flow endpoints intentionally do not — the user does
 * not yet have a session when starting passkey-based login, and
 * `processGet` is the very call that mints it via `logincookie()`.
 *
 * Error shape: every catch path returns HTTP 200 with `{ret: -1, msg,
 * data: []}`, mirroring `public/ajax.php`'s
 * `exit(json_encode(fail($exception->getMessage(), $_POST)))`. The JS
 * caller in `public/js/passkey.js` reads `data.ret !== 0` and throws
 * a JS `Error(data.msg)` from there, so the legacy XHR error UX is
 * preserved without a JS change.
 *
 * CSRF carve-out lives in `App\Http\Middleware\VerifyCsrfToken::$except`
 * (`'passkey/*'`) — the legacy XHR posts a bare `URLSearchParams` body
 * with no `_token`. Adding CSRF plumbing to `passkey.js` is a separate,
 * larger change.
 */
class PasskeyAjaxController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
    ) {}

    /**
     * `getPasskeyCreateArgs` — emit a fresh `getCreateArgs` payload
     * + cached challenge id for the registration ceremony.
     */
    public function getCreateArgs(): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            // The auth.nexus middleware on the route should keep us
            // from ever reaching this branch; the explicit 401 is
            // cheaper than relying on a redirect for an XHR caller.
            return $this->failResponse('Permission denied.', status: 401);
        }

        try {
            $data = UserPasskeyRepository::getCreateArgs($user->id, $user->username);
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse($data);
    }

    /**
     * `processPasskeyCreate` — verify the registration response and
     * persist the new credential against the current user.
     */
    public function processCreate(Request $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        $params = $this->params($request);

        try {
            UserPasskeyRepository::processCreate(
                $user->id,
                $params['challengeId'] ?? null,
                $params['clientDataJSON'] ?? null,
                $params['attestationObject'] ?? null,
            );
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse(true);
    }

    /**
     * `deletePasskey` — remove a registered credential from the
     * current user's passkey list.
     */
    public function deletePasskey(Request $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        $params = $this->params($request);

        try {
            $deleted = UserPasskeyRepository::delete(
                $user->id,
                $params['credentialId'] ?? null,
            );
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse($deleted);
    }

    /**
     * `getPasskeyList` — enumerate registered credentials for the
     * current user. NOTE: no first-party caller in `passkey.js`;
     * `UserPasskeyRepository::renderList()` calls the repository
     * directly. The endpoint is migrated for parity in case
     * third-party integrations rely on it.
     */
    public function getList(): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        try {
            $list = UserPasskeyRepository::getList($user->id);
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse($list);
    }

    /**
     * `getPasskeyGetArgs` — emit a fresh `getGetArgs` payload + cached
     * challenge id for the assertion ceremony. Login flow: the caller
     * is not yet authenticated, so the route MUST stay outside
     * `auth.nexus:nexus-web`.
     */
    public function getGetArgs(): JsonResponse
    {
        try {
            $data = UserPasskeyRepository::getGetArgs();
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse($data);
    }

    /**
     * `processPasskeyGet` — verify the assertion, identify the user
     * via the stored credential, and mint the auth cookie via
     * `logincookie()`. Login flow: the caller is not yet authenticated.
     */
    public function processGet(Request $request): JsonResponse
    {
        $params = $this->params($request);

        try {
            UserPasskeyRepository::processGet(
                $params['challengeId'] ?? null,
                $params['id'] ?? null,
                $params['clientDataJSON'] ?? null,
                $params['authenticatorData'] ?? null,
                $params['signature'] ?? null,
                $params['userHandle'] ?? null,
            );
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
     * @return array<string,mixed>
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

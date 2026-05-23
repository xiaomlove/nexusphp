<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Repositories\BonusRepository;
use App\Repositories\MedalRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Phase 2.5 (this PR / batch B of the `public/ajax.php` cleanup):
 * replaces 4 of the remaining `AjaxInterface::*` static methods that
 * the legacy reflection-dispatcher in `public/ajax.php` exposes —
 * the medal sub-API (buy / gift / showcase-toggle / showcase-save).
 *
 * The four wire-level contracts are preserved verbatim — same
 * accepted POST keys (`params[*]`), same `{ret, msg, data}` JSON
 * envelope at HTTP 200 (including on error), so the first-party JS
 * callers keep working after a single one-line `apiUrl` flip
 * inside each:
 *
 *   - `app/Http/Controllers/Legacy/MedalController.php:154` (the
 *     "Buy medal" button on `/medal.php`),
 *   - `app/Http/Controllers/Legacy/MedalController.php:165` (the
 *     "Gift medal" button on `/medal.php`),
 *   - `public/userdetails.php:83` (the "Save chosen medals" form on
 *     `/userdetails.php?id=<self>`).
 *
 * Action map (legacy → Laravel route, all authed):
 *   - `toggleUserMedalStatus`  → POST /medal/toggle-status
 *   - `buyMedal`               → POST /medal/buy
 *   - `giftMedal`              → POST /medal/gift
 *   - `saveUserMedal`          → POST /medal/save-user
 *
 * `toggleUserMedalStatus` has no first-party JS caller in the tree
 * (the `MedalRepository::toggleUserMedalStatus()` method itself is
 * only invoked through the HTTP fasade); it is migrated for parity
 * in case third-party integrations depend on the legacy URL.
 *
 * Auth posture: all four routes sit behind `auth.nexus:nexus-web`,
 * mirroring the `loggedinorreturn()` gate in the legacy dispatcher.
 *
 * CSRF carve-out lives in `App\Http\Middleware\VerifyCsrfToken::$except`
 * (`'medal/*'`) — the legacy XHR posts a bare `application/x-www-
 * form-urlencoded` body with no `_token`. Adding CSRF plumbing to
 * the inline-script JS (`MedalController` + `userdetails.php`) is a
 * separate, larger change.
 *
 * Error shape: every catch path returns HTTP 200 with `{ret: -1,
 * msg, data: []}`, mirroring `public/ajax.php`'s
 * `exit(json_encode(fail($exception->getMessage(), $_POST)))`. The
 * three JS call-sites read `response.ret !== 0` and `layer.alert
 * (response.msg)` — the legacy XHR error UX is preserved without
 * a JS change.
 */
class MedalAjaxController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
        private readonly MedalRepository $medals,
        private readonly BonusRepository $bonus,
    ) {}

    /**
     * `toggleUserMedalStatus` — flip a single `user_medals` row
     * between active / inactive. Owned by the current user.
     */
    public function toggleStatus(Request $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        $params = $this->params($request);

        try {
            $result = $this->medals->toggleUserMedalStatus(
                $params['id'] ?? null,
                $user->id,
            );
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse($result);
    }

    /**
     * `buyMedal` — debit `users.seedbonus` and grant the medal to
     * the current user.
     */
    public function buy(Request $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        $params = $this->params($request);

        try {
            $result = $this->bonus->consumeToBuyMedal(
                $user->id,
                $params['medal_id'] ?? null,
            );
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse($result);
    }

    /**
     * `giftMedal` — debit `users.seedbonus` of the current user and
     * grant the medal to another user identified by `params.uid`.
     */
    public function gift(Request $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        $params = $this->params($request);

        try {
            $result = $this->bonus->consumeToGiftMedal(
                $user->id,
                $params['medal_id'] ?? null,
                $params['uid'] ?? null,
            );
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse($result);
    }

    /**
     * `saveUserMedal` — persist the showcase preferences (priority,
     * expiry hint) for the current user's owned medals. Receives a
     * jQuery `form.serializeArray()` payload — a list of `{name,
     * value}` entries where each `name` is `<field>_<userMedalId>`.
     *
     * Parsing logic mirrors `public/ajax.php` verbatim — the
     * (field, userMedalId) split happens here rather than inside
     * the repository so the migration is wire-compatible.
     */
    public function saveUserMedal(Request $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        $rows = $this->params($request);

        try {
            $data = [];
            foreach ($rows as $row) {
                if (! is_array($row) || ! isset($row['name'], $row['value'])) {
                    continue;
                }
                $fieldAndId = explode('_', (string) $row['name']);
                if (count($fieldAndId) < 2) {
                    continue;
                }
                $field = $fieldAndId[0];
                $id = $fieldAndId[1];
                $data[$id][$field] = $row['value'];
            }

            $result = $this->medals->saveUserMedal($user->id, $data);
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

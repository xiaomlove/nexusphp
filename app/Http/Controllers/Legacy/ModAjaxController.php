<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Offer;
use App\Repositories\TorrentRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nexus\Database\NexusDB;
use Throwable;

/**
 * Phase 2.5 (this PR / batch E of the `public/ajax.php` cleanup):
 * replaces 5 of the remaining `AjaxInterface::*` static methods that
 * the legacy reflection-dispatcher in `public/ajax.php` exposes —
 * the mod/admin sub-API (approval, offers, leech-warn, shoutbox).
 *
 * The five wire-level contracts are preserved verbatim — same
 * accepted POST keys (`params[*]`), same `{ret, msg, data}` JSON
 * envelope at HTTP 200 (including on error), so the first-party JS
 * callers keep working after a single `jQuery.post(...)` URL flip:
 *
 *   - `public/userdetails.php` (the "Remove leech warn" button),
 *   - `public/index.php` (the "Clear shout box" button for sbmanage
 *     users).
 *
 * Action map (legacy → Laravel route, all authed):
 *   - `removeUserLeechWarn`  → POST /mod/remove-leech-warn
 *   - `getOffer`             → POST /mod/get-offer
 *   - `approvalModal`        → POST /mod/approval-modal
 *   - `approval`             → POST /mod/approval
 *   - `clearShoutBox`        → POST /mod/clear-shoutbox
 *
 * `getOffer`, `approvalModal`, and `approval` have no first-party JS
 * callers via `ajax.php` in the current tree — the modern UI uses
 * `/web/torrent-approval-page` and `/web/torrent-approval` already.
 * They are migrated for parity in case third-party integrations rely
 * on the legacy URLs.
 *
 * Auth posture: all five routes sit behind `auth.nexus:nexus-web`,
 * mirroring the `loggedinorreturn()` gate in the legacy dispatcher.
 *
 * CSRF carve-out lives in `App\Http\Middleware\VerifyCsrfToken::$except`
 * (`'mod/*'`) — the legacy XHR posts a bare `application/x-www-form-
 * urlencoded` body with no `_token`. Adding CSRF plumbing to the
 * inline-script JS is a separate, larger change.
 *
 * Error shape: every catch path returns HTTP 200 with `{ret: -1,
 * msg, data: []}`, mirroring `public/ajax.php`'s
 * `exit(json_encode(fail($exception->getMessage(), $_POST)))`.
 */
class ModAjaxController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
    ) {}

    /**
     * `removeUserLeechWarn` — remove leech warning for a target user.
     * Caller: `public/userdetails.php` inline JS.
     */
    public function removeLeechWarn(Request $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        $params = $this->params($request);

        try {
            $rep = new UserRepository;
            $result = $rep->removeLeechWarn($user->id, $params['uid'] ?? null);
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse($result);
    }

    /**
     * `getOffer` — fetch a single offer by ID. No first-party JS
     * caller in the tree; migrated for parity.
     */
    public function getOffer(Request $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        $params = $this->params($request);

        try {
            $offer = Offer::query()->findOrFail($params['id'] ?? null);
            $result = $offer->toArray();
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse($result);
    }

    /**
     * `approvalModal` — build the torrent approval modal data. No
     * first-party JS caller via `ajax.php` — `details.php` now uses
     * `/web/torrent-approval-page`. Migrated for parity.
     */
    public function approvalModal(Request $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        $params = $this->params($request);

        try {
            $rep = new TorrentRepository;
            $result = $rep->buildApprovalModal($user->id, $params['torrent_id'] ?? null);
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse($result);
    }

    /**
     * `approval` — process a torrent approval decision. No first-
     * party JS caller via `ajax.php` — the modern UI posts to
     * `/web/torrent-approval`. Migrated for parity.
     */
    public function approval(Request $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        $params = $this->params($request);

        foreach (['torrent_id', 'approval_status'] as $field) {
            if (! isset($params[$field])) {
                return $this->failResponse("Require $field");
            }
        }

        try {
            $rep = new TorrentRepository;
            $result = $rep->approval($user->id, $params);
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse($result);
    }

    /**
     * `clearShoutBox` — truncate the shoutbox table. Requires
     * `sbmanage` permission.
     * Caller: `public/index.php` inline JS.
     */
    public function clearShoutBox(Request $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        try {
            user_can('sbmanage', true);
            NexusDB::table('shoutbox')->delete();
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse(true);
    }

    /**
     * Pull the legacy `params[*]` POST array, normalised to a plain
     * `string => mixed` map.
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

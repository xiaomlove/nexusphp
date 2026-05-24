<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Repositories\AttendanceRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Nexus\PTGen\PTGen;
use Throwable;

/**
 * Phase 2.5 (this PR / batch E of the `public/ajax.php` cleanup):
 * replaces the last 2 user-facing utility `AjaxInterface::*` static
 * methods that the legacy reflection-dispatcher in `public/ajax.php`
 * exposes — PTGen generation and attendance retroactive sign-in.
 *
 * The two wire-level contracts are preserved verbatim — same accepted
 * POST keys (`params[*]`), same `{ret, msg, data}` JSON envelope at
 * HTTP 200 (including on error), so the first-party JS callers keep
 * working after a single `jQuery.post(...)` URL flip:
 *
 *   - `public/js/ptgen.js` (the "Get PT-Gen info" button on upload/
 *     edit forms),
 *   - `app/Http/Controllers/Legacy/AttendanceController.php:351`
 *     (the retroactive sign-in day click handler).
 *
 * Action map (legacy → Laravel route, all authed):
 *   - `getPtGen`                → POST /misc/pt-gen
 *   - `attendanceRetroactive`   → POST /misc/attendance-retroactive
 *
 * Auth posture: both routes sit behind `auth.nexus:nexus-web`,
 * mirroring the `loggedinorreturn()` gate in the legacy dispatcher.
 *
 * CSRF carve-out lives in `App\Http\Middleware\VerifyCsrfToken::$except`
 * (`'misc/*'`) — the legacy XHR posts a bare `application/x-www-form-
 * urlencoded` body with no `_token`. Adding CSRF plumbing to the
 * inline-script JS is a separate, larger change.
 *
 * Error shape: every catch path returns HTTP 200 with `{ret: -1,
 * msg, data: []}`, mirroring `public/ajax.php`'s
 * `exit(json_encode(fail($exception->getMessage(), $_POST)))`.
 */
class MiscAjaxController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
    ) {}

    /**
     * `getPtGen` — generate PT-Gen info from a URL (douban, imdb,
     * etc.). Caller: `public/js/ptgen.js`.
     */
    public function getPtGen(Request $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        $params = $this->params($request);

        try {
            $rep = new PTGen;
            $result = $rep->generate($params['url'] ?? '');

            if ($rep->isRawPTGen($result)) {
                $data = $result;
            } elseif ($rep->isIyuu($result)) {
                $data = $result['data'];
            } else {
                $data = '';
            }
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse($data);
    }

    /**
     * `attendanceRetroactive` — retroactively sign in for a missed
     * day. Caller: `AttendanceController.php` inline JS (the
     * FullCalendar day-click handler).
     */
    public function attendanceRetroactive(Request $request): JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            return $this->failResponse('Permission denied.', status: 401);
        }

        $params = $this->params($request);

        try {
            $rep = new AttendanceRepository;
            $result = $rep->retroactive($user->id, $params['date'] ?? null);
        } catch (Throwable $e) {
            return $this->failResponse($e->getMessage());
        }

        return $this->okResponse($result);
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

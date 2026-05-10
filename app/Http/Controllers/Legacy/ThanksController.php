<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Legacy\SayThanksRequest;
use App\Legacy\LegacyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/thanks.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — first canonical "full rewrite"
 * page; see `docs/legacy-strategy.md` § "Phase 2" and
 * `docs/migration-recipe.md` § "Worked example".
 *
 * Original legacy flow (see `public/thanks.php` in pre-migration history):
 *   1. `dbconn();` + `loggedinorreturn();` → bootstraps legacy globals.
 *   2. `if ($_GET['id'])` → 200 HTML stderr "Party is over!".
 *   3. `$torrentowner = NexusDB::table('torrents')->where('id', $id)->value('owner');`
 *   4. Existence check; if null → stderr("Invalid torrent id!").
 *   5. Duplicate-thanks check; if hit → stderr("You already said thanks!").
 *   6. INSERT into `thanks` + `KPS('+', $saythanks_bonus, $userid)` +
 *      `KPS('+', $receivethanks_bonus, $torrentowner)`.
 *
 * Replacement contract (this controller):
 *   - GET → 405 (route is POST-only; the legacy "Party is over" message
 *     was an anti-bot warning, not a feature).
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Validation failures (missing id, non-integer, unknown torrent) →
 *     422 with a typed error body, courtesy of `SayThanksRequest`.
 *   - Already thanked → 409 Conflict (typed error JSON / 409 rather
 *     than legacy stderr() HTML; the JS in `common.js` ignores the
 *     response body either way).
 *   - Success → 204 No Content. The JS already updates the DOM
 *     locally; it doesn't read the response body.
 *
 * Bonus seedbonus updates use the same helper that `KPS()` does
 * under the hood (NexusDB raw column update), preserving the exact
 * legacy arithmetic.
 */
class ThanksController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(SayThanksRequest $request): Response|JsonResponse
    {
        $user = $this->context->user();
        // The route's auth middleware guarantees we have a user, but
        // we re-assert it so the type system is happy and a
        // misconfigured route can't reach the bonus arithmetic with
        // a null user.
        if ($user === null) {
            return $this->errorResponse(401, 'Unauthenticated.');
        }

        $torrentId = (int) $request->validated('id');

        // Pull the owner directly so we keep the single round-trip the
        // legacy script had. We already validated `exists:torrents,id`
        // in the FormRequest, so a null here means a TOCTOU race —
        // surface it as 404 rather than crashing.
        $owner = (int) (NexusDB::table('torrents')->where('id', $torrentId)->value('owner') ?? 0);
        if ($owner === 0) {
            return $this->errorResponse(404, 'Torrent not found.');
        }

        $alreadyThanked = NexusDB::table('thanks')
            ->where('torrentid', $torrentId)
            ->where('userid', $user->id)
            ->exists();
        if ($alreadyThanked) {
            return $this->errorResponse(409, 'You already said thanks for this torrent.');
        }

        NexusDB::table('thanks')->insert([
            'torrentid' => $torrentId,
            'userid' => $user->id,
        ]);

        $this->awardBonus($user->id, (float) $this->context->setting('bonus.saythanks', 0));
        $this->awardBonus($owner, (float) $this->context->setting('bonus.receivethanks', 0));

        return response()->noContent();
    }

    /**
     * Build a typed-JSON error response. We bypass `abort($code, $msg)`
     * because the legacy `App\Exceptions\Handler::getHttpStatusCode`
     * collapses every `\RuntimeException` (which the Symfony
     * `HttpException` extends) to HTTP 200 — fixing that handler is
     * out of scope for the Phase 2 thanks rewrite.
     */
    private function errorResponse(int $status, string $message): JsonResponse
    {
        return new JsonResponse(['message' => $message], $status);
    }

    /**
     * Mirrors the legacy `KPS('+', $point, $id)` helper for the
     * "thanks" use case: only credit positive amounts, and only when
     * the global bonus toggle allows it. Negative or zero amounts are
     * silently ignored, matching the legacy semantics.
     */
    private function awardBonus(int $userId, float $points): void
    {
        if ($points <= 0.0) {
            return;
        }

        // Mirrors `$bonus_tweak = $TWEAK['bonus']` from
        // include/config.php — settings are persisted under the
        // `tweak.bonus` key (see settings table).
        $tweak = (string) $this->context->setting('tweak.bonus', 'enable');
        if ($tweak !== 'enable' && $tweak !== 'disablesave') {
            return;
        }

        NexusDB::table('users')->where('id', $userId)->update([
            'seedbonus' => NexusDB::raw('seedbonus + '.$points),
        ]);
    }
}

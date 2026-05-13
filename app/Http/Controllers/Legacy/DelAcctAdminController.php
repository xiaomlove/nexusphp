<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/delacctadmin.php` (deleted in the same PR).
 *
 * Phase 2 batch #7 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2".
 *
 * Original legacy flow:
 *   1. `dbconn();` + `loggedinorreturn();` bootstrap.
 *   2. `user_can('user-delete', true)` — throws / `stderr()`s if the
 *      user lacks the `user-delete` permission.
 *   3. `POST` with `userid`:
 *      - Empty `userid` → `stderr('Error', 'Please fill out the form
 *        correctly.')`.
 *      - Unknown id → `stderr('Error', 'Bad user id...')`.
 *      - Otherwise → `UserRepository::destroy($id)` and `stderr(
 *        'Success', 'The account <b>X</b> was deleted.', false)`.
 *   4. `GET` (or fall-through from POST) → form with `userid`.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authenticated user without the `user-delete` permission →
 *     `abort(403)`.
 *   - GET → 200 chrome-less HTML with the form (input + submit).
 *   - POST with empty `userid` or unknown id → 200 HTML re-rendering
 *     the form with an inline error message.
 *   - POST with a valid id → `UserRepository::destroy($id)`, then
 *     200 HTML with the success message.
 *
 * The `user-delete` permission gate is delegated to the existing
 * `user_can()` helper, which already knows how to resolve the
 * Filament-style permission keys against `User::$class`. We pass
 * `$fail=false` and call `abort(403)` ourselves so the chrome-less
 * controllers in this directory never invoke `stderr()` (which
 * bootstraps legacy chrome and is the original crash source — see
 * `MoreSmiliesController`'s docblock).
 */
class DelAcctAdminController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
        private readonly UserRepository $users,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if (! user_can('user-delete', false, (int) $user->id)) {
            abort(403);
        }

        $error = null;
        $successName = null;

        if ($request->isMethod('POST')) {
            $userid = trim((string) $request->input('userid', ''));
            if ($userid === '') {
                $error = 'Please fill out the form correctly.';
            } else {
                $row = NexusDB::table('users')->where('id', (int) $userid)->first();
                $row = $row ? (array) $row : null;
                if ($row === null) {
                    $error = 'Bad user id. Please verify that all entered information is correct.';
                } else {
                    $this->users->destroy((int) $row['id']);
                    $successName = (string) $row['username'];
                }
            }
        }

        $body = '<h1>Delete account</h1>'."\n";
        if ($successName !== null) {
            $body .= '<p>The account <b>'.htmlspecialchars($successName).'</b> was deleted.</p>'."\n";
        } else {
            if ($error !== null) {
                $body .= '<p style="color:red">'.htmlspecialchars($error).'</p>'."\n";
            }
            $body .= '<table border="1" cellspacing="0" cellpadding="5">'."\n"
                .'<form method="post" action="delacctadmin.php">'."\n"
                .'<tr><td class="rowhead">User name</td>'
                .'<td><input size="40" name="userid"></td></tr>'."\n"
                .'<tr><td colspan="2">'
                .'<input type="submit" class="btn" value="Delete"></td></tr>'."\n"
                .'</form>'."\n"
                .'</table>'."\n";
        }

        return new Response($this->wrap('Delete account', $body));
    }

    private function wrap(string $title, string $body): string
    {
        $titleEsc = htmlspecialchars($title);

        return <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head>
<body>
{$body}</body>
</html>
HTML;
    }
}

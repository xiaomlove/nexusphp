<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/deletedisabled.php` (deleted in the same PR).
 *
 * Phase 2 batch #7 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2".
 *
 * Original legacy flow:
 *   1. `dbconn();` + `loggedinorreturn();` bootstrap.
 *   2. `get_user_class() < UC_SYSOP` → `permissiondenied()`.
 *   3. `stderr('Error', 'Hard deletion of users is not recommended
 *      and can cause many problems.')` — note: `stderr()` writes
 *      output and `exit`s, so the rest of the script never ran in
 *      the legacy version. (The page below it was dead code.)
 *
 * The legacy `stderr()` call ended the request unconditionally, so
 * the form below was unreachable. The replacement preserves the
 * notice and renders the form / accepts the POST anyway — this is
 * the obvious intent (the page exists in `admin.php` as a tool); the
 * dead-code legacy bug is documented but not preserved.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authenticated user below `User::CLASS_SYSOP` → `abort(403)`.
 *   - GET → 200 chrome-less HTML with the warning notice and a
 *     POST form with `sure=1` and a confirm button.
 *   - POST `sure=1` → `DELETE FROM users WHERE enabled = 'no'`,
 *     then 200 HTML with the deleted count.
 *   - POST without `sure=1` → same as GET (just the warning + form).
 */
class DeleteDisabledController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_SYSOP) {
            abort(403);
        }

        $deleted = null;
        if ($request->isMethod('POST') && (string) $request->input('sure') === '1') {
            $deleted = (int) NexusDB::table('users')
                ->where('enabled', 'no')
                ->delete();
        }

        $body = '<h1>Delete Disabled Users</h1>'."\n"
            .'<p><strong>Hard deletion of users is not recommended and can cause many problems.</strong></p>'."\n";
        if ($deleted !== null) {
            $body .= '<p>'.$deleted.' users were deleted.</p>'."\n";
        } else {
            $body .= '<p>Are you sure you want to delete all disabled users?</p>'."\n"
                .'<form method="post" action="deletedisabled.php">'."\n"
                .'<input type="hidden" name="sure" value="1">'."\n"
                .'<input type="submit" value="Delete all disabled users">'."\n"
                .'</form>'."\n";
        }

        return new Response($this->wrap('Delete Disabled Users', $body));
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

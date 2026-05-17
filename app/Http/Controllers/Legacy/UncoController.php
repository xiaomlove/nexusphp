<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/unco.php` (deleted in the same PR).
 *
 * Phase 2 batch #12 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2".
 *
 * Original legacy flow (60 LOC):
 *   1. `dbconn();` + `loggedinorreturn();`.
 *   2. `get_user_class() < UC_MODERATOR` → `stderr('Sorry',
 *      'Access denied.')`.
 *   3. `$status = $_GET['status'];` + `int_check($status, true)` if
 *      set — the value is a flag set by `modtask.php` after a
 *      successful confirm/reject and is only used to render the
 *      "user updated" notice; it is never read as an integer
 *      identifier despite the `int_check` call.
 *   4. `SELECT * FROM users WHERE status='pending' ORDER BY
 *      username`.
 *   5. Non-empty:
 *      - `stdhead('Unconfirmed Users')` + `begin_main_frame` +
 *        `begin_frame()` chrome.
 *      - One `<tr>` per pending user: `(username, email, added,
 *        <select pending|confirmed>, [-Go-])` posting to
 *        `modtask.php?action=confirmuser&userid={id}&confirm={...}`.
 *      - `end_frame()` + `end_main_frame()` + `stdfoot()`.
 *   6. Empty:
 *      - `stderr('Updated!', 'The user account has been updated.')`
 *        if `$status` was truthy, else
 *      - `stderr('Ups!', 'Nothing Found...')`.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authenticated user below `User::CLASS_MODERATOR` → `abort(403)`.
 *     The legacy `stderr()` rendered HTTP 200; tightened in line
 *     with the rest of Phase 2.
 *   - Pending users present → 200 chrome-less HTML with the
 *     `<form>` table (one `<form action="modtask.php">` per pending
 *     row). The `?status=...` query, when present, adds the "user
 *     account has been updated" banner above the table.
 *   - No pending users → 200 chrome-less HTML with either the
 *     "Updated!" notice (if `?status=...` was set) or the
 *     "Nothing Found..." fallback.
 *
 * `modtask.php` is still on the legacy stack and lives at the same
 * URL it always did; this controller renders forms that point at
 * `/modtask.php?action=confirmuser` verbatim, so the existing
 * confirm flow keeps working without touching `modtask.php`.
 */
class UncoController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_MODERATOR) {
            abort(403);
        }

        $hasStatus = $request->query('status') !== null;

        $rows = NexusDB::table('users')
            ->where('status', User::STATUS_PENDING)
            ->orderBy('username')
            ->get(['id', 'username', 'email', 'added']);

        if ($rows->isEmpty()) {
            if ($hasStatus) {
                return new Response($this->wrap(
                    'Unconfirmed Users',
                    $this->notice('Updated!', 'The user account has been updated.'),
                ));
            }

            return new Response($this->wrap(
                'Unconfirmed Users',
                $this->notice('Ups!', 'Nothing Found...'),
            ));
        }

        $body = '<h1>Unconfirmed Users</h1>'."\n";
        if ($hasStatus) {
            $body .= '<p><font color="red" size="1">'
                .'The User account has been updated!'
                .'</font></p>'."\n";
        }
        $body .= '<table width="100%" border="1" cellspacing="0" cellpadding="5">'."\n"
            .'<tr>'
            .'<td class="rowhead"><center>Name</center></td>'
            .'<td class="rowhead"><center>eMail</center></td>'
            .'<td class="rowhead"><center>Added</center></td>'
            .'<td class="rowhead"><center>Set Status</center></td>'
            .'<td class="rowhead"><center>Confirm</center></td>'
            .'</tr>'."\n";

        foreach ($rows as $row) {
            $arr = (array) $row;
            $id = (int) ($arr['id'] ?? 0);
            $usernameEsc = htmlspecialchars(
                (string) ($arr['username'] ?? ''),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            );
            $emailEsc = htmlspecialchars(
                (string) ($arr['email'] ?? ''),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            );
            $addedEsc = htmlspecialchars(
                (string) ($arr['added'] ?? ''),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            );
            $body .= '<tr><form method="post" action="modtask.php">'
                .'<input type="hidden" name="action" value="confirmuser">'
                .'<input type="hidden" name="userid" value="'.$id.'">'
                .'<td><center><a href="userdetails.php?id='.$id.'">'.$usernameEsc.'</a></center></td>'
                .'<td align="center">'.$emailEsc.'</td>'
                .'<td align="center">'.$addedEsc.'</td>'
                .'<td align="center"><select name="confirm">'
                .'<option value="pending">pending</option>'
                .'<option value="confirmed">confirmed</option>'
                .'</select></td>'
                .'<td align="center"><input type="submit" value="-Go-" style="height: 20px; width: 40px"></td>'
                .'</form></tr>'."\n";
        }
        $body .= '</table>'."\n";

        return new Response($this->wrap('Unconfirmed Users', $body));
    }

    private function notice(string $title, string $message): string
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $messageEsc = htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return '<h1 align="center">'.$titleEsc.'</h1>'."\n"
            .'<p align="center">'.$messageEsc.'</p>'."\n";
    }

    private function wrap(string $title, string $body): string
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

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

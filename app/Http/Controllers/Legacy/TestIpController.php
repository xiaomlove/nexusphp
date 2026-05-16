<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/testip.php` (deleted in the same PR).
 *
 * Phase 2 batch of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2" and `docs/migration-recipe.md`.
 *
 * Moderator-only IP-ban check tool. Takes an IPv4 address, looks
 * for a row in the `bans` table whose `first` / `last` range
 * contains it, and renders either the matching ban rows or a
 * "not banned" notice.
 *
 * Original legacy flow (`public/testip.php`, 56 LOC):
 *   1. `dbconn();` + `loggedinorreturn();`.
 *   2. `get_user_class() < UC_MODERATOR` → `stderr('Error', 'Permission denied')`.
 *   3. Read `ip` from `$_POST` (when REQUEST_METHOD = POST) or `$_GET`.
 *   4. If `ip` is set:
 *        a. `$nip = ip2long($ip)`; `if ($nip == -1) stderr('Error', 'Bad IP.')`
 *           (note: PHP 8 `ip2long` returns `false` for an invalid
 *           address, never `-1` — the legacy `== -1` check was dead
 *           code on modern PHP; this controller upgrades it to a
 *           proper `=== false` check + `filter_var(..., FILTER_VALIDATE_IP)`
 *           pre-check so the "Bad IP." branch can actually fire).
 *        b. `SELECT * FROM bans WHERE first <= ? AND last >= ?`.
 *        c. Empty result → `stderr('Result', 'The IP address <b>X</b> is not banned.', false)`.
 *        d. Non-empty → `stderr('Result', '...<table>'+rows+'</table>', false)`.
 *   5. Render the form via `stdhead()` + `<form method=post action=testip.php>` + `stdfoot()`.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authenticated user below `User::CLASS_MODERATOR` → `abort(403)`
 *     (legacy was HTTP 200 / `stderr()` body; same hardening as
 *     every other Phase 2 controller).
 *   - GET / POST without `ip` → 200 chrome-less HTML form.
 *   - GET / POST with `ip` but invalid → 200 chrome-less "Bad IP." page.
 *   - GET / POST with valid `ip` and no `bans` hits → 200 chrome-less
 *     "The IP address <b>X</b> is not banned." page.
 *   - GET / POST with valid `ip` and one or more `bans` hits → 200
 *     chrome-less page with a 3-column (First / Last / Comment) table.
 *   - The form `action="testip.php"` URL is preserved exactly so
 *     `public/usersearch.php` `[<a href='testip.php?ip=...'>]` links
 *     and the modpanel "IP Test" menu entry (seeded in
 *     `ModpanelTableSeeder`) keep working without template changes.
 *   - The form keeps `method="post"`; same CSRF carve-out as
 *     `/donated.php` (the legacy form has no `@csrf` token).
 *
 * Intentional micro-changes (vs. legacy):
 *   - The "banned" result HTML-escapes the user-supplied `ip` value
 *     before splicing it into the page (legacy line 40 dropped the
 *     `htmlspecialchars` call by accident — a reflected XSS in a
 *     moderator-only tool, but worth fixing in passing).
 *   - "Bad IP." now actually fires for malformed IPv4 input (the
 *     legacy `== -1` check was broken on modern PHP — see above).
 */
class TestIpController extends Controller
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

        $ip = trim((string) $request->input('ip', ''));

        if ($ip === '') {
            return $this->render('Test IP address', $this->renderForm());
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return $this->render('Error', $this->renderErrorBlock('Bad IP.'));
        }

        $nip = ip2long($ip);
        if ($nip === false) {
            return $this->render('Error', $this->renderErrorBlock('Bad IP.'));
        }

        $rows = NexusDB::table('bans')
            ->where('first', '<=', (int) $nip)
            ->where('last', '>=', (int) $nip)
            ->get();

        if ($rows->count() === 0) {
            return $this->render(
                'Result',
                $this->renderNotice('The IP address <b>'.htmlspecialchars($ip).'</b> is not banned.'),
            );
        }

        return $this->render(
            'Result',
            $this->renderBannedNotice($ip, $rows->all()),
        );
    }

    /**
     * Render the legacy moderator form. The `action="testip.php"`
     * URL preserves existing links from `public/usersearch.php` and
     * the modpanel menu entry.
     */
    private function renderForm(): string
    {
        return '<h1>Test IP address</h1>'."\n"
            .'<form method="post" action="testip.php">'."\n"
            .'<table border="1" cellspacing="0" cellpadding="5">'."\n"
            .'<tr><td class="rowhead">IP address</td>'
            .'<td><input type="text" name="ip"></td></tr>'."\n"
            .'<tr><td colspan="2" align="center">'
            .'<input type="submit" class="btn" value="OK"></td></tr>'."\n"
            .'</table>'."\n"
            .'</form>'."\n";
    }

    /**
     * Render a plain notice block. Used for "not banned" and the
     * "Bad IP." error — preserves the legacy `stderr('Result',
     * ..., false)` markup (a centered `<h2>` title + body) without
     * the legacy chrome.
     */
    private function renderNotice(string $bodyHtml): string
    {
        return '<h2 align="center">Result</h2>'."\n"
            .'<p align="center">'.$bodyHtml.'</p>'."\n";
    }

    private function renderErrorBlock(string $message): string
    {
        return '<h2 align="center">Error</h2>'."\n"
            .'<p align="center"><font class="striking">'
            .htmlspecialchars($message).'</font></p>'."\n";
    }

    /**
     * Render the "banned" result page: a 3-column table of matching
     * `bans` rows (`first` / `last` / `comment`).
     *
     * Fix-in-passing: legacy emitted `<b>'.$ip.'</b>` without
     * `htmlspecialchars` for the matching rows page (a reflected
     * XSS in a moderator-only tool; line 40 of the original file).
     * The migrated version escapes consistently with the
     * "not banned" branch.
     *
     * @param  array<int,object|array<string,mixed>>  $rows
     */
    private function renderBannedNotice(string $ip, array $rows): string
    {
        $ipEsc = htmlspecialchars($ip);

        $banTable = '<table class="main" border="0" cellspacing="0" cellpadding="5">'."\n"
            .'<tr><td class="colhead">First</td>'
            .'<td class="colhead">Last</td>'
            .'<td class="colhead">Comment</td></tr>'."\n";

        foreach ($rows as $row) {
            $arr = (array) $row;
            $first = long2ip((int) $arr['first']);
            $last = long2ip((int) $arr['last']);
            $comment = htmlspecialchars((string) ($arr['comment'] ?? ''));
            $banTable .= '<tr><td>'.htmlspecialchars((string) $first).'</td>'
                .'<td>'.htmlspecialchars((string) $last).'</td>'
                .'<td>'.$comment.'</td></tr>'."\n";
        }

        $banTable .= '</table>'."\n";

        return '<h2 align="center">Result</h2>'."\n"
            .'<table border="0" cellspacing="0" cellpadding="0">'
            .'<tr><td class="embedded">'
            .'The IP address <b>'.$ipEsc.'</b> is banned:</td></tr></table>'."\n"
            .'<p>'.$banTable.'</p>'."\n";
    }

    private function render(string $title, string $body): Response
    {
        $titleEsc = htmlspecialchars($title);

        return new Response(<<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head>
<body>
{$body}</body>
</html>
HTML);
    }
}

<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/donorlist.php` (deleted in the same PR).
 *
 * Phase 2 batch #9 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2".
 *
 * Original legacy flow:
 *   1. `dbconn();` + `loggedinorreturn();` bootstrap.
 *   2. `get_user_class() > UC_MODERATOR` gate — only Administrator+
 *      (class > 13, i.e. >= 14) saw the listing; everyone else hit
 *      the trailing `stderr("Sorry", "Access denied!")` branch.
 *   3. `SELECT COUNT(*) FROM users WHERE donor='yes'` + `pager(50, ...)`.
 *   4. `stdhead("Donorlist") + begin_main_frame() + begin_frame()` chrome.
 *   5. For each donor row in the current page, render a `<tr>` with
 *      `id / get_username($id) / mailto:email / added / $donated`.
 *   6. `end_*` + `stdfoot();`.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authenticated user below `User::CLASS_ADMINISTRATOR` → `abort(403)`.
 *     The legacy script rendered HTTP 200 with an `stderr()` body,
 *     which is the same anti-pattern the rest of Phase 2 replaces
 *     with a real 403 (see `FreeleechController` /
 *     `TakeUpdateController` docblocks for the rationale).
 *   - Administrator+ → 200 with a chrome-less, self-contained HTML
 *     envelope wrapping the donor table. Matches the precedent set
 *     by `MoreSmiliesController` / `AllAgentsController` /
 *     `RulesController` — the legacy `stdhead()` / `stdfoot()` chrome
 *     is not reproduced; this is an internal admin tool and the
 *     original page used the chrome only as a viewport.
 *
 * Pagination is preserved (50 rows per page via `?page=<n>`) so
 * existing bookmarks / deep links keep working. The username column
 * is rendered as a plain `<a href="userdetails.php?id={id}">{name}</a>`
 * link rather than going through the legacy `get_username()` helper —
 * that helper renders class-styled HTML pulled from the in-process
 * `$usernameArray` static, which we deliberately do not import here
 * to keep the controller chrome-less and side-effect-free. The
 * observable link target is identical.
 */
class DonorlistController extends Controller
{
    /** Rows per page. Matches the legacy `pager(50, ...)` argument. */
    private const PER_PAGE = 50;

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_ADMINISTRATOR) {
            abort(403);
        }

        $count = (int) NexusDB::table('users')->where('donor', 'yes')->count();
        $page = max(0, (int) $request->query('page', 0));
        $offset = $page * self::PER_PAGE;

        $rows = NexusDB::table('users')
            ->where('donor', 'yes')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit(self::PER_PAGE)
            ->get(['id', 'username', 'email', 'added', 'donated']);

        $countFormatted = number_format($count);
        $body = '<h1>Donor List ('.$countFormatted.')</h1>'."\n"
            .'<table align="center" border="1" cellspacing="0" cellpadding="5">'."\n"
            .'<tr><td class="colhead">ID</td>'
            .'<td class="colhead" align="left">Username</td>'
            .'<td class="colhead" align="left">e-mail</td>'
            .'<td class="colhead" align="left">Joined</td>'
            .'<td class="colhead" align="left">How much?</td></tr>'."\n";

        foreach ($rows as $row) {
            $arr = (array) $row;
            $id = (int) ($arr['id'] ?? 0);
            $username = htmlspecialchars((string) ($arr['username'] ?? ''));
            $email = htmlspecialchars((string) ($arr['email'] ?? ''));
            $added = htmlspecialchars((string) ($arr['added'] ?? ''));
            $donated = htmlspecialchars((string) ($arr['donated'] ?? ''));
            $body .= '<tr>'
                .'<td>'.$id.'</td>'
                .'<td align="left"><a href="userdetails.php?id='.$id.'">'.$username.'</a></td>'
                .'<td align="left"><a href="mailto:'.$email.'">'.$email.'</a></td>'
                .'<td align="left">'.$added.'</td>'
                .'<td align="left">$'.$donated.'</td>'
                .'</tr>'."\n";
        }

        $body .= '</table>'."\n"
            .$this->renderPager($count, $page);

        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Donorlist</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }

    /**
     * Render a minimal `Prev / N / Next` pager block. The legacy
     * `pager()` helper renders a wider control with shortcuts and
     * jump links, which depends on `$lang_functions` and a CSS class
     * set the chrome-less envelope does not load. The simplified
     * controls below preserve the observable next/prev links so the
     * page is still navigable.
     */
    private function renderPager(int $count, int $page): string
    {
        if ($count <= self::PER_PAGE) {
            return '';
        }
        $totalPages = (int) ceil($count / self::PER_PAGE);
        $links = '';
        if ($page > 0) {
            $links .= '<a href="donorlist.php?page='.($page - 1).'">&lt;&lt; Prev</a> ';
        }
        $links .= '<b>'.($page + 1).' / '.$totalPages.'</b>';
        if ($page + 1 < $totalPages) {
            $links .= ' <a href="donorlist.php?page='.($page + 1).'">Next &gt;&gt;</a>';
        }

        return '<p align="center">'.$links.'</p>'."\n";
    }
}

<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/polloverview.php` (deleted in the same PR).
 *
 * Phase 2 batch of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2" and `docs/migration-recipe.md`.
 *
 * Administrator+ poll-overview tool. Two branches:
 *   - `?id=<n>`  → render a single poll: header row, option table,
 *     and a paginated table of voters (`pollanswers JOIN users`).
 *   - no `?id=`  → render the full list of polls ordered by id DESC.
 *
 * Original legacy flow (`public/polloverview.php`, 97 LOC):
 *   1. `dbconn();` + `require_once(get_langfile_path());` +
 *      `loggedinorreturn();`.
 *   2. `user_can('pollmanage', true);` — `AUTHORITY['pollmanage']`
 *      defaults to class 14 (Administrator) in `config/allconfig.php`
 *      and `nexus/Install/settings.default.php`. The legacy
 *      `user_can(..., true)` branch calls `stderr(...)` (which `exit()`s)
 *      before throwing `InsufficientPermissionException`.
 *   3. If `?id` is non-zero, look up the poll, dump 20 options into
 *      an array, render a 3-table screen (header, options, voters),
 *      paginate voters via `pager(100, $count, "?id=<n>&")`.
 *   4. Otherwise, list every poll with the same header table.
 *   5. `stdhead()` + `stdfoot()` for chrome.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `/login.php?returnto=...`.
 *   - Authenticated user below `User::CLASS_ADMINISTRATOR` →
 *     `abort(403)`. The legacy `stderr()` body is not reproduced —
 *     same hardening as every other Phase 2 controller
 *     (`DonorlistController`, `TakeUpdateController`, …). The runtime
 *     `user_can('pollmanage')` check is preserved as the gate so
 *     per-instance authority overrides for `pollmanage` continue to
 *     apply (calling it with `$fail = false` keeps the side-effect-
 *     free path; the `stderr()`/`exit()` branch is unreachable).
 *   - Admin with bad `?id` → 200 chrome-less "no poll with that ID"
 *     notice. Matches the legacy `stderr()` 200 response (the listing
 *     branch's empty-state used the same string).
 *   - Admin with `?id=<valid>` → 200 chrome-less screen: header row,
 *     options table, paginated voters table.
 *   - Admin with no `?id` → 200 chrome-less listing of polls; empty
 *     listing renders the same notice as the bad-`?id` branch.
 *
 * The `polloverview.php?id=<n>` URL is preserved exactly so the
 * `public/index.php:492` poll-digest deep link and the
 * `AdminpanelTableSeeder.url='polloverview.php'` menu entry keep
 * working without a template change. The `/polloverview.php` E2E
 * smoke spec (`tests/e2e/smoke/legacy-pages-extra.spec.ts`) is
 * unaffected for the same reason.
 *
 * UI strings: hard-coded English to keep the chrome-less envelope
 * self-contained. The `lang/<locale>/lang_polloverview.php` files
 * stay in tree — `makepoll.php` (the parent admin-write page) still
 * loads them via `get_langfile_path()`, and a future Phase 5 chrome
 * pass can swap the strings here back through `$lang_polloverview`.
 * This is the same trade-off `RulesController` makes for `lang_rules`.
 */
class PollOverviewController extends Controller
{
    /** Rows per page for the voter listing. Matches the legacy `pager(100, ...)`. */
    private const VOTERS_PER_PAGE = 100;

    /** Number of `optionN` columns on the `polls` table (option0 … option19). */
    private const OPTION_COUNT = 20;

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

        $pollId = (int) $request->query('id', 0);

        if ($pollId > 0) {
            return $this->renderPoll($pollId, $request);
        }

        return $this->renderList();
    }

    private function renderPoll(int $pollId, Request $request): Response
    {
        $poll = NexusDB::table('polls')->where('id', $pollId)->first();
        if ($poll === null) {
            return $this->renderEnvelope(
                'Polls Overview',
                '<h2 align="center">Error</h2>'."\n"
                .'<p align="center">Sorry...There are no polls with that ID!</p>'."\n",
            );
        }
        $pollArr = (array) $poll;

        $options = [];
        for ($i = 0; $i < self::OPTION_COUNT; $i++) {
            $options[$i] = (string) ($pollArr['option'.$i] ?? '');
        }

        $count = (int) NexusDB::table('pollanswers')
            ->where('pollid', $pollId)
            ->where('selection', '<', self::OPTION_COUNT)
            ->count();

        $page = max(0, (int) $request->query('page', 0));
        $offset = $page * self::VOTERS_PER_PAGE;

        $voters = NexusDB::table('pollanswers')
            ->leftJoin('users', 'pollanswers.userid', '=', 'users.id')
            ->where('pollanswers.pollid', $pollId)
            ->where('pollanswers.selection', '<', self::OPTION_COUNT)
            ->orderBy('users.username')
            ->offset($offset)
            ->limit(self::VOTERS_PER_PAGE)
            ->select(['pollanswers.userid', 'pollanswers.selection', 'users.username'])
            ->get();

        $body = '<h1 align="center">Polls Overview</h1>'."\n"
            .$this->renderHeaderTable([$pollArr])
            .'<h1 align="center">Poll Questions</h1><br />'."\n"
            .$this->renderOptionsTable($options)
            .'<h1 align="center">Polls User Overview</h1>'."\n"
            .$this->renderVotersTable($voters, $options, $count, $page, $pollId);

        return $this->renderEnvelope('Polls Overview', $body);
    }

    private function renderList(): Response
    {
        $polls = NexusDB::table('polls')
            ->select(['id', 'added', 'question'])
            ->orderByDesc('id')
            ->get();

        if ($polls->count() === 0) {
            return $this->renderEnvelope(
                'Polls Overview',
                '<h2 align="center">Error</h2>'."\n"
                .'<p align="center">Sorry...There are no users that voted!</p>'."\n",
            );
        }

        $rows = [];
        foreach ($polls as $row) {
            $rows[] = (array) $row;
        }

        $body = '<h1 align="center">Polls Overview</h1>'."\n"
            .$this->renderHeaderTable($rows);

        return $this->renderEnvelope('Polls Overview', $body);
    }

    /**
     * @param  array<int, array<string, mixed>>  $polls
     */
    private function renderHeaderTable(array $polls): string
    {
        $rows = '';
        foreach ($polls as $poll) {
            $id = (int) ($poll['id'] ?? 0);
            $added = htmlspecialchars((string) ($poll['added'] ?? ''));
            $question = htmlspecialchars((string) ($poll['question'] ?? ''));
            $rows .= '<tr>'
                .'<td align="center"><a href="polloverview.php?id='.$id.'">'.$id.'</a></td>'
                .'<td>'.$added.'</td>'
                .'<td><a href="polloverview.php?id='.$id.'">'.$question.'</a></td>'
                .'</tr>'."\n";
        }

        return '<table width="737" border="1" cellspacing="0" cellpadding="5">'."\n"
            .'<tr>'
            .'<td class="colhead" align="center"><nobr>ID</nobr></td>'
            .'<td class="colhead"><nobr>Added</nobr></td>'
            .'<td class="colhead"><nobr>Question</nobr></td>'
            .'</tr>'."\n"
            .$rows
            .'</table>'."\n";
    }

    /**
     * @param  array<int, string>  $options
     */
    private function renderOptionsTable(array $options): string
    {
        $rows = '';
        foreach ($options as $key => $value) {
            if ($value === '') {
                continue;
            }
            $rows .= '<tr>'
                .'<td>'.$key.'</td>'
                .'<td>'.htmlspecialchars($value).'</td>'
                .'</tr>'."\n";
        }

        return '<table width="737" border="1" cellspacing="0" cellpadding="5">'."\n"
            .'<tr>'
            .'<td class="colhead">Option No</td>'
            .'<td class="colhead">Options</td>'
            .'</tr>'."\n"
            .$rows
            .'</table>'."\n";
    }

    /**
     * @param  iterable<int, object|array<string, mixed>>  $voters
     * @param  array<int, string>  $options
     */
    private function renderVotersTable(iterable $voters, array $options, int $count, int $page, int $pollId): string
    {
        if ($count === 0) {
            return '<p align="center">Sorry...There are no users that voted!</p>'."\n";
        }

        $rows = '';
        foreach ($voters as $voter) {
            $arr = (array) $voter;
            $username = htmlspecialchars((string) ($arr['username'] ?? ''));
            $selection = (int) ($arr['selection'] ?? 0);
            $option = $options[$selection] ?? '';
            $rows .= '<tr>'
                .'<td>'.$username.'</td>'
                .'<td>'.htmlspecialchars($option).'</td>'
                .'</tr>'."\n";
        }

        return $this->renderPager($count, $page, $pollId)
            .'<table width="737" border="1" cellspacing="0" cellpadding="5">'."\n"
            .'<tr>'
            .'<td class="colhead" align="center"><nobr>Username</nobr></td>'
            .'<td class="colhead" align="center"><nobr>Selection</nobr></td>'
            .'</tr>'."\n"
            .$rows
            .'</table>'."\n"
            .$this->renderPager($count, $page, $pollId);
    }

    /**
     * Minimal `Prev / N / Next` pager block, preserving the current
     * `?id=<pollId>` so paginated views keep the same poll. The
     * legacy `pager()` helper rendered a wider control with jump
     * links and shortcuts that depended on `$lang_functions` and a
     * CSS class set the chrome-less envelope does not load — these
     * simplified controls preserve the observable next/prev links so
     * the page is still navigable. Same trade-off as
     * `DonorlistController` and `UserBanLogController`.
     */
    private function renderPager(int $count, int $page, int $pollId): string
    {
        if ($count <= self::VOTERS_PER_PAGE) {
            return '';
        }
        $totalPages = (int) ceil($count / self::VOTERS_PER_PAGE);
        $links = '';
        if ($page > 0) {
            $links .= '<a href="polloverview.php?id='.$pollId.'&amp;page='.($page - 1).'">&lt;&lt; Prev</a> ';
        }
        $links .= '<b>'.($page + 1).' / '.$totalPages.'</b>';
        if ($page + 1 < $totalPages) {
            $links .= ' <a href="polloverview.php?id='.$pollId.'&amp;page='.($page + 1).'">Next &gt;&gt;</a>';
        }

        return '<p align="center">'.$links.'</p>'."\n";
    }

    private function renderEnvelope(string $title, string $body): Response
    {
        $titleEsc = htmlspecialchars($title);
        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }
}

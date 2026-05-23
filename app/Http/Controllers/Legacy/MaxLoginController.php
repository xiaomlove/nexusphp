<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/maxlogin.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`. This is **part 3** of
 * the three-PR auth-flow batch:
 *
 *   - PR-A: login.php + takelogin.php (#304, merged)
 *   - PR-B: signup.php + takesignup.php + recover.php +
 *           confirm_resend.php (#305)
 *   - PR-C (this PR): maxlogin.php (admin failed-login admin tool)
 *
 * Sysop-class admin tool that lists / searches / edits the
 * `loginattempts` table. The companion to the per-IP ban gate that
 * `LoginController` / `TakeLoginController` / `RecoverController` /
 * `ConfirmResendController` enforce — when an IP gets stuck on
 * `banned='yes'`, this is the page a sysop visits to flip the row
 * back, raise the `attempts` count, or delete the record outright.
 *
 * Legacy actions
 * --------------
 * The single page handled seven actions via `$_POST['action'] ??
 * $_GET['action'] ?? 'showlist'`:
 *
 *   - `showlist`  (default) — paginated table of all `loginattempts`
 *                  rows, with per-row ban/unban/delete/edit links.
 *                  Sortable by `?order=<id|ip|added|attempts|type|status>`.
 *   - `ban`       — flip row's `banned` column to `yes`. Redirects to
 *                  `?update=Ban` so the success banner renders on
 *                  the next showlist hit.
 *   - `unban`     — flip row's `banned` column to `no`. Redirects to
 *                  `?update=Unban`.
 *   - `delete`    — delete row. Redirects to `?update=Delete`. The
 *                  legacy template wraps the link in a JS
 *                  `confirm()` dialog so this is intentionally a
 *                  GET despite being destructive (admin-only, no
 *                  CSRF since the form doesn't carry a token; CSRF
 *                  exemption listed in `VerifyCsrfToken::$except`
 *                  for the `save`-action POST below).
 *   - `edit`      — render single-row edit form (`<form
 *                  action=maxlogin.php>` with `action=save` hidden
 *                  field).
 *   - `save`      — POST handler for the edit form. Updates
 *                  `attempts`, `type`, `banned` on the row.
 *                  Redirects to `?update=Edit` (default) or
 *                  `?returnto=<viewunbaniprequest.php>` when set.
 *   - `searchip`  — POST search by `ip` LIKE `%input%`. Renders
 *                  the same per-row table as `showlist`, no pager.
 *
 * Anything else falls into the legacy `else { stderr('Error',
 * 'Invalid Action'); }` branch — we tighten this to `abort(400)`.
 *
 * Auth posture
 * ------------
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...` (matches the legacy
 *     `loggedinorreturn()`).
 *   - Authenticated user below `User::CLASS_SYSOP` → `abort(403)`.
 *     The legacy script `stderr('Error', 'Permission denied.')`-
 *     ed instead, returning a 200 envelope; we tighten this to
 *     match every other Phase 2 admin-tool controller.
 *
 * URL preservation
 * ----------------
 * The URL stays `/maxlogin.php` so the existing
 * `viewunbaniprequest.php` cross-link (`<form action="maxlogin.php"
 * with returnto>`) keeps working without a template change. Same
 * for any sysop bookmarks.
 *
 * CSRF
 * ----
 * `/maxlogin.php` POST is exempt — the legacy edit form has no
 * `@csrf` field. See `App\Http\Middleware\VerifyCsrfToken::$except`.
 *
 * Skipped legacy features
 * -----------------------
 * Same chrome-less envelope trade-off as every other Phase 2
 * controller — `stdhead()` / `stdfoot()` chrome (legacy logo / top
 * nav / footer) is not reproduced here because it depends on
 * top-level globals that the Laravel pipeline does not expose.
 * Phase 5 will replace with a Modern UI admin shell.
 */
class MaxLoginController extends Controller
{
    private const PER_PAGE = 50;

    /** @var array<string,string> */
    private const ORDER_COLUMNS = [
        'id' => 'id',
        'ip' => 'ip',
        'added' => 'added',
        'attempts' => 'attempts',
        'type' => 'type',
        'status' => 'banned',
    ];

    public function __construct(
        private readonly LegacyContext $context,
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_SYSOP) {
            abort(403);
        }

        // Legacy action resolver: `$_POST['action'] ??
        // $_GET['action'] ?? 'showlist'`. Both layers go through
        // `htmlspecialchars()` because the legacy script
        // interpolated the value into HTML further down.
        $action = (string) $request->input('action', $request->query('action', 'showlist'));
        $idRaw = (string) $request->input('id', $request->query('id', ''));

        return match ($action) {
            'showlist' => $this->renderShowList($request),
            'ban' => $this->doBan($idRaw),
            'unban' => $this->doUnban($idRaw),
            'delete' => $this->doDelete($idRaw),
            'edit' => $this->renderEdit($request, $idRaw),
            'save' => $this->doSave($request),
            'searchip' => $this->renderSearchIp($request),
            default => $this->renderInvalidAction(),
        };
    }

    private function renderShowList(Request $request): Response
    {
        $order = (string) $request->query('order', '');
        $orderBy = self::ORDER_COLUMNS[$order] ?? 'attempts';

        // Legacy `$countrows = number_format(...) + 1;` — the `+ 1`
        // shifts the page count by one row to mirror the off-by-
        // one the legacy pager has always had. We preserve it so
        // the page boundaries are identical to the legacy output.
        $countrows = (int) NexusDB::table('loginattempts')->count() + 1;

        // pager() returns [top, bottom, limit, offset, perPage].
        // Pure helper — works without `dbconn()` because it only
        // reads `$_GET['page']` and computes math.
        [$pagertop, $pagerbottom, , $offsetStart, $rowsPerPage] = pager(
            self::PER_PAGE,
            $countrows,
            'maxlogin.php?order='.$order.'&',
        );

        $update = (string) $request->query('update', '');
        $msg = $update !== ''
            ? '<h3><b>'.htmlspecialchars($update).' Successful!</b></h3>'
            : '';

        $rows = NexusDB::table('loginattempts')
            ->orderByDesc($orderBy)
            ->offset((int) $offsetStart)
            ->limit((int) $rowsPerPage)
            ->get();

        $body = '<h1>Failed Login Attempts</h1>'.$msg
            .'<table border="1" cellspacing="0" cellpadding="5" width="100%">'
            .$this->renderTableRows($rows)
            .'</table>'
            .($countrows > self::PER_PAGE ? (string) $pagerbottom : '')
            .$this->renderSearchForm();

        return new Response($this->wrap('Max. Login Attempts - Show List', $body));
    }

    private function doBan(string $idRaw): RedirectResponse
    {
        $id = $this->validId($idRaw);
        NexusDB::table('loginattempts')->where('id', $id)->update(['banned' => 'yes']);

        return new RedirectResponse('/maxlogin.php?update=Ban');
    }

    private function doUnban(string $idRaw): RedirectResponse
    {
        $id = $this->validId($idRaw);
        NexusDB::table('loginattempts')->where('id', $id)->update(['banned' => 'no']);

        return new RedirectResponse('/maxlogin.php?update=Unban');
    }

    private function doDelete(string $idRaw): RedirectResponse
    {
        $id = $this->validId($idRaw);
        NexusDB::table('loginattempts')->where('id', $id)->delete();

        return new RedirectResponse('/maxlogin.php?update=Delete');
    }

    private function renderEdit(Request $request, string $idRaw): Response
    {
        $id = $this->validId($idRaw);
        $row = NexusDB::table('loginattempts')->where('id', $id)->first();
        $row = $row !== null ? (array) $row : null;
        if ($row === null) {
            abort(404, 'Not found');
        }

        $ipEsc = htmlspecialchars((string) ($row['ip'] ?? ''));
        $addedEsc = htmlspecialchars((string) ($row['added'] ?? ''));
        $attempts = (int) ($row['attempts'] ?? 0);
        $type = (string) ($row['type'] ?? '');
        $banned = (string) ($row['banned'] ?? '');

        $returntoInput = $request->query('return') === 'yes'
            ? '<input type="hidden" name="returnto" value="viewunbaniprequest.php">'
            : '';

        $body = '<table border="1" cellspacing="0" cellpadding="5" width="100%">'
            .'<tr><td><p>IP Address: <b>'.$ipEsc.'</b></p>'
            .'<p>Action Time: <b>'.$addedEsc.'</b></p></td></tr>'
            .'<form method="post" action="maxlogin.php">'
            .'<input type="hidden" name="action" value="save">'
            .'<input type="hidden" name="id" value="'.$id.'">'
            .'<input type="hidden" name="ip" value="'.$ipEsc.'">'
            .$returntoInput
            .'<tr><td>Attempts <input type="text" size="33" name="attempts" value="'.$attempts.'"></td></tr>'
            .'<tr><td>Attempt Type <select name="type">'
            .'<option value="login"'.($type === 'login' ? ' selected' : '').'>Login Attempt</option>'
            .'<option value="recover"'.($type === 'recover' ? ' selected' : '').'>Recover Password Attempts</option>'
            .'</select></td></tr>'
            .'<tr><td>Current Status <select name="banned">'
            .'<option value="yes"'.($banned === 'yes' ? ' selected' : '').'>Banned!</option>'
            .'<option value="no"'.($banned === 'no' ? ' selected' : '').'>Not Banned!</option>'
            .'</select></td></tr>'
            .'<tr><td><input type="submit" name="submit" value="Save" class="btn"></td></tr>'
            .'</form>'
            .'</table>';

        return new Response($this->wrap(
            'Max. Login Attempts - EDIT ('.$id.')',
            $body,
        ));
    }

    private function doSave(Request $request): RedirectResponse
    {
        $id = (int) $request->input('id', 0);
        $attemptsRaw = (string) $request->input('attempts', '0');
        $type = (string) $request->input('type', 'login');
        $banned = (string) $request->input('banned', 'no');

        if ($id <= 0) {
            abort(400, 'Invalid ID');
        }
        if (! ctype_digit($attemptsRaw) || (int) $attemptsRaw <= 0) {
            abort(400, 'Invalid attempts');
        }

        // Legacy `type` and `banned` allowed values — a sysop can
        // enter arbitrary strings, but the legacy form's `<select>`
        // constrained both. Mirror those constraints here.
        if (! in_array($type, ['login', 'recover'], true)) {
            $type = 'login';
        }
        if (! in_array($banned, ['yes', 'no'], true)) {
            $banned = 'no';
        }

        NexusDB::table('loginattempts')
            ->where('id', $id)
            ->limit(1)
            ->update([
                'attempts' => (int) $attemptsRaw,
                'type' => $type,
                'banned' => $banned,
            ]);

        $returnto = (string) $request->input('returnto', '');
        if ($returnto !== '') {
            // Legacy script set `header("Location: $returnto")`
            // verbatim. We restrict to relative paths to stop a
            // sysop with bookmarklet authority from doubling as
            // an open-redirect vector — same hardening posture
            // PR #289 (`AdRedirectController`) introduced.
            if (str_starts_with($returnto, '/') || ! preg_match('~^https?://~i', $returnto)) {
                return new RedirectResponse('/'.ltrim($returnto, '/'));
            }
        }

        return new RedirectResponse('/maxlogin.php?update=Edit');
    }

    private function renderSearchIp(Request $request): Response
    {
        $ip = trim((string) $request->input('ip', ''));
        $rows = NexusDB::table('loginattempts')
            ->where('ip', 'like', '%'.$ip.'%')
            ->get();

        $body = '<h2>Failed Login Attempts</h2>'
            .'<table border="1" cellspacing="0" cellpadding="5" width="100%">'
            .$this->renderTableRows($rows, sorrySuffix: true)
            .'</table>'
            .$this->renderSearchForm();

        return new Response($this->wrap('Max. Login Attempts - Search', $body));
    }

    private function renderInvalidAction(): Response
    {
        // Legacy: `stderr('Error', 'Invalid Action');` (HTTP 200
        // envelope). We tighten to a 400 — the action is supplied
        // by the URL, so an unrecognised value is a client bug
        // not a server one.
        abort(400, 'Invalid Action');
    }

    /**
     * @param  iterable<int,object|array<string,mixed>>  $rows
     */
    private function renderTableRows(iterable $rows, bool $sorrySuffix = false): string
    {
        $rowsArr = is_array($rows) ? $rows : iterator_to_array($rows, false);
        if (count($rowsArr) === 0) {
            $msg = $sorrySuffix ? 'Sorry, nothing found!' : 'Nothing found';

            return '<tr><td colspan="6"><b>'.$msg.'</b></td></tr>';
        }

        $header = '<tr>'
            .'<td class="colhead"><a href="?order=id">ID</a></td>'
            .'<td class="colhead" align="left"><a href="?order=ip">Ip Address</a></td>'
            .'<td class="colhead" align="left"><a href="?order=added">Action Time</a></td>'
            .'<td class="colhead" align="left"><a href="?order=attempts">Attempts</a></td>'
            .'<td class="colhead" align="left"><a href="?order=type">Attempt Type</a></td>'
            .'<td class="colhead" align="left"><a href="?order=status">Status</a></td>'
            .'</tr>';

        $body = '';
        foreach ($rowsArr as $row) {
            $row = (array) $row;
            $ip = (string) ($row['ip'] ?? '');
            $a2 = NexusDB::table('users')
                ->where('ip', $ip)
                ->select(['id', 'username'])
                ->first();
            $a2 = $a2 !== null ? (array) $a2 : ['id' => 0, 'username' => ''];

            $idEsc = htmlspecialchars((string) ($row['id'] ?? ''));
            $ipEsc = htmlspecialchars($ip);
            $addedEsc = htmlspecialchars((string) ($row['added'] ?? ''));
            $attempts = (int) ($row['attempts'] ?? 0);
            $type = (string) ($row['type'] ?? '');
            $banned = (string) ($row['banned'] ?? '');
            $usernameLink = ((int) $a2['id']) > 0 && function_exists('get_username')
                ? (string) get_username((int) $a2['id'])
                : '';

            $typeLabel = $type === 'recover' ? 'Recover Password Attempt!' : 'Login Attempt!';
            $statusCell = $banned === 'yes'
                ? '<font color="red"><b>banned</b></font> '
                    .'<a href="maxlogin.php?action=unban&id='.$idEsc.'">'
                    .'<font color="green">[<b>unban</b>]</font></a>'
                : '<font color="green"><b>not banned</b></font> '
                    .'<a href="maxlogin.php?action=ban&id='.$idEsc.'">'
                    .'<font color="red">[<b>ban</b>]</font></a>';
            $statusCell .= '  <a OnClick="return confirm(\'Are you wish to delete this attempt?\');" '
                .'href="maxlogin.php?action=delete&id='.$idEsc.'">[<b>delete</b>]</a> '
                .'<a href="maxlogin.php?action=edit&id='.$idEsc.'">'
                .'<font color="blue">[<b>edit</b>]</font></a>';

            $body .= '<tr>'
                .'<td>'.$idEsc.'</td>'
                .'<td align="left">'.$ipEsc.' '.$usernameLink.'</td>'
                .'<td align="left">'.$addedEsc.'</td>'
                .'<td align="left">'.$attempts.'</td>'
                .'<td align="left">'.$typeLabel.'</td>'
                .'<td align="left">'.$statusCell.'</td>'
                .'</tr>';
        }

        return $header.$body;
    }

    private function renderSearchForm(): string
    {
        return '<form method="post" name="search" action="maxlogin.php">'
            .'<input type="hidden" name="action" value="searchip">'
            .'<p class="success" align="center">Search IP '
            .'<input type="text" name="ip" size="25"> '
            .'<input type="submit" name="submit" value="Search IP" class="btn">'
            .'</p>'
            .'</form>';
    }

    /**
     * Mirror of the legacy `is_valid_id()` check + `stderr('Error',
     * 'Invalid ID')`. Returns the validated id or aborts with 400.
     */
    private function validId(string $idRaw): int
    {
        if (! ctype_digit($idRaw) || (int) $idRaw <= 0) {
            abort(400, 'Invalid ID');
        }

        return (int) $idRaw;
    }

    private function wrap(string $title, string $body): string
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head>
<body>
{$body}
</body></html>
HTML;
    }
}

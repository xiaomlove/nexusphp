<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/bannedemails.php` (deleted in the same PR).
 *
 * Phase 2 batch #6 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2".
 *
 * Original legacy flow:
 *   1. `dbconn();` + `loggedinorreturn();` bootstrap.
 *   2. `get_user_class() < UC_SYSOP` → `stderr("Error", "Access denied.")`.
 *   3. `$action = $_POST['action'] ?? $_GET['action'] ?? 'showlist'`.
 *   4. `showlist` → `stdhead('Show List')` + form with the current
 *      `bannedemails.value` textarea + hidden `action=savelist`.
 *   5. `savelist` → `UPDATE bannedemails SET value = ?`, then
 *      `stdhead('Save List')` + "Saved." + `stdfoot()`.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authenticated user below `User::CLASS_SYSOP` → `abort(403)`.
 *   - GET (default `showlist`) → 200 chrome-less HTML with the
 *     edit form pre-filled from `bannedemails.value`.
 *   - POST `action=savelist` → `UPDATE bannedemails SET value =
 *     trim($input)`, then 200 chrome-less HTML "Saved." page.
 *   - Other actions fall back to `showlist` (matching the legacy
 *     `if/elseif` with no `else`).
 *
 * The `bannedemails` table is a single-row key-value store; the
 * legacy migration / seed leaves exactly one row in place, so the
 * driverless `UPDATE … SET value = ?` (no WHERE) is the correct
 * write path.
 */
class BannedEmailsController extends Controller
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

        $action = (string) $request->input(
            'action',
            $request->query('action', 'showlist'),
        );

        if ($action === 'savelist' && $request->isMethod('POST')) {
            $value = trim(htmlspecialchars((string) $request->input('value', '')));
            NexusDB::table('bannedemails')->update(['value' => $value]);

            return new Response($this->wrap('Save List', '<p>Saved.</p>'));
        }

        return new Response($this->wrap('Show List', $this->renderForm()));
    }

    private function renderForm(): string
    {
        $row = NexusDB::table('bannedemails')->first();
        $current = htmlspecialchars(
            (string) (($row ? (array) $row : ['value' => ''])['value'] ?? '')
        );

        return '<table border="1" cellspacing="0" cellpadding="5" width="737">'."\n"
            .'<form method="post" action="bannedemails.php">'."\n"
            .'<input type="hidden" name="action" value="savelist">'."\n"
            .'<tr><td>Enter a list of banned email addresses (separated by spaces):<br />'
            .'To ban a specific address enter "email@domain.com", to ban an entire domain enter "@domain.com"</td>'."\n"
            .'<td><textarea name="value" rows="5" cols="40">'.$current.'</textarea>'."\n"
            .'<input type="submit" value="save"></td></tr>'."\n"
            .'</form>'."\n"
            .'</table>'."\n";
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

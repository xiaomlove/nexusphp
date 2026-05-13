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
 * Replacement for `public/donated.php` (deleted in the same PR).
 *
 * Phase 2 batch #5 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2".
 *
 * Original legacy flow:
 *   1. `dbconn();` + `loggedinorreturn();` bootstrap.
 *   2. `get_user_class() < UC_SYSOP` → `stderr("Error", "Access denied.")`.
 *   3. GET → render a form (username + donated) wrapped in
 *      `stdhead('Update Users Donated Amounts')` / `stdfoot()`.
 *   4. POST with empty `username` or empty `donated` →
 *      `stderr("Error", "Missing form data.")`.
 *   5. POST with both fields →
 *      `UPDATE users SET donated = ? WHERE username = ?`, then
 *      `SELECT id FROM users WHERE username = ?`. If the user
 *      exists, `header('Location: .../userdetails.php?id=<id>')`
 *      + `die`. Otherwise `stderr("Error", "Unable to update
 *      account.")`.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authenticated user below `User::CLASS_SYSOP` → `abort(403)`
 *     (same hardening as `AllAgentsController` /
 *     `ClearCacheController` / `TakeUpdateController` — the legacy
 *     `stderr()` rendered HTTP 200, which is the wrong status).
 *   - POST without `username` or without `donated` → re-render the
 *     form with an inline error notice ("Missing form data.").
 *   - POST with an unknown `username` → re-render the form with an
 *     inline error notice ("Unable to update account.").
 *   - POST with valid data → `UPDATE users SET donated = ? WHERE
 *     username = ?`, then 302 to `/userdetails.php?id=<id>`.
 *   - The HTML envelope is chrome-less, matching the
 *     `MoreSmiliesController` precedent.
 *
 * The `donated` column is `decimal(8, 2)` (see
 * `database/migrations/..._create_users_table.php`). The legacy
 * script accepted any string and let MySQL truncate; the migrated
 * controller does the same to preserve the contract.
 */
class DonatedController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_SYSOP) {
            abort(403);
        }

        $username = '';
        $donated = '';
        $error = null;

        if ($request->isMethod('POST')) {
            $username = trim((string) $request->input('username', ''));
            $donated = trim((string) $request->input('donated', ''));
            if ($username === '' || $donated === '') {
                $error = 'Missing form data.';
            } else {
                NexusDB::table('users')
                    ->where('username', $username)
                    ->update(['donated' => $donated]);
                $userId = NexusDB::table('users')
                    ->where('username', $username)
                    ->value('id');
                if ($userId === null) {
                    $error = 'Unable to update account.';
                } else {
                    return redirect("/userdetails.php?id={$userId}");
                }
            }
        }

        $body = $this->renderForm($username, $donated, $error);

        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Update Users Donated Amounts</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }

    /**
     * Render the form (with optional inline error notice). The
     * markup matches the legacy script's output so the page looks
     * identical pixel-for-pixel.
     */
    private function renderForm(string $username, string $donated, ?string $error): string
    {
        $statusBlock = '';
        if ($error !== null) {
            $statusBlock = sprintf(
                '<p align="center"><font class="striking">%s</font></p>'."\n",
                htmlspecialchars($error),
            );
        }

        $usernameEsc = htmlspecialchars($username);
        $donatedEsc = htmlspecialchars($donated);

        return '<h1>Update Users Donated Amounts</h1>'."\n"
            .$statusBlock
            .'<form method="post" action="donated.php">'."\n"
            .'<table border="1" cellspacing="0" cellpadding="5">'."\n"
            .'<tr><td class="rowhead">User name</td><td>'
            .'<input type="text" name="username" size="40" value="'.$usernameEsc.'"></td></tr>'."\n"
            .'<tr><td class="rowhead">Donated</td><td>'
            .'<input type="text" name="donated" size="5" value="'.$donatedEsc.'"></td></tr>'."\n"
            .'<tr><td colspan="2" align="center">'
            .'<input type="submit" value="Okay" class="btn"></td></tr>'."\n"
            .'</table>'."\n"
            .'</form>'."\n";
    }
}

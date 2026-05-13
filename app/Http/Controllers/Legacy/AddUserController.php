<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/adduser.php` (deleted in the same PR).
 *
 * Phase 2 batch #9 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2".
 *
 * Original legacy flow:
 *   1. `dbconn();` + `loggedinorreturn();`.
 *   2. `get_user_class() < UC_ADMINISTRATOR` → `stderr("Error", "Access denied.")`.
 *   3. POST → `(new UserRepository())->store([username,email,password,
 *      password_confirmation])`. On `\Exception` → `stderr("ERROR", $msg)`.
 *      On success → `header("Location: .../userdetails.php?id=<id>")`.
 *   4. GET → render a tiny `<form method=post action=adduser.php>` with
 *      username / password / re-type / email + submit.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authenticated user below `User::CLASS_ADMINISTRATOR` → `abort(403)`
 *     (tightens the legacy 200 / `stderr()` body, same as every other
 *     Phase 2 controller).
 *   - GET → 200 chrome-less HTML with the form.
 *   - POST with invalid input → 200 chrome-less HTML re-rendering the
 *     form with the original values preserved (minus the password
 *     fields) plus an inline error notice from `UserRepository::store`
 *     (e.g. "password confirmation != password", "Invalid email: …",
 *     "The email address: … is already in use", …).
 *   - POST success → 302 to `/userdetails.php?id={new_id}`. Matches
 *     the legacy `header("Location: ...")` target exactly so existing
 *     admin bookmarks/automation keep working.
 *
 * `UserRepository` is constructor-injected (rather than instantiated
 * with `new` like the legacy script did) so the test suite can wire
 * a stub via the Laravel container without going through the full
 * `mksecret()` + settings-cached registration path. By default the
 * repository's `store()` already runs the same `validusername` /
 * `check_email` / password-length guards the legacy admin relied on.
 */
class AddUserController extends Controller
{
    public function __construct(
        private readonly LegacyContext $context,
        private readonly UserRepository $users,
    ) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_ADMINISTRATOR) {
            abort(403);
        }

        $username = trim((string) $request->input('username', ''));
        $email = trim((string) $request->input('email', ''));
        $error = null;

        if ($request->isMethod('POST')) {
            try {
                $newUser = $this->users->store([
                    'username' => $username,
                    'email' => $email,
                    'password' => (string) $request->input('password', ''),
                    'password_confirmation' => (string) $request->input('password2', ''),
                ]);

                return new RedirectResponse('/userdetails.php?id='.(int) $newUser->id);
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        return new Response($this->wrap(
            'Add user',
            $this->renderForm($username, $email, $error),
        ));
    }

    private function renderForm(string $username, string $email, ?string $error): string
    {
        $usernameEsc = htmlspecialchars($username);
        $emailEsc = htmlspecialchars($email);

        $statusBlock = '';
        if ($error !== null) {
            $statusBlock = '<p><font class="striking">'
                .htmlspecialchars($error).'</font></p>'."\n";
        }

        return '<h1>Add user</h1>'."\n"
            .$statusBlock
            .'<form method="post" action="adduser.php">'."\n"
            .'<table border="1" cellspacing="0" cellpadding="5">'."\n"
            .'<tr><td class="rowhead">User name</td>'
            .'<td><input type="text" name="username" size="40" value="'.$usernameEsc.'"></td></tr>'."\n"
            .'<tr><td class="rowhead">Password</td>'
            .'<td><input type="password" name="password" size="40"></td></tr>'."\n"
            .'<tr><td class="rowhead">Re-type password</td>'
            .'<td><input type="password" name="password2" size="40"></td></tr>'."\n"
            .'<tr><td class="rowhead">E-mail</td>'
            .'<td><input type="text" name="email" size="40" value="'.$emailEsc.'"></td></tr>'."\n"
            .'<tr><td colspan="2" align="center">'
            .'<input type="submit" value="Okay" class="btn"></td></tr>'."\n"
            .'</table>'."\n"
            .'</form>'."\n";
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

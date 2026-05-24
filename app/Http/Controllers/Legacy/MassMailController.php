<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/massmail.php` (deleted in the same PR).
 *
 * Phase 2 migration. The legacy script was a 93-LOC sysop-only page
 * that renders a "Send mass e-mail to all members" form (GET) and
 * handles the submission (POST) by querying users matching a class
 * filter and calling `sent_mail()` for each row.
 *
 * Original legacy flow:
 *   1. `require "../include/bittorrent.php"; dbconn();` bootstrap.
 *   2. `loggedinorreturn();` — redirect to login if not authenticated.
 *   3. `get_user_class() < UC_SYSOP` → `stderr("Error", "Permission denied.")`.
 *   4. POST branch:
 *      - Validate `$_POST['or']` is one of `<, >, =, <=, >=`.
 *      - Query `users` WHERE `class {$or} {$class}`.
 *      - Validate subject (default "(no subject)"), prepend "Fw: ".
 *      - Validate message not empty.
 *      - Loop through rows, call `sent_mail($email, ...)` for each.
 *      - `stderr('Success', 'Messages sent.')` or error.
 *   5. GET branch (also rendered after POST for the form):
 *      - `stdhead('Mass E-mail Gateway')`.
 *      - Render form with class operator selector + class dropdown.
 *      - `stdfoot()`.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to login.
 *   - Authenticated user below `User::CLASS_SYSOP` → `abort(403)`.
 *   - GET → 200 chrome-less HTML form.
 *   - POST → validate, send emails, return success/error as HTML 200.
 *   - `sent_mail()` helper preserved (lives in `include/functions.php`,
 *     already loaded at boot).
 *   - URL preserved at `/massmail.php`.
 */
class MassMailController extends Controller
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

        // Handle POST submission.
        if ($request->isMethod('POST')) {
            return $this->handlePost($request, $user);
        }

        // GET — render the form.
        return $this->renderFormResponse($user);
    }

    private function handlePost(Request $request, User $user): Response
    {
        $class = (int) $request->input('class', 0);
        $or = (string) $request->input('or', '');

        if (! in_array($or, ['<', '>', '=', '<=', '>='], true)) {
            return $this->stderrResponse('Error', 'Invalid symbol!');
        }

        $rows = NexusDB::table('users')
            ->whereRaw("class {$or} ?", [$class])
            ->select(['id', 'username', 'email'])
            ->get();

        $subject = substr(htmlspecialchars(trim((string) $request->input('subject', ''))), 0, 80);
        if ($subject === '') {
            $subject = '(no subject)';
        }
        $subject = "Fw: {$subject}";

        $message1 = htmlspecialchars(trim((string) $request->input('message', '')));
        if ($message1 === '') {
            return $this->stderrResponse('Error', 'Empty message!');
        }

        $siteName = (string) (Setting::getByName('basic.SITENAME') ?? 'NexusPHP');
        $siteEmail = (string) (Setting::getByName('main.SITEEMAIL') ?? '');

        $success = false;
        foreach ($rows as $arr) {
            $arr = (array) $arr;
            $to = (string) ($arr['email'] ?? '');
            if ($to === '') {
                continue;
            }

            $message = 'Message received from '.$siteName.' on '.date('Y-m-d H:i:s').".\n"
                ."---------------------------------------------------------------------\n\n"
                .$message1."\n\n"
                ."---------------------------------------------------------------------\n{$siteName}\n";

            $success = sent_mail($to, $siteName, $siteEmail, $subject, $message, 'Mass Mail', false);
        }

        if ($success) {
            return $this->stderrResponse('Success', 'Messages sent.');
        }

        return $this->stderrResponse('Error', 'Try again.');
    }

    private function renderFormResponse(User $user): Response
    {
        $userClass = (int) $user->class;

        // Build class dropdown options.
        $maxclass = $userClass - 1;
        $classOptions = '';
        for ($i = 0; $i <= $maxclass; $i++) {
            $selected = ($userClass === $i) ? ' selected' : '';
            $className = function_exists('get_user_class_name')
                ? get_user_class_name($i, false, true, true)
                : "Class {$i}";
            $classOptions .= "<option value=\"{$i}\"{$selected}>{$className}\n";
        }

        $classSelector = <<<HTML
<tr><td class="rowhead">Classe</td><td colspan="2" align="left"><select name="or"><option value="<">&lt;<option value=">">&gt;<option value="=">=<option value="<=">&lt;=<option value=">=">&gt;=</select><select name="class">
{$classOptions}</select></td></tr>
HTML;

        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Mass E-mail Gateway</title>
</head>
<body>
<p><table border="0" class="main" cellspacing="0" cellpadding="0"><tr>
<td class="embedded" style="padding-left: 10px"><font size="3"><b>Send mass e-mail to all members</b></font></td>
</tr></table></p>
<table border="1" cellspacing="0" cellpadding="5">
<form method="post" action="massmail.php">
{$classSelector}
<tr><td class="rowhead">Subject</td><td><input type="text" name="subject" size="80"></td></tr>
<tr><td class="rowhead">Body</td><td><textarea name="message" cols="80" rows="20"></textarea></td></tr>
<tr><td colspan="2" align="center"><input type="submit" value="Send" class="btn"></td></tr>
</form>
</table>
</body>
</html>
HTML;

        return new Response($html);
    }

    /**
     * Chrome-less error/success response matching the legacy
     * `stderr($title, $message)` pattern at HTTP 200.
     */
    private function stderrResponse(string $title, string $message): Response
    {
        return new Response(
            '<html><body><h2>'.htmlspecialchars($title).'</h2><p>'.htmlspecialchars($message).'</p></body></html>',
            200,
        );
    }
}

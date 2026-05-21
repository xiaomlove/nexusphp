<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Http\Requests\Legacy\SendMassMailRequest;
use App\Jobs\SendMassMail;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/massmail.php` (deleted in the same PR).
 *
 * Phase 2 batch — see `docs/legacy-strategy.md` § "Phase 2" and
 * `docs/migration-recipe.md`. SYSOP+ "Mass E-mail Gateway" — sends
 * one e-mail per user matching a `class <op> <threshold>` filter.
 * Single-endpoint controller (legacy form posts to itself).
 *
 * Original legacy flow (`public/massmail.php`, 93 LOC):
 *   1. `dbconn();` + `loggedinorreturn();` bootstrap.
 *   2. `get_user_class() < UC_SYSOP` → `stderr('Error', 'Permission denied.')`.
 *   3. POST: validate `or` ∈ `<,>,=,<=,>=`, `class` integer,
 *      `subject` (defaults to `(no subject)` if blank, gets `Fw: `
 *      prefix), `message` (required non-empty).
 *   4. `SELECT id, username, email FROM users WHERE class $or $class`
 *      and `foreach` send mail synchronously via `sent_mail`.
 *   5. GET: render form with operator dropdown + class number selector.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to
 *     `login.php?returnto=...`.
 *   - Authenticated user below `User::CLASS_SYSOP` → `abort(403)`
 *     (legacy `stderr()` was HTTP 200; tightened, same posture as
 *     every other Phase 2 controller).
 *   - GET (or POST without valid input) → 200 chrome-less HTML form.
 *     The class-number `<select>` mirrors the legacy "max class"
 *     rule — moderators are capped at `CLASS_POWER_USER`,
 *     admins/sysops see every class up to `their_class - 1`.
 *   - POST validation failures → 422 via {@see SendMassMailRequest}.
 *     Legacy `stderr('Error', 'Invalid symbol!')` /
 *     `stderr('Error', 'Empty message!')` strings preserved as the
 *     validator messages so admin-side log scrapers keep matching.
 *   - Happy path → 302 to `/massmail.php?sent=1`. The fan-out
 *     itself runs in the {@see SendMassMail} queue job — so by the
 *     time the user sees the confirmation, the mails may still be
 *     in the queue. The staff-side UX is unchanged.
 *
 * Behaviour preserved from legacy:
 *   - `(no subject)` fallback when subject is blank.
 *   - `Fw: ` prefix on every outgoing subject.
 *   - The `htmlspecialchars(trim(...))` round on subject + message
 *     is preserved — it's a long-standing legacy quirk that
 *     mangles non-ASCII payloads, but the migration PR is not the
 *     place to fix it (a Phase-5 sweep can decide whether to drop
 *     the `htmlspecialchars` since the payload is plain-text email
 *     anyway).
 *   - The legacy script's body envelope (header + separator +
 *     message + separator + footer) lives in the queue job —
 *     `SendMassMail::renderBody`.
 *   - The "moderator capped at CLASS_POWER_USER" form rule from
 *     `public/massmail.php:71-73` is preserved exactly. In
 *     practice moderators (class 13) still get the form because
 *     the route lives in `auth.nexus:nexus-web` and the controller
 *     gates on `CLASS_SYSOP`, so this branch is dead — but we
 *     keep the conditional so an in-flight settings change (e.g.
 *     lowering the gate to moderator) doesn't surprise admins.
 *
 * URL preserved exactly so:
 *   - the `SysoppanelTableSeeder` row 4 (`url='massmail.php'`),
 *   - the `<form action=massmail.php>` self-submit on the rendered
 *     form,
 *   - any admin bookmarks
 *
 * keep working without template changes. The matching nginx
 * exact-location entry lives in
 * `.docker/openresty/sites/app.conf.template`.
 *
 * UI strings: hardcoded English, same trade-off as
 * `StaffMessController` / `IncrementBulkController`.
 */
class MassmailController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response|RedirectResponse|JsonResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_SYSOP) {
            abort(403);
        }

        if ($request->isMethod('POST')) {
            return $this->handlePost($request, $user);
        }

        return $this->renderForm((int) $user->class, $request->query('sent') === '1');
    }

    private function handlePost(Request $request, User $user): RedirectResponse|JsonResponse
    {
        $form = SendMassMailRequest::createFrom($request);
        $form->setContainer(app())->setRedirector(app('redirect'));
        $form->validateResolved();

        $operator = (string) $form->validated('or');
        $threshold = (int) $form->validated('class');

        $rawSubject = (string) ($form->validated('subject') ?? '');
        $subject = substr(htmlspecialchars($rawSubject), 0, 80);
        if ($subject === '') {
            $subject = '(no subject)';
        }
        $subject = 'Fw: '.$subject;

        $message = htmlspecialchars((string) $form->validated('message'));

        SendMassMail::dispatch(
            senderId: (int) $user->id,
            operator: $operator,
            threshold: $threshold,
            subject: $subject,
            message: $message,
        );

        return new RedirectResponse('/massmail.php?sent=1');
    }

    private function renderForm(int $callerClass, bool $sent): Response
    {
        $sentBanner = '';
        if ($sent) {
            $sentBanner = '<tr><td colspan="3" align="center"><font color="red"><b>'
                .'Mass mail dispatch queued.'
                .'</b></font></td></tr>'."\n";
        }

        $maxClass = $callerClass === (int) User::CLASS_MODERATOR
            ? (int) User::CLASS_POWER_USER
            : $callerClass - 1;
        if ($maxClass < 0) {
            $maxClass = 0;
        }

        $operatorOptions = '';
        foreach (SendMassMailRequest::VALID_OPERATORS as $op) {
            $operatorOptions .= '<option value="'.htmlspecialchars($op).'">'.htmlspecialchars($op).'</option>';
        }

        $classOptions = '';
        for ($i = 0; $i <= $maxClass; $i++) {
            $label = (string) ($i.' — '.($this->className($i) ?? ''));
            $classOptions .= '<option value="'.$i.'">'.htmlspecialchars($label).'</option>'."\n";
        }

        $body = '<h1>Mass E-mail Gateway</h1>'."\n"
            .'<p><b>Send mass e-mail to all members</b></p>'."\n"
            .'<table border="1" cellspacing="0" cellpadding="5">'."\n"
            .'<form method="post" action="massmail.php">'."\n"
            .$sentBanner
            .'<tr><td class="rowhead">Class</td>'
            .'<td colspan="2" align="left">'
            .'<select name="or">'.$operatorOptions.'</select> '
            .'<select name="class">'.$classOptions.'</select>'
            .'</td></tr>'."\n"
            .'<tr><td class="rowhead">Subject</td><td><input type="text" name="subject" size="80"></td></tr>'."\n"
            .'<tr><td class="rowhead">Body</td><td><textarea name="message" cols="80" rows="20"></textarea></td></tr>'."\n"
            .'<tr><td colspan="2" align="center"><input type="submit" value="Send" class="btn"></td></tr>'."\n"
            .'</form>'."\n"
            .'</table>'."\n";

        return new Response($this->wrap('Mass E-mail Gateway', $body));
    }

    private function className(int $class): ?string
    {
        $info = User::$classes[$class] ?? null;
        if (is_array($info) && isset($info['text'])) {
            return (string) $info['text'];
        }

        return null;
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

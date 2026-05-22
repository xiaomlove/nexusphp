<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/makepoll.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. The poll-management form gated
 * on the `pollmanage` permission. Single endpoint:
 *
 *   - GET (no `?action`)            → render new-poll form,
 *   - GET `?action=edit&pollid=N`   → render edit-poll form,
 *   - POST                          → upsert poll, redirect.
 *
 * The legacy script supports up to 20 options (`option0`..`option19`)
 * — preserved verbatim, including the legacy `htmlspecialchars()`
 * pre-encoding of every option string before storage. Phase 5 can
 * decide to switch storage to a normalised options table; the
 * Phase 2 contract is "render exactly what the legacy script
 * rendered".
 *
 * URL preserved exactly so `public/index.php:488,490` (the
 * home-page poll widget's `[New]` / `[Edit]` action links) keep
 * working without template changes. The matching nginx
 * exact-location entry lives in
 * `.docker/openresty/sites/app.conf.template`. POST is CSRF-exempt
 * — see `App\Http\Middleware\VerifyCsrfToken::$except` (the legacy
 * `<form action=makepoll.php>` had no `@csrf` field).
 */
class MakePollController extends Controller
{
    /** Number of poll options the legacy schema supports (option0..option19). */
    private const OPTION_COUNT = 20;

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if (! user_can('pollmanage')) {
            abort(403, 'Permission denied.');
        }

        $lang = $this->loadLangMakePoll();
        $action = (string) $request->query('action', '');

        if ($request->isMethod('POST')) {
            return $this->handleSubmit($request, $lang);
        }

        $poll = null;
        $pollId = 0;
        if ($action === 'edit') {
            $pollId = (int) $request->query('pollid', 0);
            if ($pollId <= 0) {
                abort(422, 'Invalid pollid.');
            }
            $row = NexusDB::table('polls')->where('id', $pollId)->first();
            if ($row === null) {
                abort(404, (string) ($lang['std_no_poll_id'] ?? 'No such poll.'));
            }
            $poll = (array) $row;
        }

        return $this->renderForm($poll, $pollId, $request, $lang);
    }

    private function handleSubmit(Request $request, array $lang): RedirectResponse
    {
        $pollId = (int) $request->input('pollid', 0);

        // Mirror the legacy `htmlspecialchars($_POST['question'])`
        // contract so existing rows stay byte-compatible — the
        // public-side renderer trusts the stored string is already
        // safe.
        $question = htmlspecialchars((string) $request->input('question', ''));
        $options = [];
        for ($i = 0; $i < self::OPTION_COUNT; $i++) {
            $options['option'.$i] = htmlspecialchars((string) $request->input('option'.$i, ''));
        }

        $returnto = (string) $request->input('returnto', '');
        if (empty($question) || empty($options['option0']) || empty($options['option1'])) {
            abort(422, (string) ($lang['std_missing_form_data'] ?? 'Missing form data.'));
        }

        $payload = array_merge(['question' => $question], $options);

        if ($pollId > 0) {
            NexusDB::table('polls')->where('id', $pollId)->update($payload);
        } else {
            $payload['added'] = date('Y-m-d H:i:s');
            NexusDB::insert('polls', $payload);
        }

        $this->forgetPollCaches();

        $baseUrl = rtrim((string) Setting::getBaseUrl(), '/');
        if ($returnto === 'main') {
            return redirect($baseUrl !== '' ? $baseUrl : '/');
        }
        if ($pollId > 0) {
            return redirect(($baseUrl !== '' ? $baseUrl : '').'/log.php?action=poll#'.$pollId);
        }

        return redirect($baseUrl !== '' ? $baseUrl : '/');
    }

    /**
     * @param  array<string,mixed>|null  $poll
     */
    private function renderForm(?array $poll, int $pollId, Request $request, array $lang): Response
    {
        $isEdit = $poll !== null;
        $title = htmlspecialchars((string) (
            $isEdit
                ? ($lang['head_edit_poll'] ?? 'Edit poll')
                : ($lang['head_new_poll'] ?? 'Make poll')
        ));

        $heading = $isEdit
            ? (string) ($lang['text_edit_poll'] ?? 'Edit poll')
            : (string) ($lang['text_make_poll'] ?? 'Make poll');

        $body = '<h1>'.htmlspecialchars($heading).'</h1>';

        // "Current poll is only X days old" warning when creating a new poll.
        if (! $isEdit) {
            $body .= $this->renderRecentPollWarning($lang);
        }

        $questionLabel = htmlspecialchars((string) ($lang['text_question'] ?? 'Question'));
        $optionLabel = htmlspecialchars((string) ($lang['text_option'] ?? 'Option'));
        $required = htmlspecialchars((string) ($lang['text_required'] ?? '* required'));
        $submitLabel = htmlspecialchars((string) (
            $isEdit
                ? ($lang['submit_edit_poll'] ?? 'Save')
                : ($lang['submit_create_poll'] ?? 'Create')
        ));

        $questionVal = htmlspecialchars((string) ($poll['question'] ?? ''), ENT_QUOTES);

        $body .= '<form method="post" action="/makepoll.php">'
            .'<style type="text/css">input.mp { width: 450px; }</style>'
            .'<table border="1" cellspacing="0" cellpadding="5">'
            .'<tr><td class="rowhead">'.$questionLabel.' <font color="red">*</font></td>'
            .'<td align="left"><input name="question" class="mp" maxlength="255" value="'.$questionVal.'"></td></tr>';

        for ($i = 0; $i < self::OPTION_COUNT; $i++) {
            $optVal = htmlspecialchars((string) ($poll['option'.$i] ?? ''), ENT_QUOTES);
            $star = $i < 2 ? ' <font color="red">*</font>' : '';
            $body .= sprintf(
                '<tr><td class="rowhead">%s%d%s</td><td align="left"><input name="option%d" class="mp" maxlength="40" value="%s"></td></tr>',
                $optionLabel,
                $i + 1,
                $star,
                $i,
                $optVal,
            );
        }

        $body .= '<tr><td colspan="2" align="center"><input type="submit" value="'.$submitLabel.'" style="height: 20pt"></td></tr></table>'
            .'<p><font color="red">*</font>'.$required.'</p>';

        if ($isEdit) {
            $body .= '<input type="hidden" name="pollid" value="'.$pollId.'">';
        }
        $returntoIn = (string) ($request->query('returnto') ?? $request->server('HTTP_REFERER', ''));
        $body .= '<input type="hidden" name="returnto" value="'.htmlspecialchars($returntoIn, ENT_QUOTES).'">';
        $body .= '</form>';

        $html = <<<HTML
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$title}</title>
</head>
<body>
{$body}
</body></html>
HTML;

        return new Response($html);
    }

    private function renderRecentPollWarning(array $lang): string
    {
        $latest = NexusDB::table('polls')
            ->select(['question', 'added'])
            ->orderByDesc('added')
            ->limit(1)
            ->first();
        if ($latest === null) {
            return '';
        }
        $latest = (array) $latest;

        $diffSeconds = max(0, time() - strtotime((string) $latest['added']));
        $hours = (int) floor($diffSeconds / 3600);
        $days = (int) floor($hours / 24);
        if ($days >= 3) {
            return '';
        }

        if ($days >= 1) {
            $unit = (string) ($lang['text_day'] ?? ' day');
            $magnitude = $days;
        } else {
            $unit = (string) ($lang['text_hour'] ?? ' hour');
            $magnitude = $hours;
        }
        $duration = $magnitude.$unit.add_s($magnitude);

        return sprintf(
            '<p><font class="striking"><b>%s(<i>%s</i>)%s%s%s</b></font></p>',
            (string) ($lang['text_current_poll'] ?? 'Current poll'),
            htmlspecialchars((string) $latest['question']),
            (string) ($lang['text_is_only'] ?? ' is only '),
            $duration,
            (string) ($lang['text_old'] ?? ' old.'),
        );
    }

    private function forgetPollCaches(): void
    {
        $cache = $GLOBALS['Cache'] ?? null;
        if (is_object($cache) && method_exists($cache, 'delete_value')) {
            $cache->delete_value('current_poll_content');
            $cache->delete_value('current_poll_result', true);

            return;
        }
        NexusDB::cache_del('current_poll_content');
        NexusDB::cache_del('current_poll_result');
    }

    /** @return array<string,string> */
    private function loadLangMakePoll(): array
    {
        $path = base_path(get_langfile_path('makepoll.php'));
        $lang_makepoll = [];
        if (is_file($path)) {
            require $path;
        }

        return is_array($lang_makepoll) ? $lang_makepoll : [];
    }
}

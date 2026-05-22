<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Message;
use App\Models\StaffMessage;
use App\Models\User;
use App\Repositories\MessageRepository;
use App\Repositories\ToolRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/staffbox.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. Staff private-message inbox,
 * accessible to any user with the `staffmem` permission OR to users
 * whose custom permission set includes the permission attached to a
 * specific staff message.
 *
 * Actions:
 *   - GET  (none)                          → inbox list (paginated)
 *   - GET  `?action=viewpm&pmid=N`         → view single message
 *   - GET  `?action=answermessage&...`     → answer compose form
 *   - POST `?action=takeanswer`            → send answer, redirect
 *   - GET  `?action=deletestaffmessage&id=N` → delete, redirect
 *   - GET  `?action=setanswered&id=N`      → mark answered, redirect
 *   - POST `?action=takecontactanswered`   → bulk mark/delete, redirect
 *
 * URL preserved exactly so `include/functions.php:2316` (the staff-box
 * icon link in the header) and `include/functions.php:2444`
 * (the `msgalert` call) keep working without template changes.
 *
 * POST is CSRF-exempt — see
 * `App\Http\Middleware\VerifyCsrfToken::$except`; the legacy forms
 * had no `@csrf` field.
 */
class StaffboxController extends Controller
{
    /** Messages per page for the inbox list. Matches the legacy `pager(20, …)`. */
    private const PER_PAGE = 20;

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }

        $lang = $this->loadLang();
        $action = (string) $request->query('action', '');

        // POST verbs.
        if ($request->isMethod('POST')) {
            $postAction = (string) $request->input('action', '');
            if ($postAction === 'takeanswer') {
                return $this->handleTakeAnswer($request, $user, $lang);
            }
            if ($postAction === 'takecontactanswered') {
                return $this->handleTakeContactAnswered($request, $user, $lang);
            }
        }

        return match ($action) {
            'viewpm' => $this->renderViewPm($request, $user, $lang),
            'answermessage' => $this->renderAnswerForm($request, $user, $lang),
            'deletestaffmessage' => $this->handleDelete($request, $user),
            'setanswered' => $this->handleSetAnswered($request, $user),
            default => $this->renderInbox($request, $user, $lang),
        };
    }

    // ─── Inbox list ──────────────────────────────────────────────────────

    private function renderInbox(Request $request, User $user, array $lang): Response
    {
        $url = '/staffbox.php?';
        $query = MessageRepository::buildStaffMessageQuery((int) $user->id);
        $count = $query->count();
        $page = max(0, (int) $request->query('page', 0));

        $title = htmlspecialchars((string) ($lang['head_staff_pm'] ?? 'Staff PM'));

        if ($count === 0) {
            $body = '<h1 align="center">'.$title.'</h1>'
                .'<p>'.htmlspecialchars((string) ($lang['std_no_messages_yet'] ?? 'No messages yet.')).'</p>';

            return $this->wrap($title, $body);
        }

        $totalPages = (int) ceil($count / self::PER_PAGE);
        $page = min($page, max(0, $totalPages - 1));

        $res = $query
            ->forPage($page + 1, self::PER_PAGE)
            ->orderBy('id', 'desc')
            ->get()
            ->toArray();

        $rows = '';
        foreach ($res as $arr) {
            if ($arr['answered']) {
                $answered = '<nobr><font color=green>'
                    .htmlspecialchars((string) ($lang['text_yes'] ?? 'Yes'))
                    .'</font> - '.get_username((int) $arr['answeredby'])
                    .'</nobr>';
            } else {
                $answered = '<font color=red>'.htmlspecialchars((string) ($lang['text_no'] ?? 'No')).'</font>';
            }
            $pmid = (int) $arr['id'];
            $rows .= '<tr>'
                .'<td width="100%" class="rowfollow" align="left">'
                .'<a href="/staffbox.php?action=viewpm&pmid='.$pmid.'&return='.urlencode((string) $request->getQueryString()).'">'
                .htmlspecialchars((string) ($arr['subject'] ?? ''))
                .'</a></td>'
                .'<td class="rowfollow" align="center">'.get_username((int) $arr['sender']).'</td>'
                .'<td class="rowfollow" align="center"><nobr>'.gettime((string) ($arr['added'] ?? ''), true, false).'</nobr></td>'
                .'<td class="rowfollow" align="center">'.$answered.'</td>'
                .'<td class="rowfollow" align="center"><input type="checkbox" name="setanswered[]" value="'.$pmid.'" /></td>'
                .'</tr>';
        }

        $checkAll = htmlspecialchars((string) ($GLOBALS['lang_functions']['input_check_all'] ?? 'Check all'), ENT_QUOTES);
        $uncheckAll = htmlspecialchars((string) ($GLOBALS['lang_functions']['input_uncheck_all'] ?? 'Uncheck all'), ENT_QUOTES);

        $colSubject = htmlspecialchars((string) ($lang['col_subject'] ?? 'Subject'));
        $colSender = htmlspecialchars((string) ($lang['col_sender'] ?? 'Sender'));
        $colAdded = htmlspecialchars((string) ($lang['col_added'] ?? 'Added'));
        $colAnswered = htmlspecialchars((string) ($lang['col_answered'] ?? 'Answered'));
        $colAction = htmlspecialchars((string) ($lang['col_action'] ?? 'Action'));
        $submitSet = htmlspecialchars((string) ($lang['submit_set_answered'] ?? 'Set answered'));
        $submitDelete = htmlspecialchars((string) ($lang['submit_delete'] ?? 'Delete'));

        $pager = $this->renderPager($count, $page, '/staffbox.php?');

        $body = '<h1 align="center">'.$title.'</h1>'
            .'<form method="post" action="/staffbox.php?action=takecontactanswered">'
            .'<table width="940" border="1" cellspacing="0" cellpadding="5" align="center">'
            .'<tr>'
            .'<td class="colhead" align="left">'.$colSubject.'</td>'
            .'<td class="colhead" align="center">'.$colSender.'</td>'
            .'<td class="colhead" align="center"><nobr>'.$colAdded.'</nobr></td>'
            .'<td class="colhead" align="center">'.$colAnswered.'</td>'
            .'<td class="colhead" align="center"><nobr>'.$colAction.'</nobr></td>'
            .'</tr>'
            .$rows
            .'<tr><td class="rowfollow" align="right" colspan="5">'
            .'<input type="button" value="'.$checkAll.'" onclick="this.value=check(form, \''.$checkAll.'\', \''.$uncheckAll.'\')" />'
            .'<input type="submit" name="setdealt" value="'.$submitSet.'" />'
            .'<input type="submit" name="delete" value="'.$submitDelete.'" />'
            .'</td></tr>'
            .'</table>'
            .'</form>'
            .$pager;

        return $this->wrap($title, $body);
    }

    // ─── View single PM ──────────────────────────────────────────────────

    private function renderViewPm(Request $request, User $user, array $lang): Response
    {
        $pmid = (int) $request->query('pmid', 0);
        if ($pmid <= 0) {
            abort(422, 'Invalid pmid.');
        }

        $rows = NexusDB::select('SELECT * FROM staffmessages WHERE id = '.$pmid);
        $arr4 = $rows ? (array) ($rows[0] ?? []) : [];
        if (empty($arr4)) {
            abort(404, 'Message not found.');
        }

        $this->authorizeStaffMessage($arr4, $user);

        $answeredby = get_username((int) ($arr4['answeredby'] ?? 0));
        $sender = is_numeric($arr4['sender'] ?? '') && (int) $arr4['sender'] > 0
            ? get_username((int) $arr4['sender'])
            : htmlspecialchars((string) ($lang['text_system'] ?? 'System'));

        $subject = htmlspecialchars((string) ($arr4['subject'] ?? ''));
        $isAnswered = (int) ($arr4['answered'] ?? 0) === 1;
        $colspan = $isAnswered ? '3' : '2';
        $width = $isAnswered ? '33' : '50';
        $title = htmlspecialchars((string) ($lang['head_view_staff_pm'] ?? 'View Staff PM'));

        $colFrom = htmlspecialchars((string) ($lang['col_from'] ?? 'From'));
        $colAnsweredBy = htmlspecialchars((string) ($lang['col_answered_by'] ?? 'Answered by'));
        $colDate = htmlspecialchars((string) ($lang['col_date'] ?? 'Date'));
        $textReply = htmlspecialchars((string) ($lang['text_reply'] ?? 'Reply'));
        $textMark = htmlspecialchars((string) ($lang['text_mark_answered'] ?? 'Mark answered'));
        $textDelete = htmlspecialchars((string) ($lang['text_delete'] ?? 'Delete'));

        $answerColHdr = $isAnswered
            ? '<td width="34%" class="colhead" align="left">'.$colAnsweredBy.'</td>'
            : '';
        $answerColVal = $isAnswered
            ? '<td class="rowfollow" align="left">'.$answeredby.'</td>'
            : '';

        $answerContent = '';
        if ($isAnswered && ($arr4['answer'] ?? '') !== '') {
            $answerContent = '<tr><td colspan="'.$colspan.'" align="left">'.format_comment((string) $arr4['answer']).'</td></tr>';
        }

        $actionLinks = '';
        if (! $isAnswered) {
            $returnParam = urlencode((string) ($request->query('return', '')));
            $actionLinks = '[ <a href="/staffbox.php?action=answermessage&receiver='.(int) ($arr4['sender'] ?? 0).'&answeringto='.$pmid.'">'.$textReply.'</a> ] '
                .'[ <a href="/staffbox.php?action=setanswered&id='.$pmid.'&return='.$returnParam.'">'.$textMark.'</a> ] ';
        }
        $actionLinks .= '[ <a href="/staffbox.php?action=deletestaffmessage&id='.$pmid.'">'.$textDelete.'</a> ]';

        $body = '<h1 align="center">'
            .'<a class="faqlink" href="/staffbox.php">'.htmlspecialchars((string) ($lang['text_staff_pm'] ?? 'Staff PM')).'</a>'
            .'-->'.$subject
            .'</h1>'
            .'<table width="737" border="0" cellpadding="4" cellspacing="0">'
            .'<tr>'
            .'<td width="'.$width.'%" class="colhead" align="left">'.$colFrom.'</td>'
            .$answerColHdr
            .'<td width="'.$width.'%" class="colhead" align="left">'.$colDate.'</td>'
            .'</tr>'
            .'<tr>'
            .'<td class="rowfollow" align="left">'.$sender.'</td>'
            .$answerColVal
            .'<td class="rowfollow" align="left">'.gettime((string) ($arr4['added'] ?? '')).'</td>'
            .'</tr>'
            .'<tr><td colspan="'.$colspan.'" align="left">'.format_comment((string) ($arr4['msg'] ?? '')).'</td></tr>'
            .$answerContent
            .'<tr><td colspan="'.$colspan.'" align="right">'
            .'<font color="white">'.$actionLinks.'</font>'
            .'</td></tr>'
            .'</table>';

        return $this->wrap($title, $body);
    }

    // ─── Answer compose form ─────────────────────────────────────────────

    private function renderAnswerForm(Request $request, User $user, array $lang): Response
    {
        $answeringto = (int) $request->query('answeringto', 0);
        $receiver = (int) $request->query('receiver', 0);
        if ($receiver <= 0) {
            abort(422, 'Invalid receiver.');
        }

        $receiverRows = NexusDB::select('SELECT * FROM users WHERE id = '.$receiver);
        $receiverUser = $receiverRows ? ($receiverRows[0] ?? null) : null;
        if ($receiverUser === null) {
            abort(404, htmlspecialchars((string) ($lang['std_no_user_id'] ?? 'No such user.')));
        }

        $staffMsgRows = NexusDB::select('SELECT * FROM staffmessages WHERE id = '.$answeringto);
        $staffmsg = $staffMsgRows ? (array) ($staffMsgRows[0] ?? []) : [];
        $this->authorizeStaffMessage($staffmsg, $user);

        $title = htmlspecialchars((string) ($lang['head_answer_to_staff_pm'] ?? 'Answer'));
        $returnto = (string) ($request->query('returnto') ?? $request->server('HTTP_REFERER', ''));
        $msgSubject = htmlspecialchars((string) ($staffmsg['subject'] ?? ''));
        $textAns = htmlspecialchars((string) ($lang['text_answering_to'] ?? 'Answering to'));
        $textSentBy = htmlspecialchars((string) ($lang['text_sent_by'] ?? ', sent by'));

        $composeTitle = $textAns
            .'<a href="/staffbox.php?action=viewpm&pmid='.$answeringto.'">'.$msgSubject.'</a>'
            .$textSentBy.get_username((int) ($staffmsg['sender'] ?? 0));

        // begin_compose renders the compose textarea widget.
        ob_start();
        begin_compose($composeTitle, 'reply', '', false);
        end_compose();
        $composeHtml = (string) ob_get_clean();

        $returntoHidden = $returnto !== ''
            ? '<input type="hidden" name="returnto" value="'.htmlspecialchars($returnto, ENT_QUOTES).'">'
            : '';

        $body = '<form method="post" id="compose" name="message" action="/staffbox.php?action=takeanswer">'
            .$returntoHidden
            .'<input type="hidden" name="receiver" value="'.$receiver.'">'
            .'<input type="hidden" name="answeringto" value="'.$answeringto.'">'
            .$composeHtml
            .'</form>';

        return $this->wrap($title, $body);
    }

    // ─── POST: send answer ────────────────────────────────────────────────

    private function handleTakeAnswer(Request $request, User $user, array $lang): RedirectResponse
    {
        $receiver = (int) $request->input('receiver', 0);
        $answeringto = (int) $request->input('answeringto', 0);
        if ($receiver <= 0) {
            abort(422, 'Invalid receiver.');
        }

        $msg = trim((string) $request->input('body', ''));
        if ($msg === '') {
            abort(422, htmlspecialchars((string) ($lang['std_body_is_empty'] ?? 'Body is empty.')));
        }

        $this->authorizeStaffMessageById($answeringto, $user);

        $subject = (string) StaffMessage::query()->findOrFail($answeringto)->toArray()['subject'];

        Message::add([
            'sender' => (int) $user->id,
            'receiver' => $receiver,
            'subject' => $subject,
            'added' => now(),
            'msg' => $msg,
        ]);

        NexusDB::table('staffmessages')
            ->where('id', $answeringto)
            ->update([
                'answer' => $msg,
                'answered' => '1',
                'answeredby' => (int) $user->id,
            ]);

        $this->forgetStaffCache();

        return redirect('/staffbox.php?action=viewpm&pmid='.$answeringto);
    }

    // ─── GET: delete ─────────────────────────────────────────────────────

    private function handleDelete(Request $request, User $user): RedirectResponse
    {
        $id = (int) $request->query('id', 0);
        if ($id <= 0) {
            return redirect('/staffbox.php');
        }

        $this->authorizeStaffMessageById($id, $user);

        NexusDB::statement('DELETE FROM staffmessages WHERE id = '.$id);
        $this->forgetStaffCache();

        return redirect('/staffbox.php');
    }

    // ─── GET: set answered ───────────────────────────────────────────────

    private function handleSetAnswered(Request $request, User $user): RedirectResponse
    {
        $id = (int) $request->query('id', 0);
        if ($id > 0) {
            $this->authorizeStaffMessageById($id, $user);
            NexusDB::statement(
                'UPDATE staffmessages SET answered = 1, answeredby = '.(int) $user->id.' WHERE id = '.$id
            );
            $this->forgetStaffCache();
        }

        $return = (string) $request->query('return', '');

        return redirect('/staffbox.php'.($return !== '' ? '?'.$return : ''));
    }

    // ─── POST: bulk mark/delete ──────────────────────────────────────────

    private function handleTakeContactAnswered(Request $request, User $user, array $lang): RedirectResponse
    {
        $ids = $request->input('setanswered', []);
        if (empty($ids)) {
            abort(422, htmlspecialchars((string) ($lang['std_sorry'] ?? 'Sorry.')));
        }

        $idList = implode(', ', array_map('intval', (array) $ids));

        if ($request->input('setdealt') !== null) {
            foreach (NexusDB::select('SELECT * FROM staffmessages WHERE answered = 0 AND id IN ('.$idList.')') as $arr) {
                $this->authorizeStaffMessage((array) $arr, $user);
                NexusDB::statement(
                    'UPDATE staffmessages SET answered = 1, answeredby = '.(int) $user->id.' WHERE id = '.(int) $arr['id']
                );
            }
        } elseif ($request->input('delete') !== null) {
            foreach (NexusDB::select('SELECT * FROM staffmessages WHERE id IN ('.$idList.')') as $arr) {
                $this->authorizeStaffMessage((array) $arr, $user);
                NexusDB::statement('DELETE FROM staffmessages WHERE id = '.(int) $arr['id']);
            }
        }

        $this->forgetStaffCache();

        return redirect('/staffbox.php');
    }

    // ─── Authorization helper ────────────────────────────────────────────

    /**
     * @param  array<string,mixed>  $msg
     */
    private function authorizeStaffMessage(array $msg, User $user): void
    {
        if (user_can('staffmem')) {
            return;
        }
        $permission = $msg['permission'] ?? null;
        if (
            empty($permission)
            || ! in_array($permission, ToolRepository::listUserAllPermissions((int) $user->id), true)
        ) {
            abort(403, 'Access denied.');
        }
    }

    private function authorizeStaffMessageById(int $id, User $user): void
    {
        $rows = NexusDB::select('SELECT * FROM staffmessages WHERE id = '.$id);
        $msg = $rows ? (array) ($rows[0] ?? []) : [];
        $this->authorizeStaffMessage($msg, $user);
    }

    // ─── Cache helpers ───────────────────────────────────────────────────

    private function forgetStaffCache(): void
    {
        $cache = $GLOBALS['Cache'] ?? null;
        if (is_object($cache) && method_exists($cache, 'delete_value')) {
            $cache->delete_value('staff_new_message_count');
            $cache->delete_value('staff_message_count');
        }
        if (function_exists('clear_staff_message_cache')) {
            clear_staff_message_cache();
        }
    }

    // ─── Pager ───────────────────────────────────────────────────────────

    private function renderPager(int $count, int $page, string $baseUrl): string
    {
        if ($count <= self::PER_PAGE) {
            return '';
        }
        $totalPages = (int) ceil($count / self::PER_PAGE);
        $links = '';
        if ($page > 0) {
            $links .= '<a href="'.$baseUrl.'page='.($page - 1).'">&lt;&lt; Prev</a> ';
        }
        $links .= '<b>'.($page + 1).' / '.$totalPages.'</b>';
        if ($page + 1 < $totalPages) {
            $links .= ' <a href="'.$baseUrl.'page='.($page + 1).'">Next &gt;&gt;</a>';
        }

        return '<p align="center">'.$links.'</p>';
    }

    // ─── Wrap & lang ─────────────────────────────────────────────────────

    private function wrap(string $title, string $body): Response
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5);
        $html = <<<HTML
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head>
<body>
{$body}
</body></html>
HTML;

        return new Response($html);
    }

    /** @return array<string,string> */
    private function loadLang(): array
    {
        $path = base_path(get_langfile_path('staffbox.php'));
        $lang_staffbox = [];
        if (is_file($path)) {
            require $path;
        }

        return is_array($lang_staffbox) ? $lang_staffbox : [];
    }
}

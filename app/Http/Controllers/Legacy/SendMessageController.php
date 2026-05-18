<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

class SendMessageController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $viewer = $this->context->user();
        if ($viewer === null) {
            abort(401);
        }
        if (($viewer->parked ?? 'no') === 'yes') {
            abort(403, 'Your account is parked.');
        }

        $receiver = (int) $request->query('receiver', 0);
        if ($receiver <= 0) {
            abort(422, 'Invalid receiver id.');
        }

        $recipient = NexusDB::table('users')
            ->where('id', $receiver)
            ->first(['id', 'username']);
        if ($recipient === null) {
            abort(404, 'No such user.');
        }

        $replyto = (string) $request->query('replyto', '');
        if ($replyto !== '' && ! ctype_digit($replyto)) {
            abort(403, 'Permission denied.');
        }
        $replytoId = $replyto === '' ? 0 : (int) $replyto;

        $subject = '';
        $body = '';
        if ($replytoId > 0) {
            $msg = NexusDB::table('messages')
                ->where('id', $replytoId)
                ->first(['id', 'sender', 'receiver', 'subject', 'msg']);
            if ($msg === null || (int) $msg->receiver !== (int) $viewer->id) {
                abort(403, 'Permission denied.');
            }

            $body = ((string) $msg->msg)."\n\n-------- [url=userdetails.php?id="
                .((int) $viewer->id).']'.((string) $viewer->username)
                .'[/url][i] Wrote at '.date('Y-m-d H:i:s').":[/i] --------\n";

            $subject = $this->bumpReplyCounter((string) $msg->subject);
        }

        $receiverName = $this->renderUsername($receiver, (string) ($recipient->username ?? ''));
        $returnto = $this->resolveReturnTo($request);

        $bodyEsc = htmlspecialchars($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $subjectEsc = htmlspecialchars($subject, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $returnHidden = $returnto === ''
            ? ''
            : '<input type="hidden" name="returnto" value="'.htmlspecialchars($returnto, ENT_QUOTES | ENT_HTML5, 'UTF-8').'">';

        $deleteCheckbox = '';
        $origMsgHidden = '';
        if ($replytoId > 0) {
            $checked = ((string) ($viewer->deletepms ?? 'no')) === 'yes' ? ' checked' : '';
            $deleteCheckbox = '<input type="checkbox" name="delete" value="yes"'.$checked.'>Delete message you are replying to';
            $origMsgHidden = '<input type="hidden" name="origmsg" value="'.$replytoId.'">';
        }

        $saveChecked = ((string) ($viewer->savepms ?? 'no')) === 'yes' ? ' checked' : '';
        $saveCheckbox = '<input type="checkbox" name="save" value="yes"'.$saveChecked.'>Save message to sendbox';

        $html = '<h1>Message to '.$receiverName.'</h1>'."\n"
            .'<form id="compose" name="compose" method="post" action="takemessage.php">'
            .'<input type="hidden" name="receiver" value="'.$receiver.'">'
            .$returnHidden
            .'<table border="0" cellspacing="0" cellpadding="5" align="center" width="600">'
            .'<tr><td class="rowhead">Subject</td>'
            .'<td><input type="text" name="subject" size="60" value="'.$subjectEsc.'"></td></tr>'
            .'<tr><td class="rowhead">Message</td>'
            .'<td><textarea name="body" rows="10" cols="60">'.$bodyEsc.'</textarea></td></tr>'
            .'<tr><td class="toolbox" colspan="2" align="center">'
            .$deleteCheckbox.$origMsgHidden.$saveCheckbox
            .'</td></tr>'
            .'<tr><td colspan="2" align="center"><input type="submit" value="Send"></td></tr>'
            .'</table></form>';

        return new Response($this->wrap('Send Message', $html));
    }

    private function bumpReplyCounter(string $subject): string
    {
        if (preg_match('/^Re:\s/', $subject) === 1) {
            return preg_replace('/^Re:\s(.*)$/', 'Re(2): \\1', $subject) ?? $subject;
        }
        if (preg_match('/^Re\(([0-9]+)\):\s/', $subject, $m) === 1) {
            $next = ((int) $m[1]) + 1;

            return preg_replace('/^Re\(([0-9]+)\):\s(.*)$/', 'Re('.$next.'): \\2', $subject) ?? $subject;
        }

        return 'Re: '.$subject;
    }

    private function renderUsername(int $userId, string $fallback): string
    {
        if (function_exists('get_username')) {
            return (string) call_user_func('get_username', $userId);
        }
        $nameEsc = htmlspecialchars($fallback, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return '<a href="userdetails.php?id='.$userId.'">'.$nameEsc.'</a>';
    }

    private function resolveReturnTo(Request $request): string
    {
        $returnto = (string) $request->query('returnto', '');
        if ($returnto !== '') {
            return $returnto;
        }

        return (string) $request->headers->get('referer', '');
    }

    private function wrap(string $title, string $body): string
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');

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

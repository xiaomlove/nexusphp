<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

class IpHistoryController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if (! user_can('userprofile', false, (int) $user->id)) {
            abort(403);
        }

        $userid = (int) $request->query('id', 0);
        if (! is_valid_id($userid)) {
            return $this->render('Error', $this->renderNotice('Invalid ID'));
        }

        $username = NexusDB::table('users')
            ->where('id', $userid)
            ->value('username');
        if ($username === null) {
            return $this->render('Error', $this->renderNotice('User not found'));
        }

        $distinctIps = (int) NexusDB::table('iplog')
            ->where('userid', $userid)
            ->distinct()
            ->count('access');
        $countrows = $distinctIps + 1;

        $order = (string) $request->query('order', '');
        [$pagertop, $pagerbottom, $limit] = pager(
            self::PER_PAGE,
            $countrows,
            'iphistory.php?id='.$userid.'&order='.urlencode($order).'&',
        );

        $rows = NexusDB::select(
            'SELECT u.id, u.ip AS ip, last_access AS access FROM users AS u WHERE u.id = '.$userid.' '
            .'UNION DISTINCT '
            .'SELECT u.id, iplog.ip AS ip, iplog.access AS access FROM users AS u '
            .'RIGHT JOIN iplog ON u.id = iplog.userid WHERE u.id = '.$userid.' '
            .'ORDER BY access DESC '.$limit
        );

        $body = '<h1 align="center">Historical IP addresses used by '
            .get_username($userid).'</h1>'."\n";

        if ($countrows > self::PER_PAGE) {
            $body .= $pagertop;
        }

        $body .= '<table width="500" border="1" cellspacing="0" cellpadding="5" align="center">'."\n"
            .'<tr>'
            .'<td class="colhead">Last access</td>'
            .'<td class="colhead">IP</td>'
            .'<td class="colhead">Hostname</td>'
            .'</tr>'."\n";

        foreach ($rows as $row) {
            $arr = (array) $row;
            $ip = (string) ($arr['ip'] ?? '');
            $access = (string) ($arr['access'] ?? '');

            $addr = '';
            $ipshow = '';
            if ($ip !== '') {
                $addr = $this->resolveHostname($ip);
                $ipshow = $this->renderIpCell($ip);
            }

            $date = htmlspecialchars((string) gettime($access));
            $body .= '<tr>'
                .'<td>'.$date.'</td>'."\n"
                .'<td>'.$ipshow.'</td>'."\n"
                .'<td>'.htmlspecialchars($addr).'</td>'
                .'</tr>'."\n";
        }

        $body .= '</table>'."\n".$pagerbottom;

        return $this->render('IP History Log for '.$username, $body);
    }

    private function resolveHostname(string $ip): string
    {
        $dom = @gethostbyaddr($ip);
        if ($dom === false || $dom === $ip || @gethostbyname($dom) !== $ip) {
            return 'N/A';
        }

        return (string) $dom;
    }

    private function renderIpCell(string $ip): string
    {
        $ipEsc = htmlspecialchars($ip);
        $ipQuoted = NexusDB::getPdo()->quote($ip);
        $countRows = NexusDB::select(
            'SELECT COUNT(*) AS c FROM ('
            .'SELECT u.id FROM users AS u WHERE u.ip = '.$ipQuoted.' '
            .'UNION '
            .'SELECT u.id FROM users AS u RIGHT JOIN iplog ON u.id = iplog.userid WHERE iplog.ip = '.$ipQuoted.' '
            .'GROUP BY u.id'
            .') AS ipsearch'
        );
        $count = $countRows ? (int) ((array) $countRows[0])['c'] : 0;

        $link = '<a href="ipsearch.php?ip='.urlencode($ip).'">'.$ipEsc.'</a>';

        return $count > 1
            ? $link.' <b>(<font class="striking">Dupe</font>)</b>'
            : $link;
    }

    private function renderNotice(string $message): string
    {
        return '<h2 align="center">'.htmlspecialchars($message).'</h2>'."\n";
    }

    private function render(string $title, string $body): Response
    {
        $titleEsc = htmlspecialchars($title);

        return new Response(<<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head>
<body>
{$body}</body>
</html>
HTML);
    }
}

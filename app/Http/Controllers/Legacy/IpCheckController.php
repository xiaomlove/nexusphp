<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/ipcheck.php` (deleted in the same PR).
 *
 * Moderator+ admin tool that lists groups of `enabled='yes'` users
 * sharing the same `ip` address. Linked from the modpanel
 * "Duplicate IP Check" entry (`ModpanelTableSeeder::url = 'ipcheck.php'`).
 *
 * Legacy quirks dropped on the way:
 *   - The `$_REQUEST['tab']` / `$_REQUEST['page']` parsing was
 *     present in the source but the matching `<ul class="menu">`
 *     was commented out — neither variable was ever read past the
 *     branch decision, so both are gone.
 *   - The inner `if (get_user_class() >= UC_MODERATOR || $CURUSER['guard'] == 'yes')`
 *     was dead — the outer gate already required moderator+, so the
 *     `else` "only for Team" branch was unreachable.
 *
 * Drive-by hardening: `email` and `ip` values are now passed through
 * `htmlspecialchars()` before splicing into HTML (a reflected XSS
 * in a moderator-only tool, but cheap to fix here).
 */
class IpCheckController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_MODERATOR) {
            abort(403);
        }

        return $this->render('Duplicate IP users', $this->renderTable());
    }

    private function renderTable(): string
    {
        $duplicates = NexusDB::select(
            "SELECT count(*) AS dupl, ip FROM users
             WHERE enabled = 'yes' AND ip <> '' AND ip <> '127.0.0.0'
             GROUP BY ip ORDER BY dupl DESC, ip"
        );

        $html = '<h1>Duplicate IP users</h1>'."\n"
            .'<table class="main" border="1" cellspacing="0" cellpadding="5">'."\n"
            .'<tr align="center">'
            .'<td class="colhead" width="90">User</td>'
            .'<td class="colhead" width="70">Email</td>'
            .'<td class="colhead" width="70">Registered</td>'
            .'<td class="colhead" width="75">Last access</td>'
            .'<td class="colhead" width="70">Downloaded</td>'
            .'<td class="colhead" width="70">Uploaded</td>'
            .'<td class="colhead" width="45">Ratio</td>'
            .'<td class="colhead" width="125">IP</td>'
            .'<td class="colhead" width="40">Peer</td>'
            .'</tr>'."\n";

        $stripeIndex = 0;
        $lastIp = '';

        foreach ($duplicates as $rasObj) {
            $ras = (array) $rasObj;
            if ((int) $ras['dupl'] <= 1) {
                break;
            }
            if ($lastIp === $ras['ip']) {
                continue;
            }

            $rows = NexusDB::table('users')
                ->where('ip', $ras['ip'])
                ->orderBy('id')
                ->select([
                    'id', 'username', 'email', 'added', 'last_access',
                    'downloaded', 'uploaded', 'ip', 'warned', 'donor', 'enabled',
                ])
                ->get();

            if ($rows->count() < 2) {
                continue;
            }

            $stripeIndex++;
            foreach ($rows as $row) {
                $arr = (array) $row;
                $html .= $this->renderRow($arr, $stripeIndex);
            }
            $lastIp = (string) $ras['ip'];
        }

        $html .= '</table>'."\n";

        return $html;
    }

    /**
     * @param  array<string,mixed>  $arr
     */
    private function renderRow(array $arr, int $stripeIndex): string
    {
        $added = (string) ($arr['added'] ?? '');
        if ($added === '0000-00-00 00:00:00' || $added === '') {
            $added = '-';
        } else {
            $added = substr($added, 0, 10);
        }

        $lastAccess = (string) ($arr['last_access'] ?? '');
        if ($lastAccess === '0000-00-00 00:00:00' || $lastAccess === '') {
            $lastAccess = '-';
        } else {
            $lastAccess = substr($lastAccess, 0, 10);
        }

        $downloadedRaw = (int) ($arr['downloaded'] ?? 0);
        $uploadedRaw = (int) ($arr['uploaded'] ?? 0);

        $ratio = $downloadedRaw !== 0
            ? number_format($uploadedRaw / $downloadedRaw, 3)
            : '---';
        $ratioHtml = '<font color="'.get_ratio_color($ratio).'">'.htmlspecialchars($ratio).'</font>';

        $downloaded = mksize($downloadedRaw);
        $uploaded = mksize($uploadedRaw);

        $bg = $stripeIndex % 2 === 0 ? '' : ' bgcolor="ECE9D8"';

        $ip = (string) ($arr['ip'] ?? '');
        $ipEsc = htmlspecialchars($ip);
        $email = htmlspecialchars((string) ($arr['email'] ?? ''));

        $peerCount = (int) NexusDB::table('peers')
            ->where('ip', $ip)
            ->where('userid', (int) ($arr['id'] ?? 0))
            ->count();
        $peerCell = $peerCount > 0 ? 'ja' : 'nein';

        return '<tr'.$bg.'>'
            .'<td align="left">'.get_username((int) ($arr['id'] ?? 0)).'</td>'
            .'<td align="center">'.$email.'</td>'
            .'<td align="center">'.htmlspecialchars($added).'</td>'
            .'<td align="center">'.htmlspecialchars($lastAccess).'</td>'
            .'<td align="center">'.htmlspecialchars((string) $downloaded).'</td>'
            .'<td align="center">'.htmlspecialchars((string) $uploaded).'</td>'
            .'<td align="center">'.$ratioHtml.'</td>'
            .'<td align="center"><a href="http://www.whois.sc/'.$ipEsc.'" target="_blank">'.$ipEsc.'</a></td>'
            .'<td align="center">'.$peerCell.'</td>'
            .'</tr>'."\n";
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

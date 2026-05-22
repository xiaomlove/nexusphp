<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/ipsearch.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. Authed staff tool — gated on
 * the `userprofile` permission (default class >= Moderator) — that
 * looks up every account that ever logged in from a given IP
 * (current `users.ip` column + every `iplog.ip` entry), with
 * optional CIDR / dotted-quad subnet mask support.
 *
 * The legacy script accepts:
 *   - `?ip=<ipv4>`            — required
 *   - `?mask=<dotted-quad>`   — optional, default `255.255.255.255`
 *     (single-IP match). `/N` CIDR shorthand expanded to dotted-
 *     quad before the SQL.
 *   - `?order=<col>`          — sort column whitelist
 *     (added/username/email/last_ip/last_access). Default sort by
 *     synthetic `access` desc.
 *   - `?page=<n>`             — pagination, 20/page.
 *
 * The search SQL is preserved bit-for-bit (UNION across `users.ip`
 * and `iplog`) but every dynamic value the query string can
 * influence (`$ip`, `$mask`, `$orderby`) is now constructed from
 * a hard whitelist before interpolation:
 *
 *   - `$ip` flows through `filter_var(..., FILTER_VALIDATE_IP)`,
 *   - `$mask` is either dotted-quad-validated by the legacy regex
 *     or expanded from a `/N` shorthand into a dotted-quad,
 *   - `$orderby` is a hard-coded literal from a `match` expression.
 *
 * URL preserved (`/ipsearch.php?ip=...&mask=...&order=...`) so the
 * existing modpanel "IP Search" deep link and any staff bookmarks
 * keep working without template changes.
 */
class IpSearchController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if (! user_can('userprofile')) {
            abort(403, 'Permission denied.');
        }

        $lang = $this->loadLangIpSearch();
        $rawIp = trim((string) $request->query('ip', ''));
        $rawMask = trim((string) $request->query('mask', ''));
        $order = (string) $request->query('order', '');

        $ip = '';
        if ($rawIp !== '') {
            if (! filter_var($rawIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                abort(422, (string) ($lang['std_invalid_ip'] ?? 'Invalid IP.'));
            }
            $ip = $rawIp;
        }

        $mask = $rawMask;
        if ($mask !== '' && $mask !== '255.255.255.255') {
            $mask = $this->normaliseMask($mask, $lang);
        }

        $body = $this->renderForm($ip, $mask, $lang);

        if ($ip !== '') {
            [$where1, $where2] = $this->buildWhereClauses($ip, $mask);
            $count = $this->countMatches($where1, $where2);

            if ($count === 0) {
                $body .= '<p align="center">'
                    .htmlspecialchars((string) ($lang['text_no_users_found'] ?? 'No users found.'))
                    .'</p>';
            } else {
                $body .= $this->renderResults($count, $ip, $mask, $order, $where1, $where2, $request, $lang);
            }
        }

        return $this->wrap(
            (string) ($lang['head_search_ip_history'] ?? 'IP Search'),
            $body,
        );
    }

    private function normaliseMask(string $mask, array $lang): string
    {
        $regex = '/^(((1?\d{1,2})|(2[0-4]\d)|(25[0-5]))(\.\b|$)){4}$/';

        if (str_starts_with($mask, '/')) {
            $n = substr($mask, 1);
            if (! ctype_digit($n) || (int) $n < 0 || (int) $n > 32) {
                abort(422, (string) ($lang['std_invalid_subnet_mask'] ?? 'Invalid subnet mask.'));
            }
            $expanded = long2ip((int) (2 ** 32 - 2 ** (32 - (int) $n)));
            if ($expanded === false) {
                abort(422, (string) ($lang['std_invalid_subnet_mask'] ?? 'Invalid subnet mask.'));
            }

            return $expanded;
        }

        if (! preg_match($regex, $mask)) {
            abort(422, (string) ($lang['std_invalid_subnet_mask'] ?? 'Invalid subnet mask.'));
        }

        return $mask;
    }

    /** @return array{0:string,1:string} */
    private function buildWhereClauses(string $ip, string $mask): array
    {
        // Both `$ip` and `$mask` are validated IPv4 strings — the
        // result of either `FILTER_VALIDATE_IP` (for $ip) or
        // `long2ip` / regex-validated dotted-quad (for $mask) — so
        // injection through the SQL string is impossible.
        if ($mask === '' || $mask === '255.255.255.255') {
            return [
                "u.ip = '".$ip."'",
                "iplog.ip = '".$ip."'",
            ];
        }

        return [
            "INET_ATON(u.ip) & INET_ATON('".$mask."') = INET_ATON('".$ip."') & INET_ATON('".$mask."')",
            "INET_ATON(iplog.ip) & INET_ATON('".$mask."') = INET_ATON('".$ip."') & INET_ATON('".$mask."')",
        ];
    }

    private function countMatches(string $where1, string $where2): int
    {
        $sql = "SELECT COUNT(*) AS c FROM (
            SELECT u.id FROM users AS u WHERE $where1
            UNION SELECT u.id FROM users AS u RIGHT JOIN iplog ON u.id = iplog.userid WHERE $where2
            GROUP BY u.id
        ) AS ipsearch";

        $rows = NexusDB::select($sql);

        return (int) ($rows[0]['c'] ?? 0);
    }

    private function renderForm(string $ip, string $mask, array $lang): string
    {
        $heading = htmlspecialchars((string) ($lang['text_search_ip_history'] ?? 'Search IP history'));
        $rowIp = htmlspecialchars((string) ($lang['row_ip'] ?? 'IP'));
        $rowMask = htmlspecialchars((string) ($lang['row_subnet_mask'] ?? 'Subnet mask'));
        $submitSearch = htmlspecialchars((string) ($lang['submit_search'] ?? 'Search'), ENT_QUOTES);
        $ipEsc = htmlspecialchars($ip, ENT_QUOTES);
        $maskEsc = htmlspecialchars($mask, ENT_QUOTES);

        return '<h1 align="center">'.$heading.'</h1>'
            .'<form method="get" action="/ipsearch.php">'
            .'<table align="center" border="1" cellspacing="0" width="115" cellpadding="5">'
            .'<tr><td class="rowhead">'.$rowIp.'<font color="red">*</font></td>'
            .'<td class="rowfollow"><input type="text" name="ip" size="40" value="'.$ipEsc.'" /></td></tr>'
            .'<tr><td class="rowhead"><nobr>'.$rowMask.'</nobr></td>'
            .'<td class="rowfollow"><input type="text" name="mask" size="40" value="'.$maskEsc.'" /></td></tr>'
            .'<tr><td align="right" colspan="2"><input type="submit" value="'.$submitSearch.'" /></td></tr>'
            .'</table></form>';
    }

    private function renderResults(
        int $count,
        string $ip,
        string $mask,
        string $order,
        string $where1,
        string $where2,
        Request $request,
        array $lang,
    ): string {
        $orderby = match ($order) {
            'added' => 'added DESC',
            'username' => 'UPPER(username) ASC',
            'email' => 'email ASC',
            'last_ip', 'last_access' => 'last_ip ASC',
            default => 'access DESC',
        };

        $page = max(1, (int) $request->query('page', 1));
        $totalPages = (int) max(1, ceil($count / self::PER_PAGE));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * self::PER_PAGE;

        $sql = "SELECT * FROM (
            SELECT u.id, u.username, u.ip AS ip, u.ip AS last_ip, u.last_access, u.last_access AS access,
                u.email, u.invited_by, u.added, u.class, u.uploaded, u.downloaded, u.donor, u.enabled, u.warned
            FROM users AS u
            WHERE $where1
            UNION SELECT u.id, u.username, iplog.ip AS ip, u.ip as last_ip, u.last_access,
                max(iplog.access) AS access, u.email, u.invited_by, u.added, u.class, u.uploaded,
                u.downloaded, u.donor, u.enabled, u.warned
            FROM users AS u
            RIGHT JOIN iplog ON u.id = iplog.userid
            WHERE $where2
            GROUP BY u.id
        ) AS ipsearch
        GROUP BY id
        ORDER BY $orderby
        LIMIT $offset, ".self::PER_PAGE;

        $rows = NexusDB::select($sql);

        $heading = $count.htmlspecialchars((string) ($lang['text_users_used_the_ip'] ?? ' users used the IP ')).htmlspecialchars($ip);
        $colUsername = htmlspecialchars((string) ($lang['col_username'] ?? 'Username'));
        $colLastIp = htmlspecialchars((string) ($lang['col_last_ip'] ?? 'Last IP'));
        $colLastAccess = htmlspecialchars((string) ($lang['col_last_access'] ?? 'Last access'));
        $colIpNum = htmlspecialchars((string) ($lang['col_ip_num'] ?? '#IPs'));
        $colLastAccessOn = htmlspecialchars((string) ($lang['col_last_access_on'] ?? 'Last access on'));
        $colAdded = htmlspecialchars((string) ($lang['col_added'] ?? 'Added'));
        $colInvitedBy = htmlspecialchars((string) ($lang['col_invited_by'] ?? 'Invited by'));
        $notAvailable = htmlspecialchars((string) ($lang['text_not_available'] ?? 'N/A'));

        $ipEsc = urlencode($ip);
        $maskEsc = urlencode($mask);

        $body = '<h1 align="center">'.$heading.'</h1>'
            .'<table border="1" cellspacing="0" cellpadding="5" align="center">'
            .'<tr>'
            .'<td class="colhead" align="center"><a class="colhead" href="?ip='.$ipEsc.'&mask='.$maskEsc.'&order=username">'.$colUsername.'</a></td>'
            .'<td class="colhead" align="center"><a class="colhead" href="?ip='.$ipEsc.'&mask='.$maskEsc.'&order=last_ip">'.$colLastIp.'</a></td>'
            .'<td class="colhead" align="center"><a class="colhead" href="?ip='.$ipEsc.'&mask='.$maskEsc.'&order=last_access">'.$colLastAccess.'</a></td>'
            .'<td class="colhead" align="center">'.$colIpNum.'</td>'
            .'<td class="colhead" align="center"><a class="colhead" href="?ip='.$ipEsc.'&mask='.$maskEsc.'">'.$colLastAccessOn.'</a></td>'
            .'<td class="colhead" align="center"><a class="colhead" href="?ip='.$ipEsc.'&mask='.$maskEsc.'&order=added">'.$colAdded.'</a></td>'
            .'<td class="colhead" align="center">'.$colInvitedBy.'</td>'
            .'</tr>';

        foreach ($rows as $row) {
            $row = (array) $row;
            $added = $this->formatTimestamp((string) ($row['added'] ?? ''), $notAvailable);
            $lastAccess = $this->formatTimestamp((string) ($row['last_access'] ?? ''), $notAvailable);
            $lastIpStr = ($row['last_ip'] ?? '') !== '' ? htmlspecialchars((string) $row['last_ip']) : $notAvailable;
            $userId = (int) ($row['id'] ?? 0);
            $iphistory = (int) NexusDB::table('iplog')
                ->where('userid', $userId)
                ->distinct()
                ->count('ip');

            $invitedBy = (int) ($row['invited_by'] ?? 0) > 0
                ? get_username((int) $row['invited_by'])
                : $notAvailable;

            $body .= '<tr>'
                .'<td align="center">'.get_username($userId).'</td>'
                .'<td align="center">'.$lastIpStr.'</td>'
                .'<td align="center">'.$lastAccess.'</td>'
                .'<td align="center"><a href="iphistory.php?id='.$userId.'">'.$iphistory.'</a></td>'
                .'<td align="center">'.gettime((string) ($row['access'] ?? '')).'</td>'
                .'<td align="center">'.gettime((string) ($row['added'] ?? '')).'</td>'
                .'<td align="center">'.$invitedBy.'</td>'
                .'</tr>';
        }
        $body .= '</table>';
        $body .= $this->renderPager($count, $page, $totalPages, $ipEsc, $maskEsc, $order);

        return $body;
    }

    private function formatTimestamp(string $value, string $fallback): string
    {
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return $fallback;
        }

        return gettime($value);
    }

    private function renderPager(int $count, int $page, int $totalPages, string $ip, string $mask, string $order): string
    {
        if ($count <= self::PER_PAGE) {
            return '';
        }
        $base = '/ipsearch.php?ip='.$ip.'&mask='.$mask.'&order='.urlencode($order);
        $links = '';
        if ($page > 1) {
            $links .= '<a href="'.$base.'&page='.($page - 1).'">&lt;&lt; Prev</a> ';
        }
        $links .= '<b>'.$page.' / '.$totalPages.'</b>';
        if ($page < $totalPages) {
            $links .= ' <a href="'.$base.'&page='.($page + 1).'">Next &gt;&gt;</a>';
        }

        return '<p align="center">'.$links.'</p>';
    }

    /** @return array<string,string> */
    private function loadLangIpSearch(): array
    {
        $path = base_path(get_langfile_path('ipsearch.php'));
        $lang_ipsearch = [];
        if (is_file($path)) {
            require $path;
        }

        return is_array($lang_ipsearch) ? $lang_ipsearch : [];
    }

    private function wrap(string $title, string $body): Response
    {
        $titleHtml = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5);
        $html = <<<HTML
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleHtml}</title>
</head>
<body>
{$body}
</body></html>
HTML;

        return new Response($html);
    }
}

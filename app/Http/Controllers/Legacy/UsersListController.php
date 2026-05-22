<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/users.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`.
 *
 * Original legacy flow:
 *   1. `require bittorrent.php` → `dbconn()` + `loggedinorreturn()`.
 *   2. `parked()` check + `user_can('viewuserlist', true)` gate.
 *   3. Renders a search form (username search, class filter, country
 *      filter, A-Z letter index).
 *   4. Paginated user listing (50/page) showing username, registered
 *      date, last access, class, country flag.
 *
 * Replacement contract (this controller):
 *   - Guest → middleware `auth.nexus:nexus-web` redirects to login.
 *   - Missing `viewuserlist` permission → `abort(403)`.
 *   - Authenticated → 200 with chrome-less HTML envelope.
 *   - Filters: `?search=`, `?class=`, `?country=`, `?letter=`.
 *   - Pagination via the offset from row count (50/page).
 */
class UsersListController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }

        if (! user_can('viewuserlist')) {
            abort(403);
        }

        $search = trim((string) $request->query('search', ''));
        $class = (string) $request->query('class', '-');
        $country = (int) $request->query('country', 0);
        $letter = trim((string) $request->query('letter', ''));
        $page = max(1, (int) $request->query('page', 1));

        if (strlen($letter) > 1) {
            $letter = '';
        }

        // Build WHERE
        $where = "status='confirmed'";
        $qParams = [];

        if ($letter !== '' && str_contains('0abcdefghijklmnopqrstuvwxyz', $letter)) {
            $letterEsc = NexusDB::getPdo()->quote($letter.'%');
            $where .= " AND username LIKE {$letterEsc}";
            $qParams[] = 'letter='.urlencode($letter);
        } elseif ($search !== '') {
            $searchEsc = NexusDB::getPdo()->quote('%'.$search.'%');
            $where .= " AND username LIKE {$searchEsc}";
            $qParams[] = 'search='.urlencode($search);
        }

        if ($class !== '-' && is_numeric($class)) {
            $where .= ' AND class='.(int) $class;
            $qParams[] = 'class='.(int) $class;
        }

        if ($country > 0) {
            $where .= ' AND country='.$country;
            $qParams[] = 'country='.$country;
        }

        $queryString = implode('&', $qParams);

        // Count
        $countRows = NexusDB::select("SELECT COUNT(*) AS c FROM users WHERE {$where}");
        $count = $countRows ? (int) ((array) $countRows[0])['c'] : 0;

        $totalPages = max(1, (int) ceil($count / self::PER_PAGE));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * self::PER_PAGE;

        // Fetch users
        $sql = 'SELECT users.id, users.class, users.added, users.last_access, '
            ."IF(users.country > 0, CONCAT('<img src=\"pic/flag/', countries.flagpic, '\" alt=\"', countries.name, '\">'), '---') AS country_html "
            .'FROM users LEFT JOIN countries ON users.country = countries.id '
            ."WHERE {$where} ORDER BY username LIMIT ".self::PER_PAGE." OFFSET {$offset}";

        $userRows = NexusDB::select($sql);

        // Render
        $body = '<h1>Users</h1>'."\n";
        $body .= $this->renderSearchForm($search, $class, $country);
        $body .= $this->renderLetterIndex($letter, $class, $country);
        $body .= $this->renderPager($count, $page, $totalPages, $queryString);
        $body .= $this->renderTable($userRows);
        $body .= $this->renderPager($count, $page, $totalPages, $queryString);

        $html = <<<HTML
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>Users</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }

    private function renderSearchForm(string $search, string $class, int $country): string
    {
        $searchEsc = htmlspecialchars($search);
        $form = '<form method="get" action="users.php">'."\n"
            .'Search: <input type="text" style="width:100px" name="search" value="'.$searchEsc.'"> '."\n"
            .'<select name="class"><option value="-">Any class</option>'."\n";

        for ($i = 0; $i <= 16; $i++) {
            $className = get_user_class_name($i);
            if (! $className) {
                break;
            }
            $selected = ($class !== '-' && (int) $class === $i) ? ' selected' : '';
            $form .= '<option value="'.$i.'"'.$selected.'>'.htmlspecialchars($className).'</option>'."\n";
        }
        $form .= '</select>'."\n";

        // Country selector
        $ctRows = NexusDB::table('countries')->orderBy('name')->select(['id', 'name'])->get();
        $form .= '<select name="country"><option value="0">Any country</option>'."\n";
        foreach ($ctRows as $ct) {
            $arr = (array) $ct;
            $selected = ($country === (int) $arr['id']) ? ' selected' : '';
            $form .= '<option value="'.htmlspecialchars((string) $arr['id']).'"'.$selected.'>'.htmlspecialchars((string) $arr['name']).'</option>'."\n";
        }
        $form .= '</select> <input type="submit" value="OK"></form>'."\n";

        return $form;
    }

    private function renderLetterIndex(string $currentLetter, string $class, int $country): string
    {
        $html = '<p>'."\n";
        for ($i = 97; $i < 123; $i++) {
            $l = chr($i);
            $L = chr($i - 32);
            if ($l === $currentLetter) {
                $html .= '<font class="gray"><b>'.$L.'</b></font> ';
            } else {
                $params = 'letter='.$l;
                if ($class !== '-') {
                    $params .= '&class='.(int) $class;
                }
                if ($country > 0) {
                    $params .= '&country='.$country;
                }
                $html .= '<a href="users.php?'.$params.'"><b>'.$L.'</b></a> ';
            }
        }
        $html .= '</p>'."\n";

        return $html;
    }

    private function renderTable(array $userRows): string
    {
        $body = '<table border="1" cellspacing="0" cellpadding="5">'."\n"
            .'<tr><td class="colhead" align="left">Username</td>'
            .'<td class="colhead">Registered</td>'
            .'<td class="colhead">Last Access</td>'
            .'<td class="colhead" align="left">Class</td>'
            .'<td class="colhead">Country</td></tr>'."\n";

        foreach ($userRows as $rowObj) {
            $arr = (array) $rowObj;
            $id = (int) ($arr['id'] ?? 0);
            $added = (string) ($arr['added'] ?? '');
            $lastAccess = (string) ($arr['last_access'] ?? '');
            $userClass = (int) ($arr['class'] ?? 0);
            $countryHtml = (string) ($arr['country_html'] ?? '---');

            $username = get_username($id);
            $className = get_user_class_name($userClass) ?: 'Unknown';
            $addedStr = $added ? htmlspecialchars(gettime($added, true, false)) : '-';
            $lastAccessStr = $lastAccess ? htmlspecialchars(gettime($lastAccess, true, false)) : '-';

            $body .= '<tr>'
                .'<td align="left">'.$username.'</td>'
                .'<td>'.$addedStr.'</td>'
                .'<td>'.$lastAccessStr.'</td>'
                .'<td align="left">'.htmlspecialchars($className).'</td>'
                .'<td align="center">'.$countryHtml.'</td>'
                .'</tr>'."\n";
        }

        $body .= '</table>'."\n";

        return $body;
    }

    private function renderPager(int $count, int $page, int $totalPages, string $queryString): string
    {
        if ($count <= self::PER_PAGE) {
            return '';
        }
        $sep = $queryString !== '' ? '&' : '';
        $links = '';
        if ($page > 1) {
            $links .= '<a href="users.php?'.$queryString.$sep.'page='.($page - 1).'">&lt;&lt; Prev</a> ';
        }
        $links .= '<b>'.$page.' / '.$totalPages.'</b>';
        if ($page < $totalPages) {
            $links .= ' <a href="users.php?'.$queryString.$sep.'page='.($page + 1).'">Next &gt;&gt;</a>';
        }

        return '<p align="center">'.$links.'</p>'."\n";
    }
}

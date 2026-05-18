<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/warned.php` (deleted in the same PR).
 *
 * Moderator+ listing of currently warned users. The page renders
 * a single `<form action="nowarn.php" method="post">` with one row
 * per warned account; the bulk action runs through the already
 * migrated `/nowarn.php` endpoint (`NoWarnController`). The submit
 * button + the `nowarned=nowarned` hidden input only render for
 * Administrator+, matching the legacy `if (get_user_class() >=
 * UC_ADMINISTRATOR)` block.
 *
 * Fix-in-passing: the legacy `SELECT ... WHERE warned=1 ...` query
 * compared the textual `users.warned` column ('yes' / 'no') to the
 * integer literal 1 — under MySQL implicit conversion the predicate
 * resolves to false, so the legacy page silently rendered an empty
 * table on every modern deploy. This controller matches against the
 * canonical `'yes'` value (same column the page's own count uses).
 */
class WarnedController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_MODERATOR) {
            abort(403);
        }

        $rows = NexusDB::table('users')
            ->where('warned', 'yes')
            ->where('enabled', 'yes')
            ->select(['id', 'username', 'class', 'added', 'last_access', 'uploaded', 'downloaded', 'warneduntil'])
            ->orderByRaw('uploaded / (CASE WHEN downloaded = 0 THEN 1 ELSE downloaded END)')
            ->get();

        $count = number_format($rows->count());
        $isAdmin = (int) $user->class >= (int) User::CLASS_ADMINISTRATOR;

        $body = '<h1>Warned Users ('.$count.')</h1>'."\n"
            .'<form action="nowarn.php" method="post">'."\n"
            .'<table border="1" width="675" cellspacing="0" cellpadding="2">'."\n"
            .'<tr align="center">'
            .'<td class="colhead" width="90">User Name</td>'
            .'<td class="colhead" width="70">Registered</td>'
            .'<td class="colhead" width="75">Last access</td>'
            .'<td class="colhead" width="75">User Class</td>'
            .'<td class="colhead" width="70">Downloaded</td>'
            .'<td class="colhead" width="70">UpLoaded</td>'
            .'<td class="colhead" width="45">Ratio</td>'
            .'<td class="colhead" width="125">End<br>Of Warning</td>'
            .'<td class="colhead" width="65">Remove<br>Warning</td>'
            .'<td class="colhead" width="65">Disable<br>Account</td>'
            .'</tr>'."\n";

        foreach ($rows as $row) {
            $arr = (array) $row;
            $body .= $this->renderRow($arr);
        }

        if ($isAdmin) {
            $body .= '<tr><td colspan="10" align="right">'
                .'<input type="submit" name="submit" value="Apply Changes">'
                .'</td></tr>'."\n"
                .'<input type="hidden" name="nowarned" value="nowarned">';
        }

        $body .= '</table></form>'."\n";

        return new Response($this->wrap('Warned Users', $body));
    }

    /**
     * @param  array<string,mixed>  $arr
     */
    private function renderRow(array $arr): string
    {
        $id = (int) ($arr['id'] ?? 0);
        $usernameEsc = htmlspecialchars((string) ($arr['username'] ?? ''));
        $added = (string) ($arr['added'] ?? '');
        $lastAccess = (string) ($arr['last_access'] ?? '');
        $addedDate = $this->dateOrDash($added);
        $lastAccessDate = $this->dateOrDash($lastAccess);

        $uploaded = (int) ($arr['uploaded'] ?? 0);
        $downloaded = (int) ($arr['downloaded'] ?? 0);

        $ratio = $downloaded > 0
            ? number_format($uploaded / $downloaded, 3)
            : '---';

        $uploadedStr = htmlspecialchars(Format::size((float) $uploaded));
        $downloadedStr = htmlspecialchars(Format::size((float) $downloaded));
        $classStr = htmlspecialchars((string) ($arr['class'] ?? ''));
        $warnedUntilEsc = htmlspecialchars((string) ($arr['warneduntil'] ?? ''));

        return '<tr>'
            .'<td align="left"><a href="userdetails.php?id='.$id.'">'.$usernameEsc.'</a></td>'
            .'<td align="center">'.htmlspecialchars($addedDate).'</td>'
            .'<td align="center">'.htmlspecialchars($lastAccessDate).'</td>'
            .'<td align="center">'.$classStr.'</td>'
            .'<td align="center">'.$downloadedStr.'</td>'
            .'<td align="center">'.$uploadedStr.'</td>'
            .'<td align="center">'.$ratio.'</td>'
            .'<td align="center">'.$warnedUntilEsc.'</td>'
            .'<td bgcolor="#008000" align="center">'
            .'<input type="checkbox" name="usernw[]" value="'.$id.'">'
            .'</td>'
            .'<td bgcolor="#FF000" align="center">'
            .'<input type="checkbox" name="desact[]" value="'.$id.'">'
            .'</td>'
            .'</tr>'."\n";
    }

    private function dateOrDash(string $value): string
    {
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return '-';
        }

        return substr($value, 0, 10);
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

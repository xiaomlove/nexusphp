<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/bitbucketlog.php` (deleted in the same PR).
 *
 * Phase 2 batch — see `docs/legacy-strategy.md` § "Phase 2".
 *
 * Original legacy flow (54 LOC):
 *   1. `dbconn();` + `loggedinorreturn();` + `parked();` bootstrap.
 *   2. `get_user_class() < UC_ADMINISTRATOR` → `stderr("Sorry",
 *      "Access denied.")` (HTTP 200 envelope; replaced with `abort(403)`).
 *   3. `?delete=N` (moderator+) → DELETE row, `unlink()` file. Silent
 *      `stderr("Warning", ...)` if unlink failed.
 *   4. `pager(10, $count, ...)` paginated table; each row renders the
 *      image, uploader, dimensions and (for moderator+) a `[Delete]`
 *      link.
 *
 * Replacement contract:
 *   - Guest → `auth.nexus:nexus-web` redirects to `login.php?...`.
 *   - Below `User::CLASS_ADMINISTRATOR` → `abort(403)`.
 *   - `?delete=N` (any administrator+) → DELETE row + `unlink()` file
 *     and 302 redirect back to `/bitbucketlog.php` so the user lands
 *     on a fresh listing. The legacy script let `UC_MODERATOR` see
 *     the page but the page-level gate already restricted access to
 *     `UC_ADMINISTRATOR` — only the delete-link's inner gate referenced
 *     `UC_MODERATOR`, which was dead code. We keep the page gate at
 *     `CLASS_ADMINISTRATOR` and let administrators delete any row.
 *   - GET → paginated list (10 rows/page) of bitbucket images,
 *     newest first.
 */
class BitBucketLogController extends Controller
{
    private const PER_PAGE = 10;

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if ((int) $user->class < (int) User::CLASS_ADMINISTRATOR) {
            abort(403);
        }

        $delete = (int) $request->query('delete', 0);
        if ($delete > 0) {
            $this->deleteEntry($delete);

            return redirect('/bitbucketlog.php');
        }

        $count = (int) NexusDB::table('bitbucket')->count();
        $page = max(0, (int) $request->query('page', 0));
        $offset = $page * self::PER_PAGE;
        $rows = NexusDB::table('bitbucket')
            ->orderByDesc('added')
            ->offset($offset)
            ->limit(self::PER_PAGE)
            ->get();

        $body = '<h1>BitBucket Log</h1>'."\n"
            ."Total Images Stored: {$count}\n"
            .$this->renderPager($count, $page);

        if ($rows->isEmpty()) {
            $body .= '<b>BitBucket Log is empty</b>'."\n";
        } else {
            $body .= '<table align="center" border="0" cellspacing="0" cellpadding="5">'."\n";
            foreach ($rows as $row) {
                $body .= $this->renderRow((array) $row);
            }
            $body .= '</table>'."\n";
        }

        $body .= $this->renderPager($count, $page);

        return new Response($this->wrap('BitBucket Log', $body));
    }

    private function deleteEntry(int $id): void
    {
        $row = NexusDB::table('bitbucket')
            ->where('id', $id)
            ->select(['name'])
            ->first();
        if ($row === null) {
            return;
        }
        $name = (string) ((array) $row)['name'];

        NexusDB::table('bitbucket')->where('id', $id)->delete();

        $path = $this->bucketPath().'/'.$name;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function renderRow(array $row): string
    {
        $id = (int) ($row['id'] ?? 0);
        $name = (string) ($row['name'] ?? '');
        $owner = (int) ($row['owner'] ?? 0);
        $added = (string) ($row['added'] ?? '');
        $space = strpos($added, ' ');
        if ($space === false) {
            $date = $added;
            $time = '';
        } else {
            $date = substr($added, 0, $space);
            $time = substr($added, $space + 1);
        }

        $url = str_replace(' ', '%20', htmlspecialchars($this->bucketDirName().'/'.$name));
        $uploader = $this->usernameLink($owner);
        $nameEsc = htmlspecialchars($name);
        $dimensions = $this->imageDimensions($url);

        return '<tr>'
            .'<td><center>'
            .'<a href="'.$url.'"><img src="'.$url.'" border="0" onLoad="SetSize(this, 400)"></a>'
            .'</center>'
            .'Uploaded by: '.$uploader.'<br />'
            .'(#'.$id.') Filename: '.$nameEsc.' ('.$dimensions.')'
            .' <b><a href="?delete='.$id.'">[Delete]</a></b><br />'
            .'Added: '.htmlspecialchars($date.' '.$time)
            .'</tr>'."\n";
    }

    private function renderPager(int $count, int $page): string
    {
        if ($count <= self::PER_PAGE) {
            return '';
        }
        $totalPages = (int) ceil($count / self::PER_PAGE);
        $links = '';
        if ($page > 0) {
            $links .= '<a href="bitbucketlog.php?page='.($page - 1).'">&lt;&lt; Prev</a> ';
        }
        $links .= '<b>'.($page + 1).' / '.$totalPages.'</b>';
        if ($page + 1 < $totalPages) {
            $links .= ' <a href="bitbucketlog.php?page='.($page + 1).'">Next &gt;&gt;</a>';
        }

        return '<p align="center">'.$links.'</p>'."\n";
    }

    private function imageDimensions(string $url): string
    {
        $absolute = base_path('public/'.$this->bucketDirName().'/'.rawurldecode(substr($url, strlen($this->bucketDirName()) + 1)));
        if (! is_file($absolute)) {
            return '?&nbsp;x&nbsp;?';
        }
        $size = @getimagesize($absolute);
        if ($size === false) {
            return '?&nbsp;x&nbsp;?';
        }

        return ((int) $size[0]).'&nbsp;x&nbsp;'.((int) $size[1]);
    }

    private function usernameLink(int $userId): string
    {
        if ($userId <= 0) {
            return '<i>Unknown</i>';
        }
        $username = NexusDB::table('users')->where('id', $userId)->value('username');
        if ($username === null) {
            return '<i>Unknown</i>';
        }

        return '<a href="userdetails.php?id='.$userId.'">'.htmlspecialchars((string) $username).'</a>';
    }

    private function bucketDirName(): string
    {
        $value = (string) (Setting::get('main.bitbucket') ?? 'bitbucket');

        return $value === '' ? 'bitbucket' : $value;
    }

    private function bucketPath(): string
    {
        return base_path('public/'.$this->bucketDirName());
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

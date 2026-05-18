<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Cheater;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Nexus\Database\NexusDB;

class CheaterboxController extends Controller
{
    private const PER_PAGE = 50;

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): RedirectResponse|Response
    {
        $viewer = $this->context->user();
        if ($viewer === null) {
            abort(401);
        }
        if (($viewer->parked ?? 'no') === 'yes') {
            abort(403, 'Your account is parked.');
        }
        if (! $this->canStaffMem($viewer)) {
            abort(403);
        }

        if ($request->isMethod('POST')) {
            return $this->handlePost($request, $viewer);
        }

        $count = (int) NexusDB::table('cheaters')->count();
        if ($count === 0) {
            return new Response($this->wrap('Cheaterbox', $this->notice('Oho', 'No suspect detected.')));
        }

        $page = max(0, (int) $request->query('page', 0));
        $offset = $page * self::PER_PAGE;
        $rows = NexusDB::table('cheaters')
            ->orderBy('dealtwith')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit(self::PER_PAGE)
            ->get();

        $body = '<h1 align="center">Cheaterbox</h1>'."\n"
            .'<form method="post" action="cheaterbox.php">'
            .'<table class="cheaterbox" border="1" cellspacing="0" cellpadding="5" align="center">'."\n"
            .'<tr>'
            .'<td class="colhead"><nobr>Added</nobr></td>'
            .'<td class="colhead">Suspect</td>'
            .'<td class="colhead"><nobr>Hit</nobr></td>'
            .'<td class="colhead">Torrent</td>'
            .'<td class="colhead">UL</td>'
            .'<td class="colhead">DL</td>'
            .'<td class="colhead"><nobr>Ann. time</nobr></td>'
            .'<td class="colhead"><nobr>Seeders</nobr></td>'
            .'<td class="colhead"><nobr>Leechers</nobr></td>'
            .'<td class="colhead">Comment</td>'
            .'<td class="colhead"><nobr>Dealt with</nobr></td>'
            .'<td class="colhead"><nobr>Action</nobr></td>'
            .'</tr>'."\n";

        foreach ($rows as $row) {
            $body .= $this->renderRow((array) $row);
        }

        $body .= '<tr><td class="colhead" colspan="12" style="text-align: right">'
            .'<input type="submit" name="setdealt" value="Set dealt">'
            .'<input type="submit" name="delete" value="Delete">'
            .'</td></tr>'
            .'</table></form>'."\n"
            .$this->renderPager($count, $page);

        return new Response($this->wrap('Cheaterbox', $body));
    }

    private function handlePost(Request $request, User $viewer): RedirectResponse|Response
    {
        $ids = $this->toIntIds($request->input('delcheater'));

        if ($request->boolean('setdealt') || $request->input('setdealt') !== null) {
            if ($ids === []) {
                return new Response('Select at least one record.', 422);
            }
            Cheater::query()
                ->whereIn('id', $ids)
                ->where('dealtwith', 0)
                ->update(['dealtwith' => 1, 'dealtby' => (int) $viewer->id]);
            $this->forgetCache();

            return redirect('/cheaterbox.php');
        }

        if ($request->boolean('delete') || $request->input('delete') !== null) {
            if ($ids === []) {
                return new Response('Select at least one record.', 422);
            }
            Cheater::query()->whereIn('id', $ids)->delete();
            $this->forgetCache();

            return redirect('/cheaterbox.php');
        }

        return redirect('/cheaterbox.php');
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function renderRow(array $row): string
    {
        $id = (int) ($row['id'] ?? 0);
        $userId = (int) ($row['userid'] ?? 0);
        $torrentId = (int) ($row['torrentid'] ?? 0);
        $uploadedBytes = (int) ($row['uploaded'] ?? 0);
        $downloadedBytes = (int) ($row['downloaded'] ?? 0);
        $anctime = (int) ($row['anctime'] ?? 0);
        $upspeed = $uploadedBytes > 0 && $anctime > 0 ? (int) floor($uploadedBytes / $anctime) : 0;
        $lespeed = $downloadedBytes > 0 && $anctime > 0 ? (int) floor($downloadedBytes / $anctime) : 0;

        $torrentName = NexusDB::table('torrents')->where('id', $torrentId)->value('name');
        if ($torrentName !== null) {
            $torrent = '<a href="details.php?id='.$torrentId.'">'
                .htmlspecialchars((string) $torrentName, ENT_QUOTES | ENT_HTML5, 'UTF-8').'</a>';
        } else {
            $torrent = 'Torrent does not exist.';
        }

        $dealtwith = ((int) ($row['dealtwith'] ?? 0)) === 1
            ? '<font color="green">Yes</font> - '.$this->renderUsername((int) ($row['dealtby'] ?? 0))
            : '<font color="red">No</font>';

        $uploaded = $this->mksize($uploadedBytes).($upspeed > 0 ? ' @ '.$this->mksize($upspeed).'/s' : '');
        $downloaded = $this->mksize($downloadedBytes).($lespeed > 0 ? ' @ '.$this->mksize($lespeed).'/s' : '');

        $added = $this->renderTime((string) ($row['added'] ?? ''));

        return '<tr>'
            .'<td class="rowfollow">'.$added.'</td>'
            .'<td class="rowfollow">'.$this->renderUsername($userId).'</td>'
            .'<td class="rowfollow">'.((int) ($row['hit'] ?? 0)).'</td>'
            .'<td class="rowfollow">'.$torrent.'</td>'
            .'<td class="rowfollow">'.$uploaded.'</td>'
            .'<td class="rowfollow">'.$downloaded.'</td>'
            .'<td class="rowfollow">'.$anctime.' sec</td>'
            .'<td class="rowfollow">'.((int) ($row['seeders'] ?? 0)).'</td>'
            .'<td class="rowfollow">'.((int) ($row['leechers'] ?? 0)).'</td>'
            .'<td class="rowfollow">'.htmlspecialchars((string) ($row['comment'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8').'</td>'
            .'<td class="rowfollow">'.$dealtwith.'</td>'
            .'<td class="rowfollow"><input type="checkbox" name="delcheater[]" value="'.$id.'" /></td>'
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
            $links .= '<a href="cheaterbox.php?page='.($page - 1).'">&lt;&lt; Prev</a> ';
        }
        $links .= '<b>'.($page + 1).' / '.$totalPages.'</b>';
        if ($page + 1 < $totalPages) {
            $links .= ' <a href="cheaterbox.php?page='.($page + 1).'">Next &gt;&gt;</a>';
        }

        return '<p align="center">'.$links.'</p>'."\n";
    }

    private function canStaffMem(User $viewer): bool
    {
        if (! function_exists('user_can')) {
            return (int) $viewer->class >= (int) User::CLASS_MODERATOR;
        }

        return (bool) call_user_func('user_can', 'staffmem', false, (int) $viewer->id);
    }

    private function forgetCache(): void
    {
        try {
            Cache::forget('staff_new_cheater_count');
        } catch (\Throwable) {
        }
    }

    private function renderUsername(int $userId): string
    {
        if ($userId <= 0) {
            return '-';
        }
        if (function_exists('get_username')) {
            return (string) call_user_func('get_username', $userId);
        }
        $name = NexusDB::table('users')->where('id', $userId)->value('username');
        if ($name === null) {
            return '-';
        }
        $nameEsc = htmlspecialchars((string) $name, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return '<a href="userdetails.php?id='.$userId.'">'.$nameEsc.'</a>';
    }

    private function renderTime(string $value): string
    {
        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return '-';
        }
        if (function_exists('gettime')) {
            return (string) call_user_func('gettime', $value, true, false);
        }

        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function mksize(int $bytes): string
    {
        if (function_exists('mksize')) {
            return (string) call_user_func('mksize', $bytes);
        }

        return (string) $bytes;
    }

    /**
     * @return list<int>
     */
    private function toIntIds(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $v) {
            $id = (int) $v;
            if ($id > 0) {
                $out[] = $id;
            }
        }

        return array_values(array_unique($out));
    }

    private function notice(string $title, string $message): string
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $messageEsc = htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return '<h1 align="center">'.$titleEsc.'</h1>'."\n"
            .'<p align="center">'.$messageEsc.'</p>'."\n";
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

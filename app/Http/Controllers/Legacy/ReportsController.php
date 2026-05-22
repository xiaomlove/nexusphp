<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/reports.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. Staff-only paginated listing
 * (10/page) of `reports` rows ordered by `dealtwith` then
 * descending `id`. The form posts to `/takeupdate.php` (already
 * migrated in Phase 2 batch #1 — see
 * `App\Http\Controllers\Legacy\TakeUpdateController`) which handles
 * setdealt + delete bulk actions and 302s back to `/reports.php`.
 *
 * Each row's `reportid` is dereferenced into the human-readable
 * "what's being reported" link (torrent / user / offer / forum
 * post / comment / subtitle); the legacy switch is preserved
 * verbatim.
 *
 * Permission: `staffmem` (default class >= Moderator). The
 * pre-existing `parked()` gate is preserved.
 *
 * URL stays `/reports.php` so:
 *   - `TakeUpdateController` keeps redirecting back to
 *     `/reports.php` after setdealt/delete (and its tests pin that
 *     redirect target),
 *   - any staff bookmarks
 *
 * keep working without further changes. The matching nginx
 * exact-location entry lives in
 * `.docker/openresty/sites/app.conf.template`. GET-only — the
 * form posts to `takeupdate.php`, not back to this URL.
 */
class ReportsController extends Controller
{
    private const PER_PAGE = 10;

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if (($user->parked ?? 'no') === 'yes') {
            abort(403, 'Your account is parked.');
        }
        if (! user_can('staffmem')) {
            abort(403, 'Permission denied.');
        }

        $lang = $this->loadLangReports();
        $count = (int) NexusDB::table('reports')->count();

        if ($count === 0) {
            return $this->wrap(
                (string) ($lang['head_reports'] ?? 'Reports'),
                '<h1 align="center">'.htmlspecialchars((string) ($lang['std_oho'] ?? 'Oho')).'</h1>'
                .'<p align="center">'.htmlspecialchars((string) ($lang['std_no_report'] ?? 'No reports.')).'</p>',
            );
        }

        $page = max(1, (int) $request->query('page', 1));
        $totalPages = (int) max(1, ceil($count / self::PER_PAGE));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * self::PER_PAGE;

        $rows = NexusDB::table('reports')
            ->orderBy('dealtwith', 'asc')
            ->orderByDesc('id')
            ->offset($offset)
            ->limit(self::PER_PAGE)
            ->get();

        $heading = htmlspecialchars((string) ($lang['text_reports'] ?? 'Reports'));
        $colAdded = htmlspecialchars((string) ($lang['col_added'] ?? 'Added'));
        $colReporter = htmlspecialchars((string) ($lang['col_reporter'] ?? 'Reporter'));
        $colReporting = htmlspecialchars((string) ($lang['col_reporting'] ?? 'Reporting'));
        $colType = htmlspecialchars((string) ($lang['col_type'] ?? 'Type'));
        $colReason = htmlspecialchars((string) ($lang['col_reason'] ?? 'Reason'));
        $colDealtWith = htmlspecialchars((string) ($lang['col_dealt_with'] ?? 'Dealt with'));
        $colAction = htmlspecialchars((string) ($lang['col_action'] ?? 'Action'));
        $submitSetDealt = htmlspecialchars((string) ($lang['submit_set_dealt'] ?? 'Set dealt'), ENT_QUOTES);
        $submitDelete = htmlspecialchars((string) ($lang['submit_delete'] ?? 'Delete'), ENT_QUOTES);
        $textYes = htmlspecialchars((string) ($lang['text_yes'] ?? 'Yes'));
        $textNo = htmlspecialchars((string) ($lang['text_no'] ?? 'No'));

        $body = '<h1 align="center">'.$heading.'</h1>'
            .'<form method="post" action="/takeupdate.php">'
            .'<table border="1" cellspacing="0" cellpadding="5" align="center">'
            .'<tr>'
            .'<td class="colhead"><nobr>'.$colAdded.'</nobr></td>'
            .'<td class="colhead">'.$colReporter.'</td>'
            .'<td class="colhead">'.$colReporting.'</td>'
            .'<td class="colhead"><nobr>'.$colType.'</nobr></td>'
            .'<td class="colhead">'.$colReason.'</td>'
            .'<td class="colhead"><nobr>'.$colDealtWith.'</nobr></td>'
            .'<td class="colhead"><nobr>'.$colAction.'</nobr></td>'
            .'</tr>';

        foreach ($rows as $row) {
            $row = (array) $row;
            $body .= $this->renderReportRow($row, $lang, $textYes, $textNo);
        }

        $body .= '<tr><td class="colhead" colspan="7" align="right">'
            .'<input type="submit" name="setdealt" value="'.$submitSetDealt.'" />'
            .'<input type="submit" name="delete" value="'.$submitDelete.'" />'
            .'</td></tr>'
            .'</table></form>';
        $body .= $this->renderPager($count, $page, $totalPages);

        return $this->wrap(
            (string) ($lang['head_reports'] ?? 'Reports'),
            $body,
        );
    }

    /**
     * @param  array<string,mixed>  $row
     */
    private function renderReportRow(array $row, array $lang, string $textYes, string $textNo): string
    {
        $dealtwith = (int) ($row['dealtwith'] ?? 0) > 0
            ? '<font color="green">'.$textYes.'</font> - '.get_username((int) ($row['dealtby'] ?? 0))
            : '<font color="red">'.$textNo.'</font>';

        [$type, $reporting] = $this->resolveReporting($row, $lang);

        $reasonHtml = htmlspecialchars((string) ($row['reason'] ?? ''));
        $addedHtml = gettime((string) ($row['added'] ?? ''));
        $reporter = get_username((int) ($row['addedby'] ?? 0));
        $reportId = (int) ($row['id'] ?? 0);

        return '<tr>'
            .'<td class="rowfollow"><nobr>'.$addedHtml.'</nobr></td>'
            .'<td class="rowfollow">'.$reporter.'</td>'
            .'<td class="rowfollow">'.$reporting.'</td>'
            .'<td class="rowfollow"><nobr>'.$type.'</nobr></td>'
            .'<td class="rowfollow">'.$reasonHtml.'</td>'
            .'<td class="rowfollow"><nobr>'.$dealtwith.'</nobr></td>'
            .'<td class="rowfollow"><input type="checkbox" name="delreport[]" value="'.$reportId.'" /></td>'
            .'</tr>';
    }

    /**
     * @param  array<string,mixed>  $row
     * @return array{0:string,1:string} `[type label, reporting link]`
     */
    private function resolveReporting(array $row, array $lang): array
    {
        $reportId = (int) ($row['reportid'] ?? 0);

        switch ((string) ($row['type'] ?? '')) {
            case 'torrent':
                $type = (string) ($lang['text_torrent'] ?? 'Torrent');
                $t = NexusDB::table('torrents')->where('id', $reportId)->select(['id', 'name'])->first();
                $reporting = $t === null
                    ? (string) ($lang['text_torrent_does_not_exist'] ?? 'Torrent does not exist.')
                    : '<a href="details.php?id='.(int) $t->id.'">'.htmlspecialchars((string) $t->name).'</a>';
                break;
            case 'user':
                $type = (string) ($lang['text_user'] ?? 'User');
                $u = NexusDB::table('users')->where('id', $reportId)->select(['id'])->first();
                $reporting = $u === null
                    ? (string) ($lang['text_user_does_not_exist'] ?? 'User does not exist.')
                    : get_username((int) $u->id);
                break;
            case 'offer':
                $type = (string) ($lang['text_offer'] ?? 'Offer');
                $o = NexusDB::table('offers')->where('id', $reportId)->select(['id', 'name'])->first();
                $reporting = $o === null
                    ? (string) ($lang['text_offer_does_not_exist'] ?? 'Offer does not exist.')
                    : '<a href="offers.php?id='.(int) $o->id.'&off_details=1">'.htmlspecialchars((string) $o->name).'</a>';
                break;
            case 'post':
                $type = (string) ($lang['text_forum_post'] ?? 'Forum post');
                $p = NexusDB::table('topics')
                    ->leftJoin('posts', 'posts.topicid', '=', 'topics.id')
                    ->where('posts.id', $reportId)
                    ->select(['topics.id AS topicid', 'topics.subject AS subject', 'posts.userid AS postuserid'])
                    ->first();
                if ($p === null) {
                    $reporting = (string) ($lang['text_post_does_not_exist'] ?? 'Post does not exist.');
                } else {
                    $reporting = (string) ($lang['text_post_id'] ?? 'Post id ')
                        .$reportId
                        .(string) ($lang['text_of_topic'] ?? ' of topic ')
                        .'<b><a href="forums.php?action=viewtopic&topicid='.(int) $p->topicid.'&page=p'.$reportId.'#pid'.$reportId.'">'
                        .htmlspecialchars((string) $p->subject).'</a></b>'
                        .(string) ($lang['text_by'] ?? ' by ')
                        .get_username((int) $p->postuserid);
                }
                break;
            case 'comment':
                $type = (string) ($lang['text_comment'] ?? 'Comment');
                $c = NexusDB::table('comments')->where('id', $reportId)->select(['id', 'user', 'torrent', 'offer'])->first();
                if ($c === null) {
                    $reporting = (string) ($lang['text_comment_does_not_exist'] ?? 'Comment does not exist.');
                } else {
                    if ((int) ($c->torrent ?? 0) > 0) {
                        $name = (string) (NexusDB::table('torrents')->where('id', (int) $c->torrent)->value('name') ?? '');
                        $url = 'details.php?id='.(int) $c->torrent.'#cid'.$reportId;
                        $of = (string) ($lang['text_of_torrent'] ?? ' of torrent ');
                    } elseif ((int) ($c->offer ?? 0) > 0) {
                        $name = (string) (NexusDB::table('offers')->where('id', (int) $c->offer)->value('name') ?? '');
                        $url = 'offers.php?id='.(int) $c->offer.'&off_details=1#cid'.$reportId;
                        $of = (string) ($lang['text_of_offer'] ?? ' of offer ');
                    } else {
                        $name = '';
                        $url = '#';
                        $of = ' unknown ';
                    }
                    $reporting = (string) ($lang['text_comment_id'] ?? 'Comment id ')
                        .$reportId
                        .$of
                        .'<b><a href="'.htmlspecialchars($url, ENT_QUOTES).'">'.htmlspecialchars($name).'</a></b>'
                        .(string) ($lang['text_by'] ?? ' by ')
                        .get_username((int) $c->user);
                }
                break;
            case 'subtitle':
                $type = (string) ($lang['text_subtitle'] ?? 'Subtitle');
                $s = NexusDB::table('subs')->where('id', $reportId)->select(['id', 'torrent_id', 'title'])->first();
                $reporting = $s === null
                    ? (string) ($lang['text_subtitle_does_not_exist'] ?? 'Subtitle does not exist.')
                    : '<a href="downloadsubs.php?torrentid='.(int) $s->torrent_id.'&subid='.(int) $s->id.'">'
                        .htmlspecialchars((string) $s->title).'</a>'
                        .(string) ($lang['text_for_torrent_id'] ?? ' for torrent id ')
                        .'<a href="details.php?id='.(int) $s->torrent_id.'">'.(int) $s->torrent_id.'</a>';
                break;
            default:
                $type = '';
                $reporting = '';
        }

        return [$type, $reporting];
    }

    private function renderPager(int $count, int $page, int $totalPages): string
    {
        if ($count <= self::PER_PAGE) {
            return '';
        }
        $links = '';
        if ($page > 1) {
            $links .= '<a href="/reports.php?page='.($page - 1).'">&lt;&lt; Prev</a> ';
        }
        $links .= '<b>'.$page.' / '.$totalPages.'</b>';
        if ($page < $totalPages) {
            $links .= ' <a href="/reports.php?page='.($page + 1).'">Next &gt;&gt;</a>';
        }

        return '<p align="center">'.$links.'</p>';
    }

    /** @return array<string,string> */
    private function loadLangReports(): array
    {
        $path = base_path(get_langfile_path('reports.php'));
        $lang_reports = [];
        if (is_file($path)) {
            require $path;
        }

        return is_array($lang_reports) ? $lang_reports : [];
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

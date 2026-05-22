<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/staff.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. Authed staff-roster page gated
 * on the `staffmem` permission (default class >= Moderator). Five
 * sections are rendered, each backed by a single DB query:
 *
 *   1. **First-line support**       — `users.support = 'yes'`
 *   2. **Movie critics**            — `users.picker = 'yes'`
 *   3. **Forum moderators**         — `forummods` table joined
 *   4. **General staff**            — `class > UC_VIP`, grouped
 *      by class
 *   5. **VIP**                      — `class = UC_VIP`
 *
 * Online/offline status uses a 15-minute window
 * (`last_access > now() - 900s`). Each row links to
 * `sendmessage.php?receiver=<id>` for the PM button, which is
 * already migrated.
 *
 * URL preserved exactly so:
 *   - `resources/views/legacy/formats.blade.php:222` (the
 *     "PM one of the Admins/SysOp" link in the static formats
 *     guide) keeps working,
 *   - the `tests/e2e/smoke/legacy-pages-extra.spec.ts` smoke probe
 *     for `/staff.php` keeps hitting the same URL.
 *
 * The matching nginx exact-location entry lives in
 * `.docker/openresty/sites/app.conf.template`.
 */
class StaffController extends Controller
{
    /** Online/offline cutoff window (seconds). */
    private const ONLINE_WINDOW_SECONDS = 900;

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }
        if (! user_can('staffmem')) {
            abort(403, 'Permission denied.');
        }

        $lang = $this->loadLangStaff();
        $cutoff = date('Y-m-d H:i:s', time() - self::ONLINE_WINDOW_SECONDS);

        $body = '<h1 align="center">'.htmlspecialchars((string) ($lang['head_staff'] ?? 'Staff')).'</h1>';
        $body .= $this->renderFirstLineSupport($cutoff, $lang);
        $body .= $this->renderMovieCritics($cutoff, $lang);
        $body .= $this->renderForumModerators($cutoff, $lang);
        $body .= $this->renderGeneralStaff($cutoff, $lang);
        $body .= $this->renderVip($cutoff, $lang);

        return $this->wrap((string) ($lang['head_staff'] ?? 'Staff'), $body);
    }

    private function renderFirstLineSupport(string $cutoff, array $lang): string
    {
        $rows = NexusDB::select(
            "SELECT id, username, country, last_access, supportlang, supportfor
             FROM users
             WHERE support = 'yes' AND status = 'confirmed'
             ORDER BY username"
        );

        $body = $this->renderSectionHeader(
            (string) ($lang['text_firstline_support'] ?? 'First-line support'),
            (string) ($lang['text_apply_for_it'] ?? 'Apply for it'),
        );
        $body .= '<p>'.((string) ($lang['text_firstline_support_note'] ?? '')).'</p>';
        $body .= '<table width="100%" cellspacing="0" align="center">';
        $body .= '<tr>'
            .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_username'] ?? 'Username')).'</b></td>'
            .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_country'] ?? 'Country')).'</b></td>'
            .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_online_or_offline'] ?? 'Online')).'</b></td>'
            .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_contact'] ?? 'Contact')).'</b></td>'
            .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_language'] ?? 'Language')).'</b></td>'
            .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_support_for'] ?? 'Support for')).'</b></td>'
            .'</tr>'
            .'<tr><td class="embedded" colspan="6"><hr color="#4040c0"></td></tr>';
        foreach ($rows as $row) {
            $row = (array) $row;
            $body .= $this->renderUserRow($row, $cutoff, $lang, [
                'extra' => [
                    htmlspecialchars((string) ($row['supportlang'] ?? '')),
                    htmlspecialchars((string) ($row['supportfor'] ?? '')),
                ],
            ]);
        }
        $body .= '</table>';

        return $body;
    }

    private function renderMovieCritics(string $cutoff, array $lang): string
    {
        $rows = NexusDB::select(
            "SELECT id, username, country, last_access, pickfor
             FROM users
             WHERE picker = 'yes' AND status = 'confirmed'
             ORDER BY username"
        );

        $body = $this->renderSectionHeader(
            (string) ($lang['text_movie_critics'] ?? 'Movie critics'),
            (string) ($lang['text_apply_for_it'] ?? 'Apply for it'),
        );
        $body .= '<p>'.((string) ($lang['text_movie_critics_note'] ?? '')).'</p>';
        $body .= '<table width="100%" cellspacing="0" align="center">';
        $body .= '<tr>'
            .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_username'] ?? 'Username')).'</b></td>'
            .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_country'] ?? 'Country')).'</b></td>'
            .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_online_or_offline'] ?? 'Online')).'</b></td>'
            .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_contact'] ?? 'Contact')).'</b></td>'
            .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_responsible_for'] ?? 'Responsible for')).'</b></td>'
            .'</tr>'
            .'<tr><td class="embedded" colspan="5"><hr color="#4040c0"></td></tr>';
        foreach ($rows as $row) {
            $row = (array) $row;
            $body .= $this->renderUserRow($row, $cutoff, $lang, [
                'extra' => [htmlspecialchars((string) ($row['pickfor'] ?? ''))],
            ]);
        }
        $body .= '</table>';

        return $body;
    }

    private function renderForumModerators(string $cutoff, array $lang): string
    {
        $rows = NexusDB::select(
            'SELECT forummods.userid AS userid, users.last_access, users.country
             FROM forummods
             LEFT JOIN users ON forummods.userid = users.id
             GROUP BY userid, users.last_access, users.country, forummods.forumid, forummods.userid
             ORDER BY forummods.forumid, forummods.userid'
        );

        $body = $this->renderSectionHeader(
            (string) ($lang['text_forum_moderators'] ?? 'Forum moderators'),
            (string) ($lang['text_apply_for_it'] ?? 'Apply for it'),
        );
        $body .= '<p>'.((string) ($lang['text_forum_moderators_note'] ?? '')).'</p>';
        $body .= '<table width="100%" cellspacing="0" align="center">';
        $body .= '<tr>'
            .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_username'] ?? 'Username')).'</b></td>'
            .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_country'] ?? 'Country')).'</b></td>'
            .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_online_or_offline'] ?? 'Online')).'</b></td>'
            .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_contact'] ?? 'Contact')).'</b></td>'
            .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_forums'] ?? 'Forums')).'</b></td>'
            .'</tr>'
            .'<tr><td class="embedded" colspan="5"><hr color="#4040c0"></td></tr>';
        foreach ($rows as $row) {
            $row = (array) $row;
            $userid = (int) ($row['userid'] ?? 0);
            $forumNames = $this->fetchForumNames($userid);
            $body .= $this->renderUserRow(
                ['id' => $userid, 'country' => (int) ($row['country'] ?? 0), 'last_access' => (string) ($row['last_access'] ?? '')],
                $cutoff,
                $lang,
                ['extra' => [$forumNames]],
            );
        }
        $body .= '</table>';

        return $body;
    }

    private function fetchForumNames(int $userid): string
    {
        $forums = NexusDB::select(
            'SELECT forums.id, forums.name
             FROM forums
             LEFT JOIN forummods ON forums.id = forummods.forumid
             WHERE forummods.userid = '.$userid
        );
        $links = [];
        foreach ($forums as $f) {
            $f = (array) $f;
            $links[] = sprintf(
                '<a href="forums.php?action=viewforum&forumid=%d">%s</a>',
                (int) ($f['id'] ?? 0),
                htmlspecialchars((string) ($f['name'] ?? '')),
            );
        }

        return implode(', ', $links);
    }

    private function renderGeneralStaff(string $cutoff, array $lang): string
    {
        $rows = NexusDB::select(
            'SELECT id, username, country, last_access, class, stafffor
             FROM users
             WHERE class > '.User::CLASS_VIP." AND status = 'confirmed'
             ORDER BY class DESC, username"
        );

        $body = $this->renderSectionHeader(
            (string) ($lang['text_general_staff'] ?? 'General staff'),
            (string) ($lang['text_apply_for_it'] ?? 'Apply for it'),
        );
        $body .= '<p>'.((string) ($lang['text_general_staff_note'] ?? '')).'</p>';
        $body .= '<table width="100%" cellspacing="0" align="center">';

        $currentClass = -1;
        foreach ($rows as $row) {
            $row = (array) $row;
            $rowClass = (int) ($row['class'] ?? 0);
            if ($currentClass !== $rowClass) {
                $currentClass = $rowClass;
                if ($body !== '') {
                    $body .= '<tr><td class="embedded" colspan="5" align="right">&nbsp;</td></tr>';
                }
                $className = (string) get_user_class_name($rowClass, false, true, true);
                $body .= '<tr><td class="embedded" colspan="5" align="right">'.$className.'</td></tr>'
                    .'<tr>'
                    .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_username'] ?? 'Username')).'</b></td>'
                    .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_country'] ?? 'Country')).'</b></td>'
                    .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_online_or_offline'] ?? 'Online')).'</b></td>'
                    .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_contact'] ?? 'Contact')).'</b></td>'
                    .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_duties'] ?? 'Duties')).'</b></td>'
                    .'</tr>'
                    .'<tr><td class="embedded" colspan="5"><hr color="#4040c0"></td></tr>';
            }
            $body .= $this->renderUserRow($row, $cutoff, $lang, [
                'extra' => [htmlspecialchars((string) ($row['stafffor'] ?? ''))],
            ]);
        }
        $body .= '</table>';

        return $body;
    }

    private function renderVip(string $cutoff, array $lang): string
    {
        $rows = NexusDB::select(
            'SELECT id, username, country, last_access, stafffor
             FROM users
             WHERE class = '.User::CLASS_VIP." AND status = 'confirmed'
             ORDER BY username"
        );

        $siteName = htmlspecialchars((string) Setting::getSiteName(), ENT_QUOTES);
        $vipNote = sprintf((string) ($lang['text_vip_note'] ?? ''), $siteName);

        $body = $this->renderSectionHeader((string) ($lang['text_vip'] ?? 'VIP'), null);
        $body .= '<p>'.$vipNote.'</p>';
        $body .= '<table width="100%" cellspacing="0" align="center">';
        $body .= '<tr>'
            .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_username'] ?? 'Username')).'</b></td>'
            .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_country'] ?? 'Country')).'</b></td>'
            .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_online_or_offline'] ?? 'Online')).'</b></td>'
            .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_contact'] ?? 'Contact')).'</b></td>'
            .'<td class="embedded"><b>'.htmlspecialchars((string) ($lang['text_reason'] ?? 'Reason')).'</b></td>'
            .'</tr>'
            .'<tr><td class="embedded" colspan="5"><hr color="#4040c0"></td></tr>';
        foreach ($rows as $row) {
            $row = (array) $row;
            $body .= $this->renderUserRow($row, $cutoff, $lang, [
                'extra' => [htmlspecialchars((string) ($row['stafffor'] ?? ''))],
            ]);
        }
        $body .= '</table>';

        return $body;
    }

    private function renderSectionHeader(string $title, ?string $applyLink): string
    {
        $applyHtml = $applyLink !== null
            ? '<font class="small"> - [<a class="altlink" href="contactstaff.php"><b>'.htmlspecialchars($applyLink).'</b></a>]</font>'
            : '';

        return '<h2>'.htmlspecialchars($title).$applyHtml.'</h2>';
    }

    /**
     * @param  array<string,mixed>  $row
     * @param  array{extra?: array<int,string>}  $opts
     */
    private function renderUserRow(array $row, string $cutoff, array $lang, array $opts = []): string
    {
        $userId = (int) ($row['id'] ?? 0);
        $countryRow = (array) get_country_row((int) ($row['country'] ?? 0));
        $isOnline = ($row['last_access'] ?? '') !== '' && (string) $row['last_access'] > $cutoff;
        $onlineImg = $isOnline
            ? '<img class="button_online" src="pic/trans.gif" alt="online" title="'.htmlspecialchars((string) ($lang['title_online'] ?? 'Online')).'" />'
            : '<img class="button_offline" src="pic/trans.gif" alt="offline" title="'.htmlspecialchars((string) ($lang['title_offline'] ?? 'Offline')).'" />';

        $flagPic = htmlspecialchars((string) ($countryRow['flagpic'] ?? ''), ENT_QUOTES);
        $flagName = htmlspecialchars((string) ($countryRow['name'] ?? ''), ENT_QUOTES);
        $sendPmTitle = htmlspecialchars((string) ($lang['title_send_pm'] ?? 'Send PM'), ENT_QUOTES);

        $cells = '<td class="embedded">'.get_username($userId).'</td>'
            .'<td class="embedded"><img width="24" height="15" src="pic/flag/'.$flagPic.'" title="'.$flagName.'" style="padding-bottom:1px;"></td>'
            .'<td class="embedded">'.$onlineImg.'</td>'
            .'<td class="embedded"><a href="sendmessage.php?receiver='.$userId.'" title="'.$sendPmTitle.'">'
            .'<img class="button_pm" src="pic/trans.gif" alt="pm" /></a></td>';

        foreach (($opts['extra'] ?? []) as $extraCell) {
            $cells .= '<td class="embedded">'.$extraCell.'</td>';
        }

        return '<tr>'.$cells.'</tr>';
    }

    /** @return array<string,string> */
    private function loadLangStaff(): array
    {
        $path = base_path(get_langfile_path('staff.php'));
        $lang_staff = [];
        if (is_file($path)) {
            require $path;
        }

        return is_array($lang_staff) ? $lang_staff : [];
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

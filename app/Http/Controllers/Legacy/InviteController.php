<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Invite;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/invite.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. Authed-only invite-system page.
 * GET-only — every POST happens on a separate URL:
 *   - `?type=new` form submits to `/takeinvite.php` (still legacy).
 *   - The invitee-checkbox form submits to `/takeconfirm.php`
 *     (already migrated → `TakeConfirmController`).
 *
 * Four render branches:
 *   - `?id=N&type=new`             → compose-an-invite form
 *   - `?id=N` (default `menu=invitee`) → paginated invitees of user N
 *     with status / enabled filters
 *   - `?id=N&menu=sent`            → paginated list of sent invites
 *   - `?id=N&menu=tmp`             → paginated list of temporary invites
 *
 * Permission gate: `$CURUSER['id'] == $id || user_can('viewinvite')`
 * (legacy contract preserved verbatim). A logged-in non-owner without
 * `viewinvite` is `abort(403)`'d.
 *
 * URL preserved exactly so:
 *   - `include/functions.php:2256` (the user-header invite link),
 *   - `public/usercp.php:1083` (the user-control-panel row),
 *   - `public/userdetails.php:134` (the user profile row),
 *   - `app/Http/Controllers/Legacy/TakeInviteController.php` (the
 *     post-send 302 to `/invite.php?id=...&sent=1`; was
 *     `public/takeinvite.php:142` pre-Phase-2),
 *   - `app/Http/Controllers/Legacy/TakeConfirmController.php` (3
 *     redirects + the embedded "go back" link)
 *   keep working without template / JS changes.
 *
 * Chrome-less envelope (same pattern as `ForummanageController` /
 * `MoforumsController` / `PollOverviewController`).
 */
class InviteController extends Controller
{
    private const PAGE_SIZE = 50;

    public function __construct(
        private readonly LegacyContext $context,
        private readonly UserRepository $userRep,
    ) {}

    public function __invoke(Request $request): Response
    {
        $viewer = $this->context->user();
        if ($viewer === null) {
            abort(401);
        }
        if (($viewer->parked ?? 'no') === 'yes') {
            abort(403, 'Your account is parked.');
        }

        $lang = $this->loadLang();

        $id = (int) $request->query('id', 0);
        $type = (string) $request->query('type', '');
        $menuSelected = (string) ($request->input('menu', 'invitee'));
        if (! in_array($menuSelected, ['invitee', 'sent', 'tmp'], true)) {
            $menuSelected = 'invitee';
        }

        // Legacy permission gate.
        $isOwner = ((int) $viewer->id === $id);
        if ((! $isOwner && ! user_can('viewinvite')) || ! $this->isValidId($id)) {
            abort(403, (string) ($lang['std_permission_denied'] ?? 'Permission denied.'));
        }

        $userRow = NexusDB::table('users')->where('id', $id)->first();
        if ($userRow === null) {
            abort(404, 'Invalid id.');
        }
        $user = (array) $userRow;

        $title = (string) ($lang['head_invites'] ?? 'Invites');
        $heading = '<h1 align="center">'
            .'<a href="/invite.php?id='.$id.'">'
            .htmlspecialchars((string) ($user['username'] ?? ''))
            .htmlspecialchars((string) ($lang['text_invite_system'] ?? "'s Invites"))
            .'</a></h1>';

        $sentBanner = '';
        if ((int) $request->query('sent', 0) === 1) {
            $sentBanner = '<p align="center"><font color="red">'
                .htmlspecialchars((string) ($lang['text_invite_code_sent'] ?? 'Invite code sent.'))
                .'</font></p>';
        }

        if ($type === 'new') {
            $body = $heading.$sentBanner.$this->renderNewForm($id, $user, $viewer, $lang);

            return $this->wrap($title, $body);
        }

        $body = $heading.$sentBanner.$this->renderMenu($menuSelected, $id, $isOwner, $lang);

        if ($menuSelected === 'invitee') {
            $body .= $this->renderInviteeList($request, $id, $isOwner, $viewer, $lang);
        } elseif ($menuSelected === 'sent' || $menuSelected === 'tmp') {
            $body .= $this->renderSentOrTmpList($request, $id, $menuSelected, $lang);
        }

        return $this->wrap($title, $body);
    }

    // ─── Menu / new-form / list renderers ────────────────────────────────

    /** @param  array<string,string>  $lang */
    private function renderMenu(string $selected, int $id, bool $isOwner, array $lang): string
    {
        $textInviteStatus = htmlspecialchars((string) ($lang['text_invite_status'] ?? 'Invite status'));
        $textSentStatus = htmlspecialchars((string) ($lang['text_sent_invites_status'] ?? 'Sent invites'));
        $textTmpStatus = htmlspecialchars((string) ($lang['text_tmp_status'] ?? 'Temporary invites'));

        $sendBtn = '';
        if ($isOwner) {
            try {
                $sendBtnText = (string) $this->userRep->getInviteBtnText((int) $id);
                $disabled = '';
            } catch (\Exception $e) {
                $sendBtnText = $e->getMessage();
                $disabled = ' disabled';
            }
            $sendBtn = '<form style="position:absolute;top:0;right:0" method="post" '
                .'action="/invite.php?id='.$id.'&type=new">'
                .'<input type="submit"'.$disabled.' value="'.htmlspecialchars($sendBtnText, ENT_QUOTES).'"></form>';
        }

        return '<div id="invitenav" style="position:relative">'
            .'<ul id="invitemenu" class="menu">'
            .'<li'.($selected === 'invitee' ? ' class="selected"' : '').'>'
            .'<a href="?id='.$id.'&menu=invitee">'.$textInviteStatus.'</a></li>'
            .'<li'.($selected === 'sent' ? ' class="selected"' : '').'>'
            .'<a href="?id='.$id.'&menu=sent">'.$textSentStatus.'</a></li>'
            .'<li'.($selected === 'tmp' ? ' class="selected"' : '').'>'
            .'<a href="?id='.$id.'&menu=tmp">'.$textTmpStatus.'</a></li>'
            .'</ul>'.$sendBtn.'</div>';
    }

    /**
     * @param  array<string,mixed>  $user
     * @param  array<string,string>  $lang
     */
    private function renderNewForm(int $id, array $user, User $viewer, array $lang): string
    {
        if ((int) $viewer->id !== $id) {
            abort(403, (string) ($lang['std_permission_denied'] ?? 'Permission denied.'));
        }

        try {
            $this->userRep->getInviteBtnText((int) $viewer->id);
        } catch (\Exception $e) {
            $textBack = htmlspecialchars((string) ($lang['here_to_go_back'] ?? 'here to go back'));

            return '<table border="0" cellspacing="0" cellpadding="10" width="100%" align="center">'
                .'<tr><td class="colhead" align="left">'.htmlspecialchars((string) ($lang['std_sorry'] ?? 'Sorry')).'</td></tr>'
                .'<tr><td class="text" align="left">'
                .htmlspecialchars($e->getMessage())
                .' <a class="altlink" href="/invite.php?id='.((int) $viewer->id).'">'.$textBack.'</a>'
                .'</td></tr></table>';
        }

        if (function_exists('registration_check')) {
            registration_check('invitesystem', true, false);
        }

        $temporaryInvites = Invite::query()
            ->where('inviter', (int) $viewer->id)
            ->where('invitee', '')
            ->where('expired_at', '>', now())
            ->orderBy('expired_at', 'asc')
            ->get();

        $invites = (int) ($user['invites'] ?? 0);
        $plural = $invites !== 1 ? (string) ($lang['text_s'] ?? 's') : '';
        $tmpCount = $temporaryInvites->count();

        $invitationBody = sprintf(
            (string) ($lang['text_invitation_body'] ?? 'You have been invited to %s by '),
            (string) Setting::getSiteName(),
        ).(string) $viewer->username;

        $inviteSelectOptions = '';
        if ($invites > 0) {
            $inviteSelectOptions = '<option value="permanent">'
                .htmlspecialchars((string) ($lang['text_permanent'] ?? 'Permanent'))
                .'</option>';
        }
        foreach ($temporaryInvites as $tmp) {
            $expiredAt = htmlspecialchars((string) $tmp->expired_at);
            $hash = htmlspecialchars((string) $tmp->hash);
            $textExpiredAt = htmlspecialchars((string) ($lang['text_expired_at'] ?? 'Expires'));
            $inviteSelectOptions .= sprintf(
                '<option value="%s">%s (%s: %s)</option>',
                $hash, $hash, $textExpiredAt, $expiredAt,
            );
        }

        $preUsernameTr = '';
        if (get_setting('system.is_invite_pre_email_and_username') === 'yes') {
            $label = htmlspecialchars((string) nexus_trans('invite.pre_register_username'));
            $help = htmlspecialchars((string) nexus_trans('invite.pre_register_username_help'));
            $preUsernameTr = '<tr><td class="rowhead nowrap" valign="top" align="right">'.$label.'</td>'
                .'<td align="left"><input type="text" size="40" name="pre_register_username">'
                .'<br /><font align="left" class="small">'.$help.'</font></td></tr>';
        }

        $siteName = htmlspecialchars((string) (Setting::getSiteName() ?: ($GLOBALS['SITENAME'] ?? '')));
        $emailRestrictTr = '';
        if (($GLOBALS['restrictemaildomain'] ?? '') === 'yes' && function_exists('allowedemails')) {
            $emailRestrictTr = '<br />'
                .htmlspecialchars((string) ($lang['text_email_restriction_note'] ?? 'Allowed e-mail domains: '))
                .allowedemails();
        }

        $textInviteSomeone = htmlspecialchars((string) ($lang['text_invite_someone'] ?? 'Invite someone to '));
        $textInvitation = htmlspecialchars((string) ($lang['text_invitation'] ?? ' invitation'));
        $textLeft = htmlspecialchars((string) ($lang['text_left'] ?? ' left'));
        $textTmpLeft = sprintf((string) ($lang['text_temporary_left'] ?? '%d temporary'), $tmpCount);
        $textEmailAddr = htmlspecialchars((string) ($lang['text_email_address'] ?? 'E-mail address'));
        $textEmailNote = htmlspecialchars((string) ($lang['text_email_address_note'] ?? ''));
        $textConsumeInvite = htmlspecialchars((string) ($lang['text_consume_invite'] ?? 'Consume invite'));
        $textMessage = htmlspecialchars((string) ($lang['text_message'] ?? 'Message'));
        $submitInvite = htmlspecialchars((string) ($lang['submit_invite'] ?? 'Send invitation'), ENT_QUOTES);

        return '<form method="post" action="/takeinvite.php?id='.$id.'">'
            .'<table border="1" width="100%" cellspacing="0" cellpadding="5">'
            .'<tr align="center"><td colspan="2"><b>'.$textInviteSomeone.$siteName
            .' ('.$invites.$textInvitation.$plural.$textLeft
            .' + '.htmlspecialchars($textTmpLeft).')</b></td></tr>'
            .'<tr><td class="rowhead nowrap" valign="top" align="right">'.$textEmailAddr.'</td>'
            .'<td align="left"><input type="text" size="40" name="email"><br />'
            .'<font align="left" class="small">'.$textEmailNote.'</font>'.$emailRestrictTr.'</td></tr>'
            .$preUsernameTr
            .'<tr><td class="rowhead nowrap" valign="top" align="right">'.$textConsumeInvite.'</td>'
            .'<td align="left"><select name="hash">'.$inviteSelectOptions.'</select></td></tr>'
            .'<tr><td class="rowhead nowrap" valign="top" align="right">'.$textMessage.'</td>'
            .'<td align="left"><textarea name="body" rows="10" style="width:100%">'
            .htmlspecialchars($invitationBody).'</textarea></td></tr>'
            .'<tr><td align="center" colspan="2">'
            .'<input type="submit" value="'.$submitInvite.'"></td></tr>'
            .'</table></form>';
    }

    /**
     * @param  array<string,string>  $lang
     */
    private function renderInviteeList(Request $request, int $id, bool $isOwner, User $viewer, array $lang): string
    {
        $statusFilter = (string) ($request->query('status') ?? '');
        $enabledFilter = (string) ($request->query('enabled') ?? '');

        $buildQuery = function () use ($id, $statusFilter, $enabledFilter) {
            $q = NexusDB::table('users as u')->where('u.invited_by', $id);
            if ($statusFilter !== '') {
                $q->where('u.status', $statusFilter);
            }
            if ($enabledFilter !== '') {
                $q->where('u.enabled', $enabledFilter);
            }

            return $q;
        };

        $count = (int) $buildQuery()->count();
        $haremFactor = (float) get_setting('bonus.harem_addition');

        // Filter form.
        $textSelectOne = htmlspecialchars((string) nexus_trans('nexus.select_one_please'));
        $textEnabled = htmlspecialchars((string) ($lang['text_enabled'] ?? 'Enabled'));
        $textStatus = htmlspecialchars((string) ($lang['text_status'] ?? 'Status'));
        $textSubmit = htmlspecialchars((string) nexus_trans('label.submit'), ENT_QUOTES);
        $textReset = htmlspecialchars((string) nexus_trans('label.reset'), ENT_QUOTES);

        $enabledOptions = '';
        foreach (['yes', 'no'] as $item) {
            $sel = $enabledFilter === $item ? ' selected' : '';
            $enabledOptions .= '<option value="'.$item.'"'.$sel.'>'.strtoupper($item).'</option>';
        }
        $statusOptions = '';
        foreach ([
            'pending' => (string) ($lang['text_pending'] ?? 'Pending'),
            'confirmed' => (string) ($lang['text_confirmed'] ?? 'Confirmed'),
        ] as $name => $text) {
            $sel = $statusFilter === $name ? ' selected' : '';
            $statusOptions .= '<option value="'.$name.'"'.$sel.'>'.htmlspecialchars($text).'</option>';
        }

        $requestUri = htmlspecialchars((string) ($_SERVER['REQUEST_URI'] ?? '/invite.php'), ENT_QUOTES);
        $filterForm = <<<FORM
<div>
    <form id="filterForm" action="{$requestUri}" method="get">
        <input type="hidden" name="menu" value="invitee" />
        <input type="hidden" name="id" value="{$id}" />
        <span>{$textEnabled}:</span>
        <select name="enabled">
            <option value="">-{$textSelectOne}-</option>
            {$enabledOptions}
        </select>
        &nbsp;&nbsp;
        <span>{$textStatus}:</span>
        <select name="status">
            <option value="">-{$textSelectOne}-</option>
            {$statusOptions}
        </select>
        &nbsp;&nbsp;
        <input type="submit" value="{$textSubmit}">
        <input type="button" id="reset" value="{$textReset}">
    </form>
</div>
<script>
jQuery("#reset").on('click', function () {
    jQuery("select[name=status]").val('');
    jQuery("select[name=enabled]").val('');
});
</script>
FORM;

        $body = $filterForm
            .'<table border="1" width="100%" cellspacing="0" cellpadding="5">'
            .'<form method="post" action="/takeconfirm.php?id='.$id.'">';

        $pagerHtml = '';

        if ($count === 0) {
            $body .= '<tr><td colspan="13" align="center">'
                .htmlspecialchars((string) ($lang['text_no_invites'] ?? 'No invites yet.'))
                .'</td></tr>';
        } else {
            // Manual pagination — pager() side-effects depend on legacy globals.
            $page = max(0, (int) $request->query('page', 0));
            $totalPages = (int) ceil($count / self::PAGE_SIZE);
            $page = min($page, max(0, $totalPages - 1));
            $offset = $page * self::PAGE_SIZE;

            $rows = $buildQuery()
                ->leftJoin('torrents as t', 't.owner', '=', 'u.id')
                ->groupBy('u.id')
                ->offset($offset)
                ->limit(self::PAGE_SIZE)
                ->select(
                    'u.id', 'u.username', 'u.email', 'u.uploaded', 'u.downloaded',
                    'u.status', 'u.warned', 'u.enabled', 'u.donor',
                    'u.seed_points_per_hour', 'u.seeding_torrent_count',
                    'u.seeding_torrent_size', 'u.last_announce_at',
                    NexusDB::raw('COUNT(t.id) AS torrent_count'),
                )
                ->get();

            $body .= $this->renderInviteeTableHeader($haremFactor, $isOwner || (int) $viewer->class >= User::CLASS_SYSOP, $lang);
            foreach ($rows as $rowObj) {
                $body .= $this->renderInviteeTableRow(
                    (array) $rowObj,
                    $haremFactor,
                    $isOwner || (int) $viewer->class >= User::CLASS_SYSOP,
                    $lang,
                );
            }

            // Pager.
            $base = '?id='.$id.'&menu=invitee&';
            if ($totalPages > 1) {
                $links = '';
                if ($page > 0) {
                    $links .= '<a href="'.$base.'page='.($page - 1).'">&lt;&lt; Prev</a> ';
                }
                $links .= '<b>'.($page + 1).' / '.$totalPages.'</b>';
                if ($page + 1 < $totalPages) {
                    $links .= ' <a href="'.$base.'page='.($page + 1).'">Next &gt;&gt;</a>';
                }
                $pagerHtml = '<p align="center">'.$links.'</p>';
            }

            // Submit row for confirm-pending.
            if ($isOwner || (int) $viewer->class >= User::CLASS_SYSOP) {
                $pendingCount = (int) NexusDB::table('users')
                    ->where('status', 'pending')
                    ->where('invited_by', (int) $viewer->id)
                    ->count();
                if ($pendingCount > 0) {
                    $colSpan = $haremFactor > 0 ? 13 : 12;
                    $textConfirmUsers = htmlspecialchars(
                        (string) ($lang['submit_confirm_users'] ?? 'Confirm users'),
                        ENT_QUOTES,
                    );
                    $body .= '<tr><td colspan="'.$colSpan.'" align="right">'
                        .'<input type="submit" style="height:20px" value="'.$textConfirmUsers.'">'
                        .'</td></tr>';
                }
            }
        }

        $body .= '</form></table>'.$pagerHtml;

        return $body;
    }

    /**
     * @param  array<string,string>  $lang
     */
    private function renderInviteeTableHeader(float $haremFactor, bool $showActionCol, array $lang): string
    {
        $cols = [
            'username' => $lang['text_username'] ?? 'Username',
            'email' => $lang['text_email'] ?? 'E-mail',
            'enabled' => $lang['text_enabled'] ?? 'Enabled',
            'uploaded_count' => $lang['text_uploaded_count'] ?? 'Uploads',
            'uploaded' => $lang['text_uploaded'] ?? 'Uploaded',
            'downloaded' => $lang['text_downloaded'] ?? 'Downloaded',
            'ratio' => $lang['text_ratio'] ?? 'Ratio',
            'seed_count' => $lang['text_seed_torrent_count'] ?? 'Seeding count',
            'seed_size' => $lang['text_seed_torrent_size'] ?? 'Seeding size',
            'seed_bonus' => $lang['text_seed_torrent_bonus_per_hour'] ?? 'Seed bonus/hour',
        ];
        $html = '<tr>';
        foreach ($cols as $cell) {
            $html .= '<td class="colhead"><b>'.htmlspecialchars((string) $cell).'</b></td>';
        }
        if ($haremFactor > 0) {
            $html .= '<td class="colhead">'
                .htmlspecialchars((string) ($lang['harem_addition'] ?? 'Harem addition'))
                .'</td>';
        }
        $html .= '<td class="colhead"><b>'
            .htmlspecialchars((string) ($lang['text_seed_torrent_last_announce_at'] ?? 'Last announce'))
            .'</b></td>';
        $html .= '<td class="colhead"><b>'
            .htmlspecialchars((string) ($lang['text_status'] ?? 'Status'))
            .'</b></td>';
        if ($showActionCol) {
            $html .= '<td class="colhead"><b>'
                .htmlspecialchars((string) ($lang['text_confirm'] ?? 'Confirm'))
                .'</b></td>';
        }
        $html .= '</tr>';

        return $html;
    }

    /**
     * @param  array<string,mixed>  $arr
     * @param  array<string,string>  $lang
     */
    private function renderInviteeTableRow(array $arr, float $haremFactor, bool $showActionCol, array $lang): string
    {
        $up = (float) ($arr['uploaded'] ?? 0);
        $dn = (float) ($arr['downloaded'] ?? 0);
        if ($dn > 0) {
            $ratioVal = number_format($up / $dn, 3);
            $ratio = '<font color="'.get_ratio_color($ratioVal).'">'.$ratioVal.'</font>';
        } elseif ($up > 0) {
            $ratio = 'Inf.';
        } else {
            $ratio = '---';
        }
        $userId = (int) ($arr['id'] ?? 0);
        $status = (string) ($arr['status'] ?? '');
        if ($status === 'confirmed') {
            $statusHtml = '<a href="userdetails.php?id='.$userId.'"><font color="#1f7309">'
                .htmlspecialchars((string) ($lang['text_confirmed'] ?? 'Confirmed'))
                .'</font></a>';
        } else {
            $statusHtml = '<a href="checkuser.php?id='.$userId.'"><font color="#ca0226">'
                .htmlspecialchars((string) ($lang['text_pending'] ?? 'Pending'))
                .'</font></a>';
        }

        $html = '<tr class="rowfollow">'
            .'<td class="rowfollow">'.get_username($userId).'</td>'
            .'<td class="rowfollow">'.htmlspecialchars((string) ($arr['email'] ?? '')).'</td>'
            .'<td class="rowfollow">'.htmlspecialchars((string) ($arr['enabled'] ?? '')).'</td>'
            .'<td class="rowfollow">'.((int) ($arr['torrent_count'] ?? 0)).'</td>'
            .'<td class="rowfollow">'.mksize($up).'</td>'
            .'<td class="rowfollow">'.mksize($dn).'</td>'
            .'<td class="rowfollow">'.$ratio.'</td>'
            .'<td class="rowfollow">'.number_format((int) ($arr['seeding_torrent_count'] ?? 0)).'</td>'
            .'<td class="rowfollow">'.mksize((float) ($arr['seeding_torrent_size'] ?? 0)).'</td>'
            .'<td class="rowfollow">'.number_format((float) ($arr['seed_points_per_hour'] ?? 0), 3).'</td>';
        if ($haremFactor > 0) {
            $html .= '<td class="rowfollow">'
                .number_format((float) ($arr['seed_points_per_hour'] ?? 0) * $haremFactor, 3)
                .'</td>';
        }
        $html .= '<td class="rowfollow">'.htmlspecialchars((string) ($arr['last_announce_at'] ?? '')).'</td>';
        $html .= '<td class="rowfollow">'.$statusHtml.'</td>';
        if ($showActionCol) {
            $html .= '<td class="rowfollow">';
            if ($status === 'pending') {
                $html .= '<input type="checkbox" name="conusr[]" value="'.$userId.'" />';
            }
            $html .= '</td>';
        }
        $html .= '</tr>';

        return $html;
    }

    /**
     * @param  array<string,string>  $lang
     */
    private function renderSentOrTmpList(Request $request, int $id, string $menuSelected, array $lang): string
    {
        $buildQuery = function () use ($id, $menuSelected) {
            $q = NexusDB::table('invites')->where('inviter', $id);
            if ($menuSelected === 'sent') {
                $q->where('invitee', '!=', '');
            } else {
                $q->where('invitee', '')->whereNotNull('expired_at');
            }

            return $q;
        };

        $count = (int) $buildQuery()->count();
        $body = '<table border="1" width="100%" cellspacing="0" cellpadding="5">';
        $pagerHtml = '';

        if ($count === 0) {
            $textNone = htmlspecialchars((string) ($GLOBALS['lang_functions']['text_none'] ?? 'None'));
            $body .= '<tr align="center"><td colspan="6">'.$textNone.'</td></tr>';
        } else {
            $page = max(0, (int) $request->query('page', 0));
            $totalPages = (int) ceil($count / self::PAGE_SIZE);
            $page = min($page, max(0, $totalPages - 1));
            $offset = $page * self::PAGE_SIZE;

            $rows = $buildQuery()->offset($offset)->limit(self::PAGE_SIZE)->get();

            $colEmail = htmlspecialchars((string) ($lang['text_email'] ?? 'E-mail'));
            $colHash = htmlspecialchars((string) ($lang['text_hash'] ?? 'Hash'));
            $colSendDate = htmlspecialchars((string) ($lang['text_send_date'] ?? 'Sent at'));
            $colHashStatus = htmlspecialchars((string) ($lang['text_hash_status'] ?? 'Status'));
            $colInvitee = htmlspecialchars((string) ($lang['text_invitee_user'] ?? 'Invitee'));
            $colExpiredAt = htmlspecialchars((string) ($lang['text_expired_at'] ?? 'Expires'));
            $colCreatedAt = htmlspecialchars((string) nexus_trans('label.created_at'));

            $body .= '<tr><td class="colhead">'.$colEmail.'</td>'
                .'<td class="colhead">'.$colHash.'</td>'
                .'<td class="colhead">'.$colSendDate.'</td>';
            if ($menuSelected === 'sent') {
                $body .= '<td class="colhead">'.$colHashStatus.'</td>';
            }
            $body .= '<td class="colhead">'.$colInvitee.'</td>';
            if ($menuSelected === 'tmp') {
                $body .= '<td class="colhead">'.$colExpiredAt.'</td>';
                $body .= '<td class="colhead">'.$colCreatedAt.'</td>';
            }
            $body .= '</tr>';

            $signupHelp = htmlspecialchars((string) ($lang['signup_link_help'] ?? 'Signup link'), ENT_QUOTES);
            $signupLabel = htmlspecialchars((string) ($lang['signup_link'] ?? 'Sign up'));

            foreach ($rows as $rowObj) {
                $arr = (array) $rowObj;
                $isHashValid = ((int) ($arr['valid'] ?? 0)) === Invite::VALID_YES;
                $hash = htmlspecialchars((string) ($arr['hash'] ?? ''));
                $registerLink = '';
                if ($isHashValid) {
                    $registerLink = sprintf(
                        '&nbsp;<a href="signup.php?type=invite&invitenumber=%s" title="%s" target="_blank"><small>[%s]</small></a>',
                        $hash, $signupHelp, $signupLabel,
                    );
                }
                $tr = '<tr>';
                $tr .= '<td class="rowfollow">'.htmlspecialchars((string) ($arr['invitee'] ?? '')).'</td>';
                $tr .= '<td class="rowfollow">'.$hash.$registerLink.'</td>';
                $tr .= '<td class="rowfollow">'.htmlspecialchars((string) ($arr['time_invited'] ?? '')).'</td>';
                if ($menuSelected === 'sent') {
                    $tr .= '<td class="rowfollow">'
                        .htmlspecialchars((string) (Invite::$validInfo[$arr['valid'] ?? 0]['text'] ?? ''))
                        .'</td>';
                }
                if (! $isHashValid) {
                    $registerUid = (int) ($arr['invitee_register_uid'] ?? 0);
                    $registerName = htmlspecialchars((string) ($arr['invitee_register_username'] ?? ''));
                    $tr .= '<td class="rowfollow">'
                        .'<a href="userdetails.php?id='.$registerUid.'"><font color="#1f7309">'
                        .$registerName.'</font></a></td>';
                } else {
                    $tr .= '<td class="rowfollow"></td>';
                }
                if ($menuSelected === 'tmp') {
                    $tr .= '<td class="rowfollow">'.htmlspecialchars((string) ($arr['expired_at'] ?? '')).'</td>';
                    $tr .= '<td class="rowfollow">'.htmlspecialchars((string) ($arr['created_at'] ?? '')).'</td>';
                }
                $tr .= '</tr>';
                $body .= $tr;
            }

            if ($totalPages > 1) {
                $base = '?id='.$id.'&menu='.$menuSelected.'&';
                $links = '';
                if ($page > 0) {
                    $links .= '<a href="'.$base.'page='.($page - 1).'">&lt;&lt; Prev</a> ';
                }
                $links .= '<b>'.($page + 1).' / '.$totalPages.'</b>';
                if ($page + 1 < $totalPages) {
                    $links .= ' <a href="'.$base.'page='.($page + 1).'">Next &gt;&gt;</a>';
                }
                $pagerHtml = '<p align="center">'.$links.'</p>';
            }
        }
        $body .= '</table>'.$pagerHtml;

        return $body;
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function isValidId(int $id): bool
    {
        if (function_exists('is_valid_id')) {
            return (bool) is_valid_id($id);
        }

        return $id > 0;
    }

    private function wrap(string $title, string $body): Response
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5);
        $html = <<<HTML
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head>
<body>
<table width="100%" class="main" border="0" cellspacing="0" cellpadding="0">
<tr><td class="embedded">
{$body}
</td></tr></table>
</body></html>
HTML;

        return new Response($html);
    }

    /** @return array<string,string> */
    private function loadLang(): array
    {
        $path = base_path(get_langfile_path('invite.php'));
        $lang_invite = [];
        if (is_file($path)) {
            require $path;
        }

        return is_array($lang_invite) ? $lang_invite : [];
    }
}

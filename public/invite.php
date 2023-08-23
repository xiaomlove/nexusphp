<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
parked();
$id = intval($_GET["id"] ?? 0);
$type = unesc($_GET["type"] ?? '');
$menuSelected = $_REQUEST['menu'] ?? 'invitee';
$pageSize = 50;
if (($CURUSER['id'] != $id && !user_can('viewinvite')) || !is_valid_id($id))
    stderr($lang_invite['std_sorry'],$lang_invite['std_permission_denied'], true, false);
$userRep = new \App\Repositories\UserRepository();
function inviteMenu ($selected = "invitee") {
    global $lang_invite, $id, $CURUSER, $invitesystem, $userRep;
    begin_main_frame("", false, "100%");
    print ("<div id=\"invitenav\" style='position: relative'><ul id=\"invitemenu\" class=\"menu\">");
    print ("<li" . ($selected == "invitee" ? " class=selected" : "") . "><a href=\"?id=".$id."&menu=invitee\">".$lang_invite['text_invite_status']."</a></li>");
    print ("<li" . ($selected == "sent" ? " class=selected" : "") . "><a href=\"?id=".$id."&menu=sent\">".$lang_invite['text_sent_invites_status']."</a></li>");
    print ("<li" . ($selected == "tmp" ? " class=selected" : "") . "><a href=\"?id=".$id."&menu=tmp\">".$lang_invite['text_tmp_status']."</a></li>");
    try {
        $sendBtnText = $userRep->getInviteBtnText($CURUSER['id']);
        $disabled = '';
    } catch (\Exception $exception) {
        $sendBtnText = $exception->getMessage();
        $disabled = ' disabled';
    }
    if ($CURUSER['id'] == $id) {
        print ("</ul><form style='position: absolute;top:0;right:0' method=post action=invite.php?id=".htmlspecialchars($id)."&type=new><input type=submit ".$disabled." value='".$sendBtnText."'></form></div>");
    }
    end_main_frame();
}

$res = sql_query("SELECT * FROM users WHERE id = ".mysql_real_escape_string($id)) or sqlerr();
$user =  mysql_fetch_assoc($res);
if (!$user) {
    stderr($lang_invite['std_sorry'], 'Invalid id');
}
stdhead($lang_invite['head_invites']);
print("<table width=100% class=main border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>");

print("<h1 align=center><a href=\"invite.php?id=".$id."\">".$user['username'].$lang_invite['text_invite_system']."</a></h1>");
	$sent = htmlspecialchars($_GET['sent'] ?? '');
	if ($sent == 1){
		$msg = $lang_invite['text_invite_code_sent'];
		print("<p align=center><font color=red>".$msg."</font></p>");
	}

$inv = $user;

//for one or more. "invite"/"invites"
if ($inv["invites"] != 1){
	$_s = $lang_invite['text_s'];
} else {
	$_s = "";
}

if ($type == 'new'){
    if ($CURUSER['id'] != $id) {
        stderr($lang_invite['std_sorry'],$lang_invite['std_permission_denied'], true, false);
    }
    try {
        $sendBtnText = $userRep->getInviteBtnText($CURUSER['id']);
    } catch (\Exception $exception) {
        stdmsg($lang_invite['std_sorry'],$exception->getMessage().
            "  <a class=altlink href=invite.php?id={$CURUSER['id']}>".$lang_invite['here_to_go_back'],false);
        print("</td></tr></table>");
        stdfoot();
        die;
    }
    registration_check('invitesystem',true,false);
    $temporaryInvites = \App\Models\Invite::query()->where('inviter', $CURUSER['id'])
        ->where('invitee', '')
        ->where('expired_at', '>', now())
        ->orderBy('expired_at', 'asc')
        ->get()
    ;
	$invitation_body =  $lang_invite['text_invitation_body'].$CURUSER['username'];
	//$invitation_body_insite = str_replace("<br />","\n",$invitation_body);
    $inviteSelectOptions = '';
    if ($inv['invites'] > 0) {
        $inviteSelectOptions = '<option value="permanent">'.$lang_invite['text_permanent'].'</option>';
    }
    foreach ($temporaryInvites as $tmp) {
        $inviteSelectOptions .= sprintf('<option value="%s">%s (%s: %s)</option>', $tmp->hash, $tmp->hash, $lang_invite['text_expired_at'], $tmp->expired_at);
    }
    $preUsernameTr = "";
    if (get_setting("system.is_invite_pre_email_and_username") == "yes") {
        $preUsernameTr = "<tr><td class=\"rowhead nowrap\" valign=\"top\" align=\"right\">".nexus_trans("invite.pre_register_username")."</td><td align=left><input type=text size=40 name=pre_register_username><br /><font align=left class=small>".nexus_trans("invite.pre_register_username_help")."</font></td></tr>";
    }
	print("<form method=post action=takeinvite.php?id=".htmlspecialchars($id).">".
	"<table border=1 width=100% cellspacing=0 cellpadding=5>".
	"<tr align=center><td colspan=2><b>".$lang_invite['text_invite_someone']."$SITENAME ({$inv['invites']}".$lang_invite['text_invitation'].$_s.$lang_invite['text_left'] .' + '.sprintf($lang_invite['text_temporary_left'], $temporaryInvites->count()).")</b></td></tr>".
	"<tr><td class=\"rowhead nowrap\" valign=\"top\" align=\"right\">".$lang_invite['text_email_address']."</td><td align=left><input type=text size=40 name=email><br /><font align=left class=small>".$lang_invite['text_email_address_note']."</font>".($restrictemaildomain == 'yes' ? "<br />".$lang_invite['text_email_restriction_note'].allowedemails() : "")."</td></tr>".$preUsernameTr.
	"<tr><td class=\"rowhead nowrap\" valign=\"top\" align=\"right\">".$lang_invite['text_consume_invite']."</td><td align=left><select name='hash'>".$inviteSelectOptions."</select></td></tr>".
	"<tr><td class=\"rowhead nowrap\" valign=\"top\" align=\"right\">".$lang_invite['text_message']."</td><td align=left><textarea name=body rows=10 style='width: 100%'>" .$invitation_body. "</textarea></td></tr>".
	"<tr><td align=center colspan=2><input type=submit value='".$lang_invite['submit_invite']."'></td></tr>".
	"</form></table></td></tr></table>");

} else {
    inviteMenu($menuSelected);
    if ($menuSelected == 'invitee') {
        $whereStr = "invited_by = " . sqlesc($id);
        if (!empty($_GET['status'])) {
            $whereStr .= " and status = " . sqlesc($_GET['status']);
        }
        if (!empty($_GET['enabled'])) {
            $whereStr .= " and enabled = " . sqlesc($_GET['enabled']);
        }
        $rel = sql_query("SELECT COUNT(*) FROM users WHERE $whereStr") or sqlerr(__FILE__, __LINE__);
        $arro = mysql_fetch_row($rel);
        $number = $arro[0];
        $textSelectOnePlease = nexus_trans('nexus.select_one_please');
        $enabledOptions = $statusOptions = '';
        foreach (['yes', 'no'] as $item) {
            $enabledOptions .= sprintf(
                '<option value="%s"%s>%s</option>',
                $item, $_GET['enabled'] == $item ? ' selected' : '', strtoupper($item)
            );
        }
        foreach (['pending' => $lang_invite['text_pending'], 'confirmed' => $lang_invite['text_confirmed']] as $name => $text) {
            $statusOptions .= sprintf(
                '<option value="%s"%s>%s</option>',
                $name, $_GET['status'] == $name ? ' selected' : '', $text
            );
        }

        $resetText = nexus_trans('label.reset');
        $submitText = nexus_trans('label.submit');
        $filterForm = <<<FORM
<div>
    <form id="filterForm" action="{$_SERVER['REQUEST_URI']}" method="get">
        <input type="hidden" name="menu" value="{$menuSelected}" />
        <input type="hidden" name="id" value="{$id}" />
        <span>{$lang_invite['text_enabled']}:</span>
        <select name="enabled">
            <option value="">-{$textSelectOnePlease}-</option>
            {$enabledOptions}
        </select>
        &nbsp;&nbsp;
        <span>{$lang_invite['text_status']}:</span>
        <select name="status">
            <option value="">-{$textSelectOnePlease}-</option>
            {$statusOptions}
        </select>
        &nbsp;&nbsp;
        <input type="submit" value="{$submitText}">
        <input type="button" id="reset" value="{$resetText}">
    </form>
</div>
FORM;
        $resetJs = <<<JS
jQuery("#reset").on('click', function () {
    jQuery("select[name=status]").val('')
    jQuery("select[name=enabled]").val('')
})
JS;
        \Nexus\Nexus::js($resetJs, 'footer', false);
        print($filterForm."<table border=1 width=100% cellspacing=0 cellpadding=5>".
            "<form method=post action=takeconfirm.php?id=".htmlspecialchars($id).">");

        if(!$number){
            print("<tr><td colspan=7 align=center>".$lang_invite['text_no_invites']."</tr>");
        } else {
            list($pagertop, $pagerbottom, $limit) = pager($pageSize, $number, "?id=$id&menu=$menuSelected&");
            $haremAdditionFactor = get_setting('bonus.harem_addition');
            $ret = sql_query("SELECT id, username, email, uploaded, downloaded, status, warned, enabled, donor, email FROM users WHERE $whereStr $limit") or sqlerr();
            $num = mysql_num_rows($ret);

            print("<tr>
<td class=colhead><b>".$lang_invite['text_username']."</b></td>
<td class=colhead><b>".$lang_invite['text_email']."</b></td>
<td class=colhead><b>".$lang_invite['text_enabled']."</b></td>
<td class=colhead><b>".$lang_invite['text_uploaded']."</b></td>
<td class=colhead><b>".$lang_invite['text_downloaded']."</b></td>
<td class=colhead><b>".$lang_invite['text_ratio']."</b></td>
<td class=colhead><b>".$lang_invite['text_seed_torrent_count']."</b></td>
<td class=colhead><b>".$lang_invite['text_seed_torrent_size']."</b></td>
<td class=colhead title={$lang_invite['text_seed_torrent_bonus_per_hour_help']}><b>".$lang_invite['text_seed_torrent_bonus_per_hour']."</b></td>
"
            );
            if ($haremAdditionFactor > 0) {
                print('<td class="colhead">'.$lang_invite['harem_addition'].'</td>');
            }
            print("<td class=colhead><b>".$lang_invite['text_seed_torrent_last_announce_at']."</b></td>");
            print("<td class=colhead><b>".$lang_invite['text_status']."</b></td>");
            if ($CURUSER['id'] == $id || get_user_class() >= UC_SYSOP) {
                print("<td class=colhead><b>".$lang_invite['text_confirm']."</b></td>");
            }

            print("</tr>");
            for ($i = 0; $i < $num; ++$i)
            {
                $arr = mysql_fetch_assoc($ret);

                if ($arr["downloaded"] > 0) {
                    $ratio = number_format($arr["uploaded"] / $arr["downloaded"], 3);
                    $ratio = "<font color=" . get_ratio_color($ratio) . ">$ratio</font>";
                } else {
                    if ($arr["uploaded"] > 0) {
                        $ratio = "Inf.";
                    }
                    else {
                        $ratio = "---";
                    }
                }
                if ($arr["status"] == 'confirmed')
                    $status = "<a href=userdetails.php?id={$arr['id']}><font color=#1f7309>".$lang_invite['text_confirmed']."</font></a>";
                else
                    $status = "<a href=checkuser.php?id={$arr['id']}><font color=#ca0226>".$lang_invite['text_pending']."</font></a>";
                $seedBonusResult = calculate_seed_bonus($arr['id']);
                print("<tr class=rowfollow>
<td class=rowfollow>".get_username($arr['id'])."</td>
<td>{$arr['email']}</td>
<td class=rowfollow>".$arr['enabled']."</td>
<td class=rowfollow>" . mksize($arr['uploaded']) . "</td>
<td class=rowfollow>" . mksize($arr['downloaded']) . "</td>
<td class=rowfollow>$ratio</td>
<td class=rowfollow>{$seedBonusResult['count']}</td>
<td class=rowfollow>".mksize($seedBonusResult['size'])."</td>
<td class=rowfollow>".number_format($seedBonusResult['seed_points'], 3)."</td>
");

                if ($haremAdditionFactor > 0) {
                    print ("<td class=rowfollow>".number_format($seedBonusResult['seed_points'] * $haremAdditionFactor, 3)."</td>");
                }
                print("<td class=rowfollow>{$seedBonusResult['last_action']}</td>");
                print("<td class=rowfollow>$status</td>");
                if ($CURUSER['id'] == $id || get_user_class() >= UC_SYSOP){
                    print("<td>");
                    if ($arr['status'] == 'pending')
                        print("<input type=\"checkbox\" name=\"conusr[]\" value=\"" . $arr['id'] . "\" />");
                    print("</td>");
                }

                print("</tr>");
            }
        }

        if ($CURUSER['id'] == $id || get_user_class() >= UC_SYSOP)
        {
            $pendingcount = number_format(get_row_count("users", "WHERE  status='pending' AND invited_by={$CURUSER['id']}"));
            $colSpan = 7;
            if (isset($haremAdditionFactor) && $haremAdditionFactor > 0) {
                $colSpan += 1;
            }
            if ($pendingcount){
                print("<input type=hidden name=email value={$arr['email']}>");
                print("<tr><td colspan=$colSpan align=right><input type=submit style='height: 20px' value=".$lang_invite['submit_confirm_users']."></td></tr>");
            }
            print("</form>");
        }
        print("</table>");
        print("</td></tr></table>$pagertop");
    } elseif (in_array($menuSelected, ['sent', 'tmp'])) {
        $whereStr = "inviter = " . sqlesc($id);
        if ($menuSelected == 'sent') {
            $whereStr .= " and invitee != ''";
        } elseif ($menuSelected == 'tmp') {
            $whereStr .= " and invitee = '' and expired_at is not null";
        }
        $rul = sql_query("SELECT COUNT(*) FROM invites WHERE $whereStr");
        $arre = mysql_fetch_row($rul);
        $number1 = $arre[0];
        print("<table border=1 width=100% cellspacing=0 cellpadding=5>");

        if(!$number1){
            print("<tr align=center><td colspan=6>".$lang_functions['text_none']."</tr>");
        } else {
            list($pagertop, $pagerbottom, $limit) = pager($pageSize, $number1, "?id=$id&menu=$menuSelected&");

            $rer = sql_query("SELECT * FROM invites WHERE $whereStr $limit") or sqlerr();
            $num1 = mysql_num_rows($rer);

            print("<tr><td class=colhead>".$lang_invite['text_email']."</td><td class=colhead>".$lang_invite['text_hash']."</td><td class=colhead>".$lang_invite['text_send_date']."</td>");
            if ($menuSelected == 'sent') {
                print("<td class='colhead'>".$lang_invite['text_hash_status']."</td>");
            }
            print "<td class='colhead'>".$lang_invite['text_invitee_user']."</td>";
            if ($menuSelected == 'tmp') {
                print("<td class='colhead'>".$lang_invite['text_expired_at']."</td>");
                print("<td class='colhead'>".nexus_trans('label.created_at')."</td>");
            }
            print("</tr>");
            for ($i = 0; $i < $num1; ++$i)
            {
                $arr1 = mysql_fetch_assoc($rer);
                $isHashValid = $arr1['valid'] == \App\Models\Invite::VALID_YES;
                $registerLink = '';
                if ($isHashValid) {
                    $registerLink = sprintf('&nbsp;<a href="signup.php?type=invite&invitenumber=%s" title="%s" target="_blank"><small>[%s]</small></a>', $arr1['hash'], $lang_invite['signup_link_help'], $lang_invite['signup_link']);
                }
                $tr = "<tr>";
                $tr .= "<td class=rowfollow>{$arr1['invitee']}</td>";
                $tr .= sprintf('<td class="rowfollow">%s%s</td>', $arr1['hash'], $registerLink);
                $tr .= "<td class=rowfollow>{$arr1['time_invited']}</td>";
                if ($menuSelected == 'sent') {
                    $tr .= "<td class=rowfollow>".\App\Models\Invite::$validInfo[$arr1['valid']]['text']."</td>";
                }
                if (!$isHashValid) {
                    $tr .= "<td class=rowfollow><a href=userdetails.php?id={$arr1['invitee_register_uid']}><font color=#1f7309>".$arr1['invitee_register_username']."</font></a></td>";
                } else {
                    $tr .= "<td class='rowfollow'></td>";
                }
                if ($menuSelected == 'tmp') {
                    $tr .= "<td class=rowfollow>{$arr1['expired_at']}</td>";
                    $tr .= "<td class=rowfollow>{$arr1['created_at']}</td>";
                }
                $tr .= "</tr>";
                print($tr);
            }
        }
        print("</table>");
        print("</td></tr></table>$pagertop");
    }

}
stdfoot();
die;
?>

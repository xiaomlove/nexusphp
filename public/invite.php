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

function inviteMenu ($selected = "invitee") {
    global $lang_invite, $id, $CURUSER;
    begin_main_frame("", false, "100%");
    print ("<div id=\"invitenav\" style='position: relative'><ul id=\"invitemenu\" class=\"menu\">");
    print ("<li" . ($selected == "invitee" ? " class=selected" : "") . "><a href=\"?id=".$id."&menu=invitee\">".$lang_invite['text_invite_status']."</a></li>");
    print ("<li" . ($selected == "sent" ? " class=selected" : "") . "><a href=\"?id=".$id."&menu=sent\">".$lang_invite['text_sent_invites_status']."</a></li>");
    print ("</ul><form style='position: absolute;top:0;right:0' method=post action=invite.php?id=".htmlspecialchars($id)."&type=new><input type=submit ".($CURUSER['invites'] <= 0 ? "disabled " : "")." value='".$lang_invite['sumbit_invite_someone']."'></form></div>");
    end_main_frame();
}

if (($CURUSER['id'] != $id && get_user_class() < $viewinvite_class) || !is_valid_id($id))
stderr($lang_invite['std_sorry'],$lang_invite['std_permission_denied']);
if (get_user_class() < $sendinvite_class)
stderr($lang_invite['std_sorry'],$lang_invite['std_only'].get_user_class_name($sendinvite_class,false,true,true).$lang_invite['std_or_above_can_invite'],false);
$res = sql_query("SELECT username FROM users WHERE id = ".mysql_real_escape_string($id)) or sqlerr();
$user =  mysql_fetch_assoc($res);
stdhead($lang_invite['head_invites']);
print("<table width=100% class=main border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>");

print("<h1 align=center><a href=\"invite.php?id=".$id."\">".$user['username'].$lang_invite['text_invite_system']."</a></h1>");
	$sent = htmlspecialchars($_GET['sent'] ?? '');
	if ($sent == 1){
		$msg = $lang_invite['text_invite_code_sent'];
		print("<p align=center><font color=red>".$msg."</font></p>");
	}

$res = sql_query("SELECT invites FROM users WHERE id = ".mysql_real_escape_string($id)) or sqlerr();
$inv = mysql_fetch_assoc($res);

//for one or more. "invite"/"invites"
if ($inv["invites"] != 1){
	$_s = $lang_invite['text_s'];
} else {
	$_s = "";
}

if ($type == 'new'){
    registration_check('invitesystem',true,false);
	if ($CURUSER['invites'] <= 0) {
		stdmsg($lang_invite['std_sorry'],$lang_invite['std_no_invites_left'].
		"<a class=altlink href=invite.php?id={$CURUSER['id']}>".$lang_invite['here_to_go_back'],false);
		print("</td></tr></table>");
		stdfoot();
		die;
	}
	$invitation_body =  $lang_invite['text_invitation_body'].$CURUSER['username'];
	//$invitation_body_insite = str_replace("<br />","\n",$invitation_body);
	print("<form method=post action=takeinvite.php?id=".htmlspecialchars($id).">".
	"<table border=1 width=100% cellspacing=0 cellpadding=5>".
	"<tr align=center><td colspan=2><b>".$lang_invite['text_invite_someone']."$SITENAME ({$inv['invites']}".$lang_invite['text_invitation'].$_s.$lang_invite['text_left'] .")</b></td></tr>".
	"<tr><td class=\"rowhead nowrap\" valign=\"top\" align=\"right\">".$lang_invite['text_email_address']."</td><td align=left><input type=text size=40 name=email><br /><font align=left class=small>".$lang_invite['text_email_address_note']."</font>".($restrictemaildomain == 'yes' ? "<br />".$lang_invite['text_email_restriction_note'].allowedemails() : "")."</td></tr>".
	"<tr><td class=\"rowhead nowrap\" valign=\"top\" align=\"right\">".$lang_invite['text_message']."</td><td align=left><textarea name=body rows=10 style='width: 100%'>" .$invitation_body.
	"</textarea></td></tr>".
	"<tr><td align=center colspan=2><input type=submit value='".$lang_invite['submit_invite']."'></td></tr>".
	"</form></table></td></tr></table>");

} else {
    inviteMenu($menuSelected);
    if ($menuSelected == 'invitee') {
        $rel = sql_query("SELECT COUNT(*) FROM users WHERE invited_by = ".mysql_real_escape_string($id)) or sqlerr(__FILE__, __LINE__);
        $arro = mysql_fetch_row($rel);
        $number = $arro[0];

        print("<table border=1 width=100% cellspacing=0 cellpadding=5>".
            "<form method=post action=takeconfirm.php?id=".htmlspecialchars($id).">");

        if(!$number){
            print("<tr><td colspan=7 align=center>".$lang_invite['text_no_invites']."</tr>");
        } else {
            list($pagertop, $pagerbottom, $limit) = pager($pageSize, $number, "?id=$id&menu=$menuSelected&");
            $haremAdditionFactor = get_setting('bonus.harem_addition');
            $ret = sql_query("SELECT id, username, email, uploaded, downloaded, status, warned, enabled, donor, email FROM users WHERE invited_by = ".mysql_real_escape_string($id) . " $limit") or sqlerr();
            $num = mysql_num_rows($ret);

            print("<tr><td class=colhead><b>".$lang_invite['text_username']."</b></td><td class=colhead><b>".$lang_invite['text_email']."</b></td><td class=colhead><b>".$lang_invite['text_uploaded']."</b></td><td class=colhead><b>".$lang_invite['text_downloaded']."</b></td><td class=colhead><b>".$lang_invite['text_ratio']."</b></td>");
            if ($haremAdditionFactor > 0) {
                print('<td class="colhead">'.$lang_invite['harem_addition'].'</td>');
            }
            print("<td class=colhead><b>".$lang_invite['text_status']."</b></td>");
            if ($CURUSER['id'] == $id || get_user_class() >= UC_SYSOP) {
                print("<td class=colhead><b>".$lang_invite['text_confirm']."</b></td>");
            }

            print("</tr>");
            for ($i = 0; $i < $num; ++$i)
            {
                $arr = mysql_fetch_assoc($ret);
                $user = "<td class=rowfollow>" . get_username($arr['id']) . "</td>";

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

                print("<tr class=rowfollow>$user<td>{$arr['email']}</td><td class=rowfollow>" . mksize($arr['uploaded']) . "</td><td class=rowfollow>" . mksize($arr['downloaded']) . "</td><td class=rowfollow>$ratio</td>");
                if ($haremAdditionFactor > 0) {
                    print ("<td class=rowfollow>".number_format(calculate_seed_bonus($arr['id'])['all_bonus'] * $haremAdditionFactor, 3)."</td>");
                }
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
    } elseif ($menuSelected == 'sent') {
        $rul = sql_query("SELECT COUNT(*) FROM invites WHERE inviter =".mysql_real_escape_string($id)) or sqlerr();
        $arre = mysql_fetch_row($rul);
        $number1 = $arre[0];

        print("<table border=1 width=100% cellspacing=0 cellpadding=5>");

        if(!$number1){
            print("<tr align=center><td colspan=6>".$lang_invite['text_no_invitation_sent']."</tr>");
        } else {
            list($pagertop, $pagerbottom, $limit) = pager($pageSize, $number1, "?id=$id&menu=$menuSelected&");

            $rer = sql_query("SELECT * FROM invites WHERE inviter = ".mysql_real_escape_string($id) . " $limit") or sqlerr();
            $num1 = mysql_num_rows($rer);

            print("<tr><td class=colhead>".$lang_invite['text_email']."</td><td class=colhead>".$lang_invite['text_hash']."</td><td class=colhead>".$lang_invite['text_send_date']."</td><td class='colhead'>".$lang_invite['text_hash_status']."</td><td class='colhead'>".$lang_invite['text_invitee_user']."</td></tr>");
            for ($i = 0; $i < $num1; ++$i)
            {
                $arr1 = mysql_fetch_assoc($rer);
                $tr = "<tr>";
                $tr .= "<td class=rowfollow>{$arr1['invitee']}</td>";
                $tr .= "<td class=rowfollow>{$arr1['hash']}</td>";
                $tr .= "<td class=rowfollow>{$arr1['time_invited']}</td>";
                $tr .= "<td class=rowfollow>".\App\Models\Invite::$validInfo[$arr1['valid']]['text']."</td>";
                if ($arr1['valid'] == \App\Models\Invite::VALID_NO) {
                    $tr .= "<td class=rowfollow><a href=userdetails.php?id={$arr1['invitee_register_uid']}><font color=#1f7309>".$arr1['invitee_register_username']."</font></a></td>";
                } else {
                    $tr .= "<td class='rowfollow'></td>";
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

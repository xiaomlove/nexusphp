<?php

use App\Models\Message;
use App\Models\User;
use App\Models\UserBanLog;
use App\Models\UserModifyLog;
use App\Models\UsernameChangeLog;
use Nexus\Database\NexusDB;

require '../include/bittorrent.php';
dbconn();
// require(get_langfile_path("",true));
loggedinorreturn();

function puke()
{
    $msg = 'User '.$CURUSER['username'].' (id: '.$CURUSER['id'].") is hacking user's profile. IP : ".getip();
    write_log($msg, 'mod');
    stderr('Error', 'Permission denied. For security reason, we logged this action');
}

if (! user_can('prfmanage')) {
    puke();
}

$action = $_POST['action'];
if ($action == 'confirmuser') {
    $userid = $_POST['userid'];
    $confirm = $_POST['confirm'];
    NexusDB::table('users')
        ->where('id', (int) $userid)
        ->limit(1)
        ->update(['status' => (string) $confirm, 'info' => null]);
    header('Location: '.get_protocol_prefix()."$BASEURL/unco.php?status=1");
    exit;
}
if ($action == 'edituser') {
    $userid = $_POST['userid'];
    $userInfo = User::query()->findOrFail($userid);
    //	$class = intval($_POST["class"] ?? 0);
    $class = $userInfo->class;
    $locale = get_user_locale($userid);
    //	$vip_added = ($_POST["vip_added"] == 'yes' ? 'yes' : 'no');
    $vip_added = $userInfo->vip_added;
    //	$vip_until = !empty($_POST["vip_until"]) ? $_POST['vip_until'] : null;
    $vip_until = $userInfo->vip_until;

    $warned = $_POST['warned'] ?? '';
    $warnlength = intval($_POST['warnlength'] ?? 0);
    $warnpm = $_POST['warnpm'];
    $title = $_POST['title'];
    $avatar = $_POST['avatar'];
    $signature = $_POST['signature'];

    $enabled = $_POST['enabled'];
    $uploadpos = $_POST['uploadpos'];
    $downloadpos = $_POST['downloadpos'];
    $noad = $_POST['noad'];
    $noaduntil = $_POST['noaduntil'];
    $privacy = $_POST['privacy'];
    $forumpost = $_POST['forumpost'];
    $chpassword = $_POST['chpassword'];
    $passagain = $_POST['passagain'];

    $supportlang = $_POST['supportlang'];
    $support = $_POST['support'];
    $supportfor = $_POST['supportfor'];

    $moviepicker = $_POST['moviepicker'];
    $pickfor = $_POST['pickfor'];
    $stafffor = $_POST['staffduties'];

    if (! is_valid_id($userid) || ! is_valid_user_class($class)) {
        stderr('Error', 'Bad user ID or class ID.');
    }
    if (get_user_class() <= $class) {
        stderr('Error', "You have no permission to change user's class to ".get_user_class_name($class, false, false, true).'. BTW, how do you get here?');
    }
    $arr = NexusDB::table('users')
        ->where('id', (int) $userid)
        ->first();
    if (! $arr) {
        puke();
    }
    $arr = (array) $arr;
    $user = User::query()->findOrFail($userid);

    $curenabled = $arr['enabled'];
    $curparked = $arr['parked'];
    $curuploadpos = $arr['uploadpos'];
    $curdownloadpos = $arr['downloadpos'];
    $curforumpost = $arr['forumpost'];
    $curclass = $arr['class'];
    $curwarned = $arr['warned'];

    $updateset = [];
    $updateset['stafffor'] = (string) $stafffor;
    $updateset['pickfor'] = (string) $pickfor;
    $updateset['picker'] = (string) $moviepicker;
    // migrate to management
    //	$updateset['enabled'] = (string) $enabled;
    $updateset['uploadpos'] = (string) $uploadpos;
    $updateset['downloadpos'] = (string) $downloadpos;
    $updateset['forumpost'] = (string) $forumpost;
    $updateset['avatar'] = (string) $avatar;
    $updateset['signature'] = (string) $signature;
    $updateset['title'] = (string) $title;
    $updateset['support'] = (string) $support;
    $updateset['supportfor'] = (string) $supportfor;
    $updateset['supportlang'] = (string) $supportlang;
    $banLog = [];
    $userModifyLogs = [];

    //	if(!user_can('cruprfmanage'))
    //	{
    //		$modcomment = $arr["modcomment"];
    //	}
    if (user_can('cruprfmanage')) {
        $email = $_POST['email'];
        $username = $_POST['username'];
        $modcomment = $_POST['modcomment'];
        $downloaded = $_POST['downloaded'];
        $ori_downloaded = $_POST['ori_downloaded'];
        $uploaded = $_POST['uploaded'];
        $ori_uploaded = $_POST['ori_uploaded'];
        $bonus = $_POST['bonus'];
        $ori_bonus = $_POST['ori_bonus'];
        $invites = $_POST['invites'];
        if ($arr['email'] != $email) {
            $updateset['email'] = (string) $email;
            //			$modcomment = date("Y-m-d") . " - Email changed from $arr[email] to $email by {$CURUSER['username']}.\n". $modcomment;
            $modifyLog = "Email changed from $arr[email] to $email by {$CURUSER['username']}.";
            do_log($modifyLog, 'alert');
            $userModifyLogs[] = $modifyLog;
            $locale = get_user_locale($userid);
            $subject = nexus_trans('user.msg_email_change', [], $locale);
            $msg = nexus_trans('user.msg_your_email_changed_from', [], $locale).$arr['email'].nexus_trans('user.msg_to_new', [], $locale).$email.nexus_trans('user.msg_by', [], $locale).$CURUSER['username'];

            Message::add([
                'sender' => 0,
                'receiver' => $userid,
                'subject' => $subject,
                'msg' => $msg,
                'added' => now(),
            ]);
        }
        if ($arr['username'] != $username) {
            $updateset['username'] = (string) $username;
            //			$modcomment = date("Y-m-d") . " - Username changed from {$arr['username']} to $username by {$CURUSER['username']}.\n". $modcomment;
            $userModifyLogs[] = "Username changed from {$arr['username']} to $username by {$CURUSER['username']}";

            $subject = nexus_trans('user.msg_username_change', [], $locale);
            $msg = nexus_trans('user.msg_your_username_changed_from', [], $locale).$arr['username'].nexus_trans('user.msg_to_new', [], $locale).$username.nexus_trans('user.msg_by', [], $locale).$CURUSER['username'];

            Message::add([
                'sender' => 0,
                'receiver' => $userid,
                'subject' => $subject,
                'msg' => $msg,
                'added' => now(),
            ]);

            $changeLog = [
                'uid' => $arr['id'],
                'operator' => $CURUSER['username'],
                'change_type' => UsernameChangeLog::CHANGE_TYPE_ADMIN,
                'username_old' => $arr['username'],
                'username_new' => $username,
            ];
            UsernameChangeLog::query()->create($changeLog);
        }
        // migrate to management
        //		if ($ori_downloaded != $downloaded){
        //			$updateset[] = "downloaded = " . sqlesc($downloaded);
        //			$modcomment = date("Y-m-d") . " - Downloaded amount changed from $arr[downloaded] to $downloaded by {$CURUSER['username']}.\n". $modcomment;
        //			$subject = sqlesc($lang_modtask_target[get_user_lang($userid)]['msg_downloaded_change']);
        //			$msg = sqlesc($lang_modtask_target[get_user_lang($userid)]['msg_your_downloaded_changed_from'].mksize($arr['downloaded']).$lang_modtask_target[get_user_lang($userid)]['msg_to_new'] . mksize($downloaded) .$lang_modtask_target[get_user_lang($userid)]['msg_by'].$CURUSER['username']);
        //			sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) VALUES(0, $userid, $subject, $msg, $added)") or sqlerr(__FILE__, __LINE__);
        //		}
        //
        //		if ($ori_uploaded != $uploaded){
        //			$updateset[] = "uploaded = " . sqlesc($uploaded);
        //			$modcomment = date("Y-m-d") . " - Uploaded amount changed from $arr[uploaded] to $uploaded by {$CURUSER['username']}.\n". $modcomment;
        //			$subject = sqlesc($lang_modtask_target[get_user_lang($userid)]['msg_uploaded_change']);
        //			$msg = sqlesc($lang_modtask_target[get_user_lang($userid)]['msg_your_uploaded_changed_from'].mksize($arr['uploaded']).$lang_modtask_target[get_user_lang($userid)]['msg_to_new'] . mksize($uploaded) .$lang_modtask_target[get_user_lang($userid)]['msg_by'].$CURUSER['username']);
        //			sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) VALUES(0, $userid, $subject, $msg, $added)") or sqlerr(__FILE__, __LINE__);
        //		}
        //		if ($ori_bonus != $bonus){
        //			$updateset[] = "seedbonus = " . sqlesc($bonus);
        //			$modcomment = date("Y-m-d") . " - Bonus amount changed from $arr[seedbonus] to $bonus by {$CURUSER['username']}.\n". $modcomment;
        //			$subject = sqlesc($lang_modtask_target[get_user_lang($userid)]['msg_bonus_change']);
        //			$msg = sqlesc($lang_modtask_target[get_user_lang($userid)]['msg_your_bonus_changed_from'].$arr['seedbonus'].$lang_modtask_target[get_user_lang($userid)]['msg_to_new'] . $bonus .$lang_modtask_target[get_user_lang($userid)]['msg_by'].$CURUSER['username']);
        //			sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) VALUES(0, $userid, $subject, $msg, $added)") or sqlerr(__FILE__, __LINE__);
        //		}
        //		if ($arr['invites'] != $invites){
        //			$updateset[] = "invites = " . sqlesc($invites);
        //			$modcomment = date("Y-m-d") . " - Invite amount changed from $arr[invites] to $invites by {$CURUSER['username']}.\n". $modcomment;
        //			$subject = sqlesc($lang_modtask_target[get_user_lang($userid)]['msg_invite_change']);
        //			$msg = sqlesc($lang_modtask_target[get_user_lang($userid)]['msg_your_invite_changed_from'].$arr['invites'].$lang_modtask_target[get_user_lang($userid)]['msg_to_new'] . $invites .$lang_modtask_target[get_user_lang($userid)]['msg_by'].$CURUSER['username']);
        //			sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) VALUES(0, $userid, $subject, $msg, $added)") or sqlerr(__FILE__, __LINE__);
        //		}
    }
    if (get_user_class() == UC_STAFFLEADER) {
        $donor = $_POST['donor'];
        $donoruntil = ! empty($_POST['donoruntil']) ? $_POST['donoruntil'] : null;
        $donated = $_POST['donated'];
        $donated_cny = $_POST['donated_cny'];
        $this_donated_usd = $donated - $arr['donated'];
        $this_donated_cny = $donated_cny - $arr['donated_cny'];
        $memo = htmlspecialchars($_POST['donation_memo']);

        if ($donated != $arr['donated'] || $donated_cny != $arr['donated_cny']) {
            NexusDB::insert('funds', [
                'usd' => (string) $this_donated_usd,
                'cny' => (string) $this_donated_cny,
                'user' => (int) $userid,
                'added' => date('Y-m-d H:i:s'),
                'memo' => (string) $memo,
            ]);
            $updateset['donated'] = (string) $donated;
            $updateset['donated_cny'] = (string) $donated_cny;
        }
        $updateset['donor'] = (string) $donor;
        $updateset['donoruntil'] = $donoruntil; // nullable date string

        if (($donor != $arr['donor']) && (($donor == 'yes' && $donoruntil && $donoruntil >= date('Y-m-d H:i:s')) || ($donor == 'no'))) {
            $subject = nexus_trans('user.msg_your_donor_status_changed', [], $locale);
            $msg = nexus_trans('user.msg_donor_status_changed_by', [], $locale).$CURUSER['username'];

            Message::add([
                'sender' => 0,
                'receiver' => $userid,
                'subject' => $subject,
                'msg' => $msg,
                'added' => now(),
            ]);

            //            $modcomment = date("Y-m-d") . " - donor status changed by {$CURUSER['username']}. Current donor status: $donor \n". $modcomment;
            $userModifyLogs[] = "donor status changed by {$CURUSER['username']}. Current donor status: $donor";
        }
    }
    // migrate to management
    //	if ($chpassword != "" AND $passagain != "") {
    //		unset($passupdate);
    //		$passupdate=false;
    //
    //		if ($chpassword ==  $username OR strlen($chpassword) > 40 OR strlen($chpassword) < 6 OR $chpassword != $passagain)
    //			$passupdate=false;
    //		else
    //			$passupdate=true;
    //	}
    //
    //	if (isset($passupdate) && $passupdate) {
    //		$sec = mksecret();
    //		$passhash = md5($sec . $chpassword . $sec);
    //		$updateset[] = "secret = " . sqlesc($sec);
    //		$updateset[] = "passhash = " . sqlesc($passhash);
    //	}

    if ($curclass >= get_user_class()) {
        puke();
    }

    // migrate to management
    //	if (user_can('user-change-class') && $curclass != $class)
    //	{
    //		$what = ($class > $curclass ? $lang_modtask_target[get_user_lang($userid)]['msg_promoted'] : $lang_modtask_target[get_user_lang($userid)]['msg_demoted']);
    //		$subject = sqlesc($lang_modtask_target[get_user_lang($userid)]['msg_class_change']);
    //		$msg = sqlesc($lang_modtask_target[get_user_lang($userid)]['msg_you_have_been'].$what.$lang_modtask_target[get_user_lang($userid)]['msg_to'] . get_user_class_name($class) .$lang_modtask_target[get_user_lang($userid)]['msg_by'].$CURUSER['username']);
    //		$added = sqlesc(date("Y-m-d H:i:s"));
    //		sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) VALUES(0, $userid, $subject, $msg, $added)") or sqlerr(__FILE__, __LINE__);
    //		$updateset[] = "class = $class";
    //		$what = ($class > $curclass ? "Promoted" : "Demoted");
    //		$modcomment = date("Y-m-d") . " - $what to '" . get_user_class_name($class) . "' by {$CURUSER['username']}.\n". $modcomment;
    //	}
    //	if ($class == UC_VIP)
    //	{
    //		$updateset[] = "vip_added = ".sqlesc($vip_added);
    //		if ($vip_added == 'yes')
    //			$updateset[] = "vip_until = ".sqlesc($vip_until);
    //		$subject = nexus_trans("user.msg_your_vip_status_changed", [], $locale);
    //		$msg = nexus_trans("user.msg_vip_status_changed_by", [], $locale).$CURUSER['username'];
    //		$added = sqlesc(date("Y-m-d H:i:s"));
    //
    //		\App\Models\Message::add([
    //		    'sender' => 0,
    //		    'receiver' => $userid,
    //		    'subject' => $subject,
    //		    'msg' => $msg,
    //		    'added' => now(),
    //		]);
    //
    // //		$modcomment = date("Y-m-d") . " - VIP status changed by {$CURUSER['username']}. VIP added: ".$vip_added.($vip_added == 'yes' ? "; VIP until: ".$vip_until : "").".\n". $modcomment;
    //        $userModifyLogs[] = "VIP status changed by {$CURUSER['username']}. VIP added: ".$vip_added.($vip_added == 'yes' ? "; VIP until: ".$vip_until : "");
    //	}

    if ($warned && $curwarned != $warned) {
        $updateset['warned'] = (string) $warned;
        $updateset['warneduntil'] = null;

        if ($warned == 'no') {
            //			$modcomment = date("Y-m-d") . " - Warning removed by {$CURUSER['username']}.\n". $modcomment;
            $userModifyLogs[] = "Warning removed by {$CURUSER['username']}";
            $subject = nexus_trans('user.msg_warn_removed', [], $locale);
            $msg = nexus_trans('user.msg_your_warning_removed_by', [], $locale).$CURUSER['username'].'.';
        }

        // sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) VALUES (0, $userid, $subject, $msg, $added)") or sqlerr(__FILE__, __LINE__);
        Message::add([
            'sender' => 0,
            'receiver' => $userid,
            'subject' => $subject,
            'msg' => $msg,
            'added' => now(),
        ]);
    } elseif ($warnlength) {
        if ($warnlength == 255) {
            //			$modcomment = date("Y-m-d") . " - Warned by " . $CURUSER['username'] . ".\nReason: $warnpm.\n". $modcomment;
            $userModifyLogs[] = 'Warned by '.$CURUSER['username'].".\nReason: $warnpm.";

            $msg = nexus_trans('user.msg_you_are_warned_by', [], $locale).$CURUSER['username'].'.'.($warnpm ? nexus_trans('user.msg_reason', [], $locale).$warnpm : '');
            $updateset['warneduntil'] = null;
        } else {
            $warneduntil = date('Y-m-d H:i:s', (strtotime(date('Y-m-d H:i:s')) + $warnlength * 604800));
            $dur = $warnlength.nexus_trans('user.msg_week', [], $locale).($warnlength > 1 ? nexus_trans('user.msg_s', [], $locale) : '');
            $msg = nexus_trans('user.msg_you_are_warned_for', [], $locale).$dur.nexus_trans('user.msg_by', [], $locale).$CURUSER['username'].'.'.($warnpm ? nexus_trans('user.msg_reason', [], $locale).$warnpm : '');
            //			$modcomment = date("Y-m-d") . " - Warned for $dur by " . $CURUSER['username'] .  ".\nReason: $warnpm.\n". $modcomment;
            $userModifyLogs[] = "Warned for $dur by ".$CURUSER['username'].".Reason: $warnpm";
            $updateset['warneduntil'] = $warneduntil;
        }
        $subject = nexus_trans('user.msg_you_are_warned', [], $locale);

        Message::add([
            'sender' => 0,
            'receiver' => $userid,
            'subject' => $subject,
            'msg' => $msg,
            'added' => now(),
        ]);

        $updateset['warned'] = 'yes';
        $updateset['timeswarned'] = NexusDB::raw('timeswarned + 1');
        $updateset['lastwarned'] = NexusDB::raw('NOW()');
        $updateset['warnedby'] = (int) $CURUSER['id'];
    }
    // migrate to management
    //	if ($enabled != $curenabled)
    //	{
    //		if ($enabled == 'yes') {
    //			$modcomment = date("Y-m-d") . " - Enabled by " . $CURUSER['username']. ".\n". $modcomment;
    //			if (get_single_value("users","class","WHERE id = ".sqlesc($userid)) == UC_PEASANT){
    //				$length = 30*86400; // warn users until 30 days
    //				$until = sqlesc(date("Y-m-d H:i:s",(strtotime(date("Y-m-d H:i:s")) + $length)));
    //				sql_query("UPDATE users SET enabled='yes', leechwarn='yes', leechwarnuntil=$until WHERE id = ".sqlesc($userid));
    //			}
    //			else{
    //				sql_query("UPDATE users SET enabled='yes', leechwarn='no' WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
    //			}
    //		} else {
    //			$modcomment = date("Y-m-d") . " - Disabled by " . $CURUSER['username']. ".\n". $modcomment;
    //			$banLog = [
    //			    'uid' => $userid,
    //                'username' => $user->username,
    //                'operator' => $CURUSER['id'],
    //                'reason' => nexus_trans('user.edit_ban_reason', [], $user->locale),
    //            ];
    //		}
    //	}
    if ($arr['noad'] != $noad) {
        $updateset['noad'] = (string) $noad;
        //		$modcomment = date("Y-m-d") . " - No Ad set to ".$noad." by ". $CURUSER['username']. ".\n". $modcomment;
        $userModifyLogs[] = 'No Ad set to '.$noad.' by '.$CURUSER['username'];
    }
    if ($arr['noaduntil'] != $noaduntil) {
        $updateset['noaduntil'] = $noaduntil; // nullable date string
        //		$modcomment = date("Y-m-d") . " - No Ad Until set to ".$noaduntil." by ". $CURUSER['username']. ".\n". $modcomment;
        $userModifyLogs[] = 'No Ad Until set to '.$noaduntil.' by '.$CURUSER['username'];
    }
    if ($privacy == 'low' or $privacy == 'normal' or $privacy == 'strong') {
        $updateset['privacy'] = (string) $privacy;
    }

    if (isset($_POST['resetkey']) && $_POST['resetkey'] == 'yes') {
        $newpasskey = md5($arr['username'].date('Y-m-d H:i:s').$arr['passhash']);
        $updateset['passkey'] = (string) $newpasskey;
    }
    if ($forumpost != $curforumpost) {
        if ($forumpost == 'yes') {
            //			$modcomment = date("Y-m-d") . " - Posting enabled by " . $CURUSER['username'] . ".\n" . $modcomment;
            $userModifyLogs[] = 'Posting enabled by '.$CURUSER['username'];
            $subject = nexus_trans('user.msg_posting_rights_restored', [], $locale);
            $msg = nexus_trans('user.msg_your_posting_rights_restored', [], $locale).$CURUSER['username'].nexus_trans('user.msg_you_can_post', [], $locale);
            Message::add([
                'sender' => 0,
                'receiver' => $userid,
                'subject' => $subject,
                'msg' => $msg,
                'added' => now(),
            ]);
        } else {
            //			$modcomment = date("Y-m-d") . " - Posting disabled by " . $CURUSER['username'] . ".\n" . $modcomment;
            $userModifyLogs[] = 'Posting disabled by '.$CURUSER['username'];
            $subject = nexus_trans('user.msg_posting_rights_removed', [], $locale);
            $msg = nexus_trans('user.msg_your_posting_rights_removed', [], $locale).$CURUSER['username'].nexus_trans('user.msg_probable_reason', [], $locale);
            Message::add([
                'sender' => 0,
                'receiver' => $userid,
                'subject' => $subject,
                'msg' => $msg,
                'added' => now(),
            ]);
        }
    }
    if ($uploadpos != $curuploadpos) {
        if ($uploadpos == 'yes') {
            //			$modcomment = date("Y-m-d") . " - Upload enabled by " . $CURUSER['username'] . ".\n" . $modcomment;
            $userModifyLogs[] = 'Upload enabled by '.$CURUSER['username'];
            $subject = nexus_trans('user.msg_upload_rights_restored', [], $locale);
            $msg = nexus_trans('user.msg_your_upload_rights_restored', [], $locale).$CURUSER['username'].nexus_trans('user.msg_you_upload_can_upload', [], $locale);
            Message::add([
                'sender' => 0,
                'receiver' => $userid,
                'subject' => $subject,
                'msg' => $msg,
                'added' => now(),
            ]);
        } else {
            //			$modcomment = date("Y-m-d") . " - Upload disabled by " . $CURUSER['username'] . ".\n" . $modcomment;
            $userModifyLogs[] = 'Upload disabled by '.$CURUSER['username'];
            $subject = nexus_trans('user.msg_upload_rights_removed', [], $locale);
            $msg = nexus_trans('user.msg_your_upload_rights_removed', [], $locale).$CURUSER['username'].nexus_trans('user.msg_probably_reason_two', [], $locale);
            Message::add([
                'sender' => 0,
                'receiver' => $userid,
                'subject' => $subject,
                'msg' => $msg,
                'added' => now(),
            ]);
        }
    }
    if ($downloadpos != $curdownloadpos) {
        if ($downloadpos == 'yes') {
            //			$modcomment = date("Y-m-d") . " - Download enabled by " . $CURUSER['username'] . ".\n" . $modcomment;
            $userModifyLogs[] = 'Download enabled by '.$CURUSER['username'];
            $subject = nexus_trans('user.msg_download_rights_restored', [], $locale);
            $msg = nexus_trans('user.msg_your_download_rights_restored', [], $locale).$CURUSER['username'].nexus_trans('user.msg_you_can_download', [], $locale);

            Message::add([
                'sender' => 0,
                'receiver' => $userid,
                'subject' => $subject,
                'msg' => $msg,
                'added' => now(),
            ]);
        } else {
            //			$modcomment = date("Y-m-d") . " - Download disabled by " . $CURUSER['username'] . ".\n" . $modcomment;
            $userModifyLogs[] = 'Download disabled by '.$CURUSER['username'];
            $subject = nexus_trans('user.msg_download_rights_removed', [], $locale);
            $msg = nexus_trans('user.msg_your_download_rights_removed', [], $locale).$CURUSER['username'].nexus_trans('user.msg_probably_reason_three', [], $locale);

            Message::add([
                'sender' => 0,
                'receiver' => $userid,
                'subject' => $subject,
                'msg' => $msg,
                'added' => now(),
            ]);
        }
    }

    //	$updateset['modcomment'] = (string) $modcomment;
    NexusDB::table('users')
        ->where('id', (int) $userid)
        ->update($updateset);
    if (! empty($banLog)) {
        UserBanLog::query()->insert($banLog);
    }
    if (! empty($userModifyLogs)) {
        $userModifyLogsInsert = [];
        foreach ($userModifyLogs as $userModifyLog) {
            $userModifyLogsInsert[] = [
                'user_id' => $userid,
                'content' => $userModifyLog,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
        }
        UserModifyLog::query()->insert($userModifyLogsInsert);
    }
    clear_user_cache($userid, $userInfo->passkey);
    $returnto = htmlspecialchars($_POST['returnto']);
    header('Location: '.get_protocol_prefix()."$BASEURL/$returnto");
    exit;
}
puke();

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
    global $CURUSER;
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

    if ($curclass >= get_user_class()) {
        puke();
    }


    if ($warned && $curwarned != $warned) {
        $updateset['warned'] = (string) $warned;
        $updateset['warneduntil'] = null;

        if ($warned == 'no') {
            //			$modcomment = date("Y-m-d") . " - Warning removed by {$CURUSER['username']}.\n". $modcomment;
            $userModifyLogs[] = "Warning removed by {$CURUSER['username']}";
            $subject = nexus_trans('user.msg_warn_removed', [], $locale);
            $msg = nexus_trans('user.msg_your_warning_removed_by', [], $locale).$CURUSER['username'].'.';
        }

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

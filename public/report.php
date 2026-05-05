<?php

use Nexus\Database\NexusDB;

require_once '../include/bittorrent.php';
dbconn();
require_once get_langfile_path();
loggedinorreturn();
parked();

$reportofferid = $_GET['reportofferid'];
$reportrequestid = $_GET['reportrequestid'];
$user = $_GET['user'];
$commentid = $_GET['commentid'];
$torrent = $_GET['torrent'];
$forumpost = $_GET['forumpost'];
$subtitle = $_GET['subtitle'];

$takeuser = $_POST['takeuser'];
$takecommentid = $_POST['takecommentid'];
$taketorrent = $_POST['taketorrent'];
$takeforumpost = $_POST['takeforumpost'];
$takereason = $_POST['reason'];
$takereportofferid = $_POST['takereportofferid'];
$takerequestid = $_POST['takerequestid'];
$takesubtitleid = $_POST['takesubtitleid'];

function takereport($reportid, $type, $reason)
{
    global $CURUSER, $lang_report, $Cache;
    int_check($reportid);
    // Check if takereason is set
    if ($reason == '') {
        stderr($lang_report['std_error'], $lang_report['std_missing_reason']);
        exit();
    }
    $existing = NexusDB::table('reports')
        ->where('addedby', (int) $CURUSER['id'])
        ->where('reportid', (int) $reportid)
        ->where('type', (string) $type)
        ->count();
    if ($existing == 0) {
        NexusDB::insert('reports', [
            'addedby' => (int) $CURUSER['id'],
            'reportid' => (int) $reportid,
            'type' => (string) $type,
            'reason' => trim($reason),
            'added' => date('Y-m-d H:i:s'),
        ]);
        $Cache->delete_value('staff_report_count');
        $Cache->delete_value('staff_new_report_count');
        stderr($lang_report['std_message'], $lang_report['std_successfully_reported']);
        exit();
    } else {
        stderr($lang_report['std_error'], $lang_report['std_already_reported_this']);
        exit();
    }
}

// ////////OFFER #1 START//////////
if (isset($takereportofferid) && isset($takereason)) {
    takereport($takereportofferid, 'offer', $takereason);
}
// ////////OFFER #1 END//////////

// ////////REQUEST #1 START//////////
elseif ((isset($takerequestid)) && (isset($takereason))) {
    takereport($takerequestid, 'request', $takereason);
}
// ////////REQUEST #1 END//////////

// ////////USER #1 START//////////
elseif ((isset($takeuser)) && (isset($takereason))) {
    takereport($takeuser, 'user', $takereason);
}
// ////////USER #1 END//////////

// ////////TORRENT #1 START//////////
elseif ((isset($taketorrent)) && (isset($takereason))) {
    takereport($taketorrent, 'torrent', $takereason);
}
// ////////TORRENT #1 END//////////

// ////////FORUM POST #1 START//////////
elseif ((isset($takeforumpost)) && (isset($takereason))) {
    takereport($takeforumpost, 'post', $takereason);
}
// ////////FORUM #1 END//////////

// ////////COMMENT #1 START//////////
elseif ((isset($takecommentid)) && (isset($takereason))) {
    takereport($takecommentid, 'comment', $takereason);
}
// ////////COMMENT #1 END//////////

// ////////SUBTITLE #1 START//////////
elseif ((isset($takesubtitleid)) && (isset($takereason))) {
    takereport($takesubtitleid, 'subtitle', $takereason);
}
// ////////SUBTITLE #1 END//////////

// ////////USER #2 START//////////
elseif (isset($user)) {
    int_check($user);
    if ($user == $CURUSER['id']) {
        stderr($lang_report['std_sorry'], $lang_report['std_cannot_report_oneself']);
        exit;
    }
    $arr = NexusDB::table('users')
        ->where('id', (int) $user)
        ->select(['username', 'class'])
        ->first();
    $arr = $arr ? (array) $arr : null;
    if (! $arr) {
        stderr($lang_report['std_error'], $lang_report['std_invalid_user_id']);
        exit();
    }
    if ($arr['class'] >= $staffmem_class) {
        stderr($lang_report['std_sorry'], $lang_report['std_cannot_report'].get_user_class_name($arr['class'], false, true, true), false);
        exit();
    } else {
        stderr($lang_report['std_are_you_sure'], $lang_report['text_are_you_sure_user'].get_username(htmlspecialchars($user)).$lang_report['text_to_staff'].'<br />'.$lang_report['text_not_for_leechers'].'<br />'.$lang_report['text_reason_note'].'<br /><form method=post action=report.php><input type=hidden name=takeuser value="'.htmlspecialchars($user).'">'.$lang_report['text_reason_is'].'<input type=text style="width: 200px" name=reason><input type=submit value="'.$lang_report['submit_confirm'].'"></form>', false);
    }
}
// ////////USER #2 END//////////

// ////////TORRENT #2 START//////////
elseif (isset($torrent)) {
    int_check($torrent);
    $arr = NexusDB::table('torrents')
        ->where('id', (int) $torrent)
        ->select(['name'])
        ->first();
    $arr = $arr ? (array) $arr : null;
    if (! $arr) {
        stderr($lang_report['std_error'], $lang_report['std_invalid_torrent_id']);
        exit();
    }
    stderr($lang_report['std_are_you_sure'], $lang_report['text_are_you_sure_torrent'].'<a href=details.php?id='.htmlspecialchars($torrent).'><b>'.htmlspecialchars($arr['name']).'</b></a>'.$lang_report['text_to_staff'].'<br />'.$lang_report['text_reason_note'].'<br /><form method=post action=report.php><input type=hidden name=taketorrent value="'.htmlspecialchars($torrent).'">'.$lang_report['text_reason_is'].'<input type=text style="width: 200px" name=reason><input type=submit value="'.$lang_report['submit_confirm'].'"></form>', false);
}
// ////////TORRENT #2 END//////////

// ////////FORUM POST #2 START//////////
elseif (isset($forumpost)) {
    int_check($forumpost);
    $arr = NexusDB::table('topics')
        ->leftJoin('posts', 'posts.topicid', '=', 'topics.id')
        ->where('posts.id', (int) $forumpost)
        ->select(['topics.id AS topicid', 'topics.subject AS subject', 'posts.userid AS postuserid'])
        ->first();
    $arr = $arr ? (array) $arr : null;
    if (! $arr) {
        stderr($lang_report['std_error'], $lang_report['std_invalid_post_id']);
    }
    stderr($lang_report['std_are_you_sure'], $lang_report['text_are_you_sure_post'].$forumpost.$lang_report['text_of_topic'].'<a href="forums.php?action=viewtopic&topicid='.$arr['topicid'].'&page=p'.htmlspecialchars($forumpost).'#'.htmlspecialchars($forumpost).'"><b>'.htmlspecialchars($arr['subject']).'</b></a>'.$lang_report['text_by'].get_username($arr['postuserid']).$lang_report['text_to_staff'].'<br />'.$lang_report['text_reason_note'].'<br /><form method=post action=report.php><input type=hidden name=takeforumpost value="'.htmlspecialchars($forumpost).'">'.$lang_report['text_reason_is'].'<input type=text style="width: 200px" name=reason><input type=submit value="'.$lang_report['submit_confirm'].'"></form>', false);
}
// ////////FORUM POST #2 END//////////

// ////////COMMENT #2 START//////////
elseif (isset($commentid)) {
    int_check($commentid);
    $arr = NexusDB::table('comments')
        ->where('id', (int) $commentid)
        ->select(['id', 'user', 'torrent', 'request', 'offer'])
        ->first();
    $arr = $arr ? (array) $arr : null;
    if (! $arr) {
        stderr($lang_report['std_error'], $lang_report['std_invalid_comment_id']);
    }
    if ($arr['torrent']) { // Comment of torrent. BTW, this is shitty code!
        $name = NexusDB::table('torrents')->where('id', (int) $arr['torrent'])->value('name');
        $url = 'details.php?id='.$arr['torrent'].'#'.$commentid;
        $of = $lang_report['text_of_torrent'];
    } elseif ($arr['offer']) { // Comment of offer
        $name = NexusDB::table('offers')->where('id', (int) $arr['offer'])->value('name');
        $url = 'offers.php?id='.$arr['offer'].'&off_details=1#'.$commentid;
        $of = $lang_report['text_of_offer'];
    }
    else { // Comment belongs to no one
        stderr($lang_report['std_error'], $lang_report['std_orphaned_comment']);
    }

    stderr($lang_report['std_are_you_sure'], $lang_report['text_are_you_sure_comment'].$commentid.$of.'<a href="'.$url.'"><b>'.htmlspecialchars($name).'</b></a>'.$lang_report['text_by'].get_username($arr['user']).$lang_report['text_to_staff'].'<br />'.$lang_report['text_reason_note'].'<br /><form method=post action=report.php><input type=hidden name=takecommentid value="'.htmlspecialchars($commentid).'">'.$lang_report['text_reason_is'].'<input type=text style="width: 200px" name=reason><input type=submit value="'.$lang_report['submit_confirm'].'"></form>', false);
}
// ////////COMMENT #2 END//////////

// ////////OFFER #2 START//////////
elseif (isset($reportofferid)) {
    int_check($reportofferid);
    $arr = NexusDB::table('offers')
        ->where('id', (int) $reportofferid)
        ->select(['id', 'name'])
        ->first();
    $arr = $arr ? (array) $arr : null;
    if (! $arr) {
        stderr($lang_report['std_error'], $lang_report['std_invalid_offer_id']);
    }
    stderr($lang_report['std_are_you_sure'], $lang_report['text_are_you_sure_offer'].'<a href="offers.php?id='.$arr['id'].'&off_details=1"><b>'.htmlspecialchars($arr['name']).'</b></a>'.$lang_report['text_to_staff'].'<br />'.$lang_report['text_reason_note'].'<br /><form method=post action=report.php><input type=hidden name=takereportofferid value="'.htmlspecialchars($reportofferid).'">'.$lang_report['text_reason_is'].'<input type=text style="width: 200px" name=reason><input type=submit value="'.$lang_report['submit_confirm'].'"></form>', false);
}
// ////////OFFERT #2 END//////////

// ////////REQUEST #2 START//////////
elseif (isset($reportrequestid)) {
    int_check($reportrequestid);
    $arr = NexusDB::table('requests')
        ->where('id', (int) $reportrequestid)
        ->select(['id', 'request'])
        ->first();
    $arr = $arr ? (array) $arr : null;
    if (! $arr) {
        stderr($lang_report['std_error'], $lang_report['std_invalid_request_id']);
    }
    stderr($lang_report['std_are_you_sure'], $lang_report['text_are_you_sure_request'].'<a href="viewrequests.php?id='.$arr['id'].'&req_details=1"><b>'.htmlspecialchars($arr['request']).'</b></a>'.$lang_report['text_to_staff'].'<br />'.$lang_report['text_reason_note'].'<br /><form method=post action=report.php><input type=hidden name=takerequestid value="'.htmlspecialchars($reportrequestid).'">'.$lang_report['text_reason_is'].'<input type=text style="width: 200px" name=reason><input type=submit value="'.$lang_report['submit_confirm'].'"></form>', false);
}
// ////////REQUEST #2 END//////////

// ////////SUBTITLE #2 START//////////
elseif (isset($subtitle)) {
    int_check($subtitle);
    $arr = NexusDB::table('subs')
        ->where('id', (int) $subtitle)
        ->select(['id', 'torrent_id', 'title'])
        ->first();
    $arr = $arr ? (array) $arr : null;
    if (! $arr) {
        stderr($lang_report['std_error'], $lang_report['std_invalid_subtitle_id']);
    }
    stderr($lang_report['std_are_you_sure'], $lang_report['text_are_you_sure_subtitle'].'<a href="downloadsubs.php?torrentid='.$arr['torrent_id'].'&subid='.$arr['id'].'"><b>'.htmlspecialchars($arr['title']).'</b></a>'.$lang_report['text_to_staff'].'<br />'.$lang_report['text_reason_note'].'<br /><form method=post action=report.php><input type=hidden name=takesubtitleid value="'.htmlspecialchars($subtitle).'">'.$lang_report['text_reason_is'].'<input type=text style="width: 200px" name=reason><input type=submit value="'.$lang_report['submit_confirm'].'"></form>', false);
}
// ////////SUBTITLE #2 END//////////

else { // unknown action
    stderr($lang_report['std_error'],$lang_report['std_invalid_action']);
}

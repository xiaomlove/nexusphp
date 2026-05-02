<?php

use Nexus\Database\NexusDB;

require '../include/bittorrent.php';
dbconn();
require_once get_langfile_path();
loggedinorreturn();

parked();
$userid = intval($_GET['id'] ?? 0);
int_check($userid, true);

if ($CURUSER['id'] != $userid && ! user_can('viewhistory')) {
    permissiondenied();
}

$action = htmlspecialchars($_GET['action']);

// -------- Global variables

$perpage = 15;

// -------- Action: View posts

if ($action == 'viewposts') {
    $postcount = (int) NexusDB::table('posts AS p')
        ->leftJoin('topics AS t', 'p.topicid', '=', 't.id')
        ->leftJoin('forums AS f', 't.forumid', '=', 'f.id')
        ->where('p.userid', (int) $userid)
        ->where('f.minclassread', '<=', (int) $CURUSER['class'])
        ->distinct()
        ->count('p.id');

    if ($postcount == 0) {
        stderr($lang_userhistory['std_error'], $lang_userhistory['std_no_posts_found']);
    }

    // ------ Make page menu

    [$pagertop, $pagerbottom, $limit, $offsetStart, $rowsPerPage] = pager($perpage, $postcount, $_SERVER['PHP_SELF']."?action=viewposts&id=$userid&");

    // ------ Get user data

    $userRow = NexusDB::table('users')
        ->where('id', (int) $userid)
        ->select(['username', 'donor', 'warned', 'enabled'])
        ->first();

    if ($userRow) {
        $subject = get_username($userid);
    } else {
        $subject = "unknown[$userid]";
    }

    // ------ Get posts

    $rows = NexusDB::table('posts AS p')
        ->leftJoin('topics AS t', 'p.topicid', '=', 't.id')
        ->leftJoin('forums AS f', 't.forumid', '=', 'f.id')
        ->leftJoin('readposts AS r', function ($join) {
            $join->on('p.topicid', '=', 'r.topicid')->on('p.userid', '=', 'r.userid');
        })
        ->where('p.userid', (int) $userid)
        ->where('f.minclassread', '<=', (int) $CURUSER['class'])
        ->orderByDesc('p.id')
        ->offset((int) $offsetStart)
        ->limit((int) $rowsPerPage)
        ->selectRaw('f.id AS f_id, f.name, t.id AS t_id, t.subject, t.lastpost, r.lastpostread, p.*')
        ->get();

    if (count($rows) == 0) {
        stderr($lang_userhistory['std_error'], $lang_userhistory['std_no_posts_found']);
    }

    stdhead($lang_userhistory['head_posts_history']);

    echo '<h1>'.$lang_userhistory['text_posts_history_for'].$subject."</h1>\n";

    if ($postcount > $perpage) {
        echo $pagertop;
    }

    // ------ Print table

    begin_main_frame();

    begin_frame();

    foreach ($rows as $arr) {
        $arr = (array) $arr;
        $postid = $arr['id'];

        $posterid = $arr['userid'];

        $topicid = $arr['t_id'];

        $topicname = $arr['subject'];

        $forumid = $arr['f_id'];

        $forumname = $arr['name'];

        $newposts = ($arr['lastpostread'] < $arr['lastpost']) && $CURUSER['id'] == $userid;

        $added = gettime($arr['added'], true, false, false);

        echo "<p class=sub><table border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>
	    $added&nbsp;--&nbsp;".$lang_userhistory['text_forum'].
        "<a href=forums.php?action=viewforum&forumid=$forumid>$forumname</a>
	    &nbsp;--&nbsp;".$lang_userhistory['text_topic'].
        "<a href=forums.php?action=viewtopic&topicid=$topicid>$topicname</a>
      &nbsp;--&nbsp;".$lang_userhistory['text_post'].
      "<a href=forums.php?action=viewtopic&topicid=$topicid&page=p$postid#pid$postid>#$postid</a>".
      ($newposts ? ' &nbsp;<b>(<font class=new>'.$lang_userhistory['text_new'].'</font>)</b>' : '').
      "</td></tr></table></p>\n";

        echo '<br />';

        echo "<table class=main width=100% border=1 cellspacing=0 cellpadding=5>\n";

        $body = format_comment($arr['body']);

        if (is_valid_id($arr['editedby'])) {
            $subrow = NexusDB::table('users')
                ->where('id', (int) $arr['editedby'])
                ->select(['username'])
                ->first();
            if ($subrow) {
                $body .= '<p><font size=1 class=small>'.$lang_userhistory['text_last_edited'].get_username($arr['editedby']).$lang_userhistory['text_at']."$arr[editdate]</font></p>\n";
            }
        }

        echo "<tr valign=top><td class=comment>$body</td></tr>\n";

        echo "</td></tr></table>\n";
        echo '<br />';
    }

    end_frame();

    end_main_frame();

    if ($postcount > $perpage) {
        echo $pagerbottom;
    }

    stdfoot();

    exit;
}

// -------- Action: View comments

if ($action == 'viewcomments') {
    $commentcount = (int) NexusDB::table('comments AS c')
        ->leftJoin('torrents AS t', 'c.torrent', '=', 't.id')
        ->where('c.user', (int) $userid)
        ->count();

    if ($commentcount == 0) {
        stderr($lang_userhistory['std_error'], $lang_userhistory['std_no_comments_found']);
    }

    // ------ Make page menu

    [$pagertop, $pagerbottom, $limit, $offsetStart, $rowsPerPage] = pager($perpage, $commentcount, $_SERVER['PHP_SELF']."?action=viewcomments&id=$userid&");

    // ------ Get user data

    $userRow = NexusDB::table('users')
        ->where('id', (int) $userid)
        ->select(['username', 'donor', 'warned', 'enabled'])
        ->first();

    if ($userRow) {
        $subject = get_username($userid);
    } else {
        $subject = "unknown[$userid]";
    }

    // ------ Get comments

    $rows = NexusDB::table('comments AS c')
        ->leftJoin('torrents AS t', 'c.torrent', '=', 't.id')
        ->where('c.user', (int) $userid)
        ->orderByDesc('c.id')
        ->offset((int) $offsetStart)
        ->limit((int) $rowsPerPage)
        ->selectRaw('t.name, c.torrent AS t_id, c.id, c.added, c.text')
        ->get();

    if (count($rows) == 0) {
        stderr($lang_userhistory['std_error'], $lang_userhistory['std_no_comments_found']);
    }

    stdhead($lang_userhistory['head_comments_history']);

    echo '<h1>'.$lang_userhistory['text_comments_history_for']."$subject</h1>\n";

    if ($commentcount > $perpage) {
        echo $pagertop;
    }

    // ------ Print table

    begin_main_frame();

    begin_frame();

    foreach ($rows as $arr) {
        $arr = (array) $arr;

        $commentid = $arr['id'];

        $torrent = $arr['name'];

        // make sure the line doesn't wrap
        if (strlen($torrent) > 55) {
            $torrent = substr($torrent, 0, 52).'...';
        }

        $torrentid = $arr['t_id'];

        // find the page; this code should probably be in details.php instead

        $count = (int) NexusDB::table('comments')
            ->where('torrent', (int) $torrentid)
            ->where('id', '<', (int) $commentid)
            ->count();
        $comm_page = floor($count / 20);
        $page_url = $comm_page ? "&page=$comm_page" : '';

        $added = gettime($arr['added'], true, false, false);

        echo '<p class=sub><table border=0 cellspacing=0 cellpadding=0><tr><td class=embedded>'.
        "$added&nbsp;---&nbsp;".$lang_userhistory['text_torrent'].
        ($torrent ? ("<a href=details.php?id=$torrentid&tocomm=1&hit=1>$torrent</a>") : ' [Deleted] ').
        '&nbsp;---&nbsp;'.$lang_userhistory['text_comment']."</b>#<a href=details.php?id=$torrentid&tocomm=1&hit=1$page_url>$commentid</a>
	  </td></tr></table></p>\n";
        echo '<br />';

        echo "<table class=main width=100% border=1 cellspacing=0 cellpadding=5>\n";

        $body = format_comment($arr['text']);

        echo "<tr valign=top><td class=comment>$body</td></tr>\n";

        echo "</td></tr></table>\n";

        echo '<br />';
    }

    end_frame();

    end_main_frame();

    if ($commentcount > $perpage) {
        echo $pagerbottom;
    }

    stdfoot();

    exit;
}

// -------- Handle unknown action

if ($action != '') {
    stderr($lang_userhistory['std_history_error'], $lang_userhistory['std_unkown_action']);
}

// -------- Any other case

stderr($lang_userhistory['std_history_error'], $lang_userhistory['std_invalid_or_no_query']);

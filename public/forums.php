<?php

use App\Events\ForumPostAdded;
use App\Models\Message;
use App\Models\Post;
use App\Models\Topic;
use App\Models\User;
use Nexus\Database\NexusDB;

require '../include/bittorrent.php';
dbconn();
require_once get_langfile_path();
loggedinorreturn();
parked();
if ($enableextforum == 'yes') { // check whether internal forum is disabled
    permissiondenied();
}

// ------------- start: functions ------------------//
// print forum stats
function forum_stats()
{
    global $lang_forums, $Cache, $today_date;

    if (! $activeforumuser_num = $Cache->get_value('active_forum_user_count')) {
        $secs = 900;
        $dt = date('Y-m-d H:i:s', (TIMENOW - $secs));
        $activeforumuser_num = NexusDB::table('users')->where('forum_access', '>=', $dt)->count();
        $Cache->cache_value('active_forum_user_count', $activeforumuser_num, 300);
    }
    if ($activeforumuser_num) {
        $forumusers = $lang_forums['text_there'].is_or_are($activeforumuser_num).'<b>'.$activeforumuser_num.'</b>'.$lang_forums['text_online_user'].add_s($activeforumuser_num).$lang_forums['text_in_forum_now'];
    } else {
        $forumusers = $lang_forums['text_no_active_users'];
    }
    ?>
<h2 align="left"><?php echo $lang_forums['text_stats'] ?></h2>
<table width="100%"><tr><td class="text">
<?php
        if (! $postcount = $Cache->get_value('total_posts_count')) {
            $postcount = NexusDB::table('posts')->count();
            $Cache->cache_value('total_posts_count', $postcount, 96400);
        }
    if (! $topiccount = $Cache->get_value('total_topics_count')) {
        $topiccount = NexusDB::table('topics')->count();
        $Cache->cache_value('total_topics_count', $topiccount, 96500);
    }
    if (! $todaypostcount = $Cache->get_value('today_'.$today_date.'_posts_count')) {
        $todaypostcount = NexusDB::table('posts')->where('added', '>', date('Y-m-d'))->count();
        $Cache->cache_value('today_'.$today_date.'_posts_count', $todaypostcount, 700);
    }
    echo $lang_forums['text_our_members_have'].'<b>'.$postcount.'</b>'.$lang_forums['text_posts_in_topics'].'<b>'.$topiccount.'</b>'.$lang_forums['text_in_topics'].'<b><font class="new">'.$todaypostcount.'</font></b>'.$lang_forums['text_new_post'].add_s($todaypostcount).$lang_forums['text_posts_today'].'<br /><br />';
    echo $forumusers;
    ?>
</td></tr></table>
<?php
}

// set all topics as read
function catch_up()
{
    global $CURUSER, $Cache;

    if (! $CURUSER) {
        exit;
    }
    NexusDB::statement('DELETE FROM readposts WHERE userid = '.(int) $CURUSER['id']);
    $Cache->delete_value('user_'.$CURUSER['id'].'_last_read_post_list');
    $lastpostid = NexusDB::table('posts')->orderByDesc('id')->value('id');
    if ($lastpostid) {
        $CURUSER['last_catchup'] = $lastpostid;
        NexusDB::statement('UPDATE users SET last_catchup = '.(int) $lastpostid.' WHERE id = '.(int) $CURUSER['id']);
    }
}

// return image
function get_topic_image($status = 'read')
{
    global $lang_forums;
    switch ($status) {
        case 'read':
            return '<img class="unlocked" src="pic/trans.gif" alt="read" title="'.$lang_forums['title_read'].'" />';
            break;

        case 'unread':
            return '<img class="unlockednew" src="pic/trans.gif" alt="unread" title="'.$lang_forums['title_unread'].'" />';
            break;

        case 'locked':
            return '<img class="locked" src="pic/trans.gif" alt="locked" title="'.$lang_forums['title_locked'].'" />';
            break;

        case 'lockednew':
            return '<img class="lockednew" src="pic/trans.gif" alt="lockednew" title="'.$lang_forums['title_locked_new'].'" />';
            break;

    }
}

function highlight_topic($subject, $hlcolor = 0)
{
    $colorname = get_hl_color($hlcolor);
    if ($colorname) {
        $subject = '<b><font color="'.$colorname.'">'.$subject.'</font></b>';
    }

    return $subject;
}

function check_whether_exist($id, $place = 'forum')
{
    global $lang_forums;
    int_check($id, true);
    switch ($place) {
        case 'forum':

            $count = NexusDB::table('forums')->where('id', (int) $id)->count();
            if (! $count) {
                stderr($lang_forums['std_error'], $lang_forums['std_no_forum_id']);
            }
            break;

        case 'topic':

            $count = NexusDB::table('topics')->where('id', (int) $id)->count();
            if (! $count) {
                stderr($lang_forums['std_error'], $lang_forums['std_bad_topic_id']);
            }
            $forumid = NexusDB::table('topics')->where('id', (int) $id)->value('forumid');
            check_whether_exist($forumid, 'forum');
            break;

        case 'post':

            $count = NexusDB::table('posts')->where('id', (int) $id)->count();
            if (! $count) {
                stderr($lang_forums['std_error'], $lang_forums['std_no_post_id']);
            }
            $topicid = NexusDB::table('posts')->where('id', (int) $id)->value('topicid');
            check_whether_exist($topicid, 'topic');
            break;

    }
}

// update the last post of a topic
function update_topic_last_post($topicid)
{
    global $lang_forums;
    $rows = NexusDB::select('SELECT id FROM posts WHERE topicid = '.(int) $topicid.' ORDER BY id DESC LIMIT 1');
    if (empty($rows)) {
        exit($lang_forums['std_no_post_found']);
    }
    $postid = (int) $rows[0]['id'];
    NexusDB::statement('UPDATE topics SET lastpost = '.$postid.' WHERE id = '.(int) $topicid);
}

function get_forum_row($forumid = 0)
{
    global $Cache;
    if (! $forums = $Cache->get_value('forums_list')) {
        $forums = [];
        foreach (NexusDB::select('SELECT * FROM forums ORDER BY forid ASC, sort ASC') as $row2) {
            $forums[$row2['id']] = $row2;
        }
        $Cache->cache_value('forums_list', $forums, 86400);
    }
    if (! $forumid) {
        return $forums;
    } else {
        return $forums[$forumid];
    }
}
function get_last_read_post_id($topicid)
{
    global $CURUSER, $Cache;
    static $ret;
    if (! $ret && ! $ret = $Cache->get_value('user_'.$CURUSER['id'].'_last_read_post_list')) {
        $ret = [];
        $rows = NexusDB::select('SELECT * FROM readposts WHERE userid = '.(int) $CURUSER['id']);
        if (count($rows) != 0) {
            foreach ($rows as $row) {
                $ret[$row['topicid']] = $row['lastpostread'];
            }
            $Cache->cache_value('user_'.$CURUSER['id'].'_last_read_post_list', $ret, 900);
        } else {
            $Cache->cache_value('user_'.$CURUSER['id'].'_last_read_post_list', 'no record', 900);
        }
    }
    if ($ret != 'no record' && isset($ret[$topicid]) && $CURUSER['last_catchup'] < $ret[$topicid]) {
        return $ret[$topicid];
    } elseif ($CURUSER['last_catchup']) {
        return $CURUSER['last_catchup'];
    } else {
        return 0;
    }
}

// -------- Inserts a compose frame
function insert_compose_frame($id, $type = 'new')
{
    global $maxsubjectlength, $CURUSER;
    global $lang_forums;
    $hassubject = false;
    $subject = '';
    $body = '';
    echo "<form id=\"compose\" method=\"post\" name=\"compose\" action=\"?action=post\">\n";
    switch ($type) {
        case 'new':

            $forumname = NexusDB::table('forums')->where('id', (int) $id)->value('name');
            $title = $lang_forums['text_new_topic_in'].' <a href="'.htmlspecialchars('?action=viewforum&forumid='.$id).'">'.htmlspecialchars($forumname).'</a> '.$lang_forums['text_forum'];
            $hassubject = true;
            break;

        case 'reply':

            $topicname = NexusDB::table('topics')->where('id', (int) $id)->value('subject');
            $title = $lang_forums['text_reply_to_topic'].' <a href="'.htmlspecialchars('?action=viewtopic&topicid='.$id).'">'.htmlspecialchars($topicname).'</a> ';
            break;

        case 'quote':

            $topicid = NexusDB::table('posts')->where('id', (int) $id)->value('topicid');
            $topicname = NexusDB::table('topics')->where('id', (int) $topicid)->value('subject');
            $title = $lang_forums['text_reply_to_topic'].' <a href="'.htmlspecialchars('?action=viewtopic&topicid='.$topicid).'">'.htmlspecialchars($topicname).'</a> ';
            $rows = NexusDB::select('SELECT posts.body, users.username FROM posts LEFT JOIN users ON posts.userid = users.id WHERE posts.id = '.(int) $id);
            if (count($rows) != 1) {
                stderr($lang_forums['std_error'], $lang_forums['std_no_post_id']);
            }
            $arr = $rows[0];
            $body = '[quote='.htmlspecialchars($arr['username']).']'.htmlspecialchars(unesc($arr['body'])).'[/quote]';
            $postid = $id;
            $id = $topicid;
            $type = 'reply';
            echo '<input type="hidden" name="postid" value="'.$postid.'" />';
            break;

        case 'edit':

            $rows = NexusDB::select('SELECT topicid, body FROM posts WHERE id = '.(int) $id.' LIMIT 1');
            $row = $rows[0] ?? [];
            $topicid = $row['topicid'];
            $firstpost = NexusDB::table('posts')->where('topicid', (int) $topicid)->min('id');
            if ($firstpost == $id) {
                $subject = NexusDB::table('topics')->where('id', (int) $topicid)->value('subject');
                $hassubject = true;
            }
            $body = htmlspecialchars(unesc($row['body']));
            $title = $lang_forums['text_edit_post'];
            break;

        default:

            exit;

    }
    echo '<input type="hidden" name="id" value="'.$id.'" />';
    echo '<input type="hidden" name="type" value="'.$type.'" />';
    begin_compose($title, $type, $body, $hassubject, $subject);
    end_compose();
    echo '</form>';
}
// ------------- end: functions ------------------//
// ------------- start: Global variables ------------------//
$maxsubjectlength = 100;
$postsperpage = $CURUSER['postsperpage'];
if (! $postsperpage) {
    if (is_numeric($forumpostsperpage)) {
        $postsperpage = $forumpostsperpage;
    }// system-wide setting
    else {
        $postsperpage = 10;
    }
}
// get topics per page
$topicsperpage = $CURUSER['topicsperpage'];
if (! $topicsperpage) {
    if (is_numeric($forumtopicsperpage_main)) {
        $topicsperpage = $forumtopicsperpage_main;
    }// system-wide setting
    else {
        $topicsperpage = 20;
    }
}
$today_date = date('Y-m-d', TIMENOW);
// ------------- end: Global variables ------------------//

$action = htmlspecialchars(trim($_GET['action'] ?? ''));

// -------- Action: New topic
if ($action == 'newtopic') {
    $forumid = intval($_GET['forumid'] ?? 0);
    check_whether_exist($forumid, 'forum');
    stdhead($lang_forums['head_new_topic']);
    begin_main_frame();
    insert_compose_frame($forumid, 'new');
    end_main_frame();
    stdfoot();
    exit;
}
if ($action == 'quotepost') {
    $postid = intval($_GET['postid'] ?? 0);
    check_whether_exist($postid, 'post');
    if (! can_view_post($CURUSER['id'], $postid)) {
        permissiondenied();
    }
    stdhead($lang_forums['head_post_reply']);
    begin_main_frame();
    insert_compose_frame($postid, 'quote');
    end_main_frame();
    stdfoot();
    exit;
}

// -------- Action: Reply

if ($action == 'reply') {
    $topicid = intval($_GET['topicid'] ?? 0);
    check_whether_exist($topicid, 'topic');
    stdhead($lang_forums['head_post_reply']);
    begin_main_frame();
    insert_compose_frame($topicid, 'reply');
    end_main_frame();
    stdfoot();
    exit;
}

// -------- Action: Edit post

if ($action == 'editpost') {
    $postid = intval($_GET['postid'] ?? 0);
    check_whether_exist($postid, 'post');

    $rows = NexusDB::select('SELECT userid, topicid FROM posts WHERE id = '.(int) $postid);
    $arr = $rows[0] ?? [];

    $rows2 = NexusDB::select('SELECT locked FROM topics WHERE id = '.(int) ($arr['topicid'] ?? 0));
    $arr2 = $rows2[0] ?? [];
    $locked = ($arr2['locked'] == 'yes');

    $ismod = is_forum_moderator($postid, 'post');
    if (($CURUSER['id'] != $arr['userid'] || $locked) && ! user_can('postmanage') && ! $ismod) {
        permissiondenied();
    }

    stdhead($lang_forums['text_edit_post']);
    begin_main_frame();
    insert_compose_frame($postid, 'edit');
    end_main_frame();
    stdfoot();
    exit;
}

// -------- Action: Post
if ($action == 'post') {
    if ($CURUSER['forumpost'] == 'no') {
        stderr($lang_forums['std_sorry'], $lang_forums['std_unauthorized_to_post'], false);
        exit;
    }
    $id = $_POST['id'];
    $type = $_POST['type'];
    $subject = $_POST['subject'] ?? '';
    $body = trim($_POST['body']);
    $hassubject = false;
    switch ($type) {
        case 'new':

            check_whether_exist($id, 'forum');
            $forumid = $id;
            $hassubject = true;
            break;

        case 'reply':

            check_whether_exist($id, 'topic');
            $topicid = $id;
            $forumid = NexusDB::table('topics')->where('id', (int) $topicid)->value('forumid');
            $quotepostid = $_POST['postid'];
            break;

        case 'edit':

            check_whether_exist($id, 'post');
            $rows = NexusDB::select('SELECT topicid FROM posts WHERE id = '.(int) $id.' LIMIT 1');
            $row = $rows[0] ?? [];
            $topicid = $row['topicid'];
            $forumid = NexusDB::table('topics')->where('id', (int) $topicid)->value('forumid');
            $firstpost = NexusDB::table('posts')->where('topicid', (int) $topicid)->min('id');
            if ($firstpost == $id) {
                $hassubject = true;
            }
            break;

        default:

            exit;

    }

    if ($hassubject) {
        $subject = trim($subject);
        if (! $subject) {
            stderr($lang_forums['std_error'], $lang_forums['std_must_enter_subject']);
        }
        if (strlen($subject) > $maxsubjectlength) {
            stderr($lang_forums['std_error'], $lang_forums['std_subject_limited']);
        }
    }

    // ------ Make sure sure user has write access in forum
    $arr = get_forum_row($forumid) or exit($lang_forums['std_bad_forum_id']);

    if (
        get_user_class() < $arr['minclassread']
        || get_user_class() < $arr['minclasswrite']
        || ($type == 'new' && get_user_class() < $arr['minclasscreate'])
    ) {
        permissiondenied();
    }
    if ($body == '') {
        stderr($lang_forums['std_error'], $lang_forums['std_no_body_text']);
    }

    $userid = intval($CURUSER['id'] ?? 0);
    $date = date('Y-m-d H:i:s');

    if ($type != 'new') {
        // ---- Make sure topic is unlocked

        $rows = NexusDB::select('SELECT locked FROM topics WHERE id = '.(int) $topicid);
        $arr = $rows[0] ?? null;
        if (! $arr) {
            exit('Topic id n/a');
        }
        if ($arr['locked'] == 'yes' && ! user_can('postmanage') && ! is_forum_moderator($topicid, 'topic')) {
            stderr($lang_forums['std_error'], $lang_forums['std_topic_locked']);
        }
    }

    if ($type == 'edit') {
        $postid = $id;
        $topicInfo = Topic::query()->findOrFail($topicid);
        $postInfo = Post::query()->findOrFail($id);
        if ($postInfo->userid != $CURUSER['id'] && ! is_forum_moderator($postid, 'post') && ! user_can('postmanage')) {
            permissiondenied();
        }
        if ($hassubject) {
            NexusDB::table('topics')
                ->where('id', (int) $topicid)
                ->update(['subject' => (string) $subject]);
            $forum_last_replied_topic_row = $Cache->get_value('forum_'.$forumid.'_last_replied_topic_content');
            if ($forum_last_replied_topic_row && $forum_last_replied_topic_row['id'] == $topicid) {
                $Cache->delete_value('forum_'.$forumid.'_last_replied_topic_content');
            }
        }
        NexusDB::table('posts')
            ->where('id', (int) $id)
            ->update([
                'body' => (string) $body,
                'editdate' => (string) $date,
                'editedby' => (int) $CURUSER['id'],
            ]);
        $Cache->delete_value('post_'.$postid.'_content');
        // send pm
        $postUrl = sprintf('[url=forums.php?action=viewtopic&topicid=%s&page=p%s#pid%s]%s[/url]', $topicid, $id, $id, $topicInfo->subject);
        if (! empty($postInfo->userid) && $postInfo->userid != $CURUSER['id']) {
            $receiver = $postInfo->user;
            if ($receiver) {
                $locale = $receiver->locale;
                $notify = [
                    'sender' => 0,
                    'receiver' => $receiver->id,
                    'subject' => nexus_trans('forum.post.edited_notify_subject', [], $locale),
                    'msg' => nexus_trans('forum.post.edited_notify_body', ['topic_subject' => $postUrl, 'editor' => $CURUSER['username']], $locale),
                    'added' => now(),
                ];
                Message::add($notify);
            }
        }
    } else {
        // Anti Flood Code
        // To ensure that posts are not entered within 10 seconds limiting posts
        // to a maximum of 360*6 per hour.
        if (! user_can('postmanage')) {
            if (strtotime($CURUSER['last_post']) > (TIMENOW - 10)) {
                $secs = 10 - (TIMENOW - strtotime($CURUSER['last_post']));
                stderr($lang_forums['std_error'], $lang_forums['std_post_flooding'].$secs.$lang_forums['std_seconds_before_making'], false);
            }
        }
        if ($type == 'new') { // new topic
            // add bonus
            KPS('+', $starttopic_bonus, $userid);

            // ---- Create topic
            $topicid = (int) NexusDB::insert('topics', [
                'userid' => $userid,
                'forumid' => $forumid,
                'subject' => $subject,
            ]);
            if (! $topicid) {
                stderr($lang_forums['std_error'], $lang_forums['std_no_topic_id_returned']);
            }
            NexusDB::statement('UPDATE forums SET topiccount = topiccount + 1, postcount = postcount + 1 WHERE id = '.(int) $forumid);
        } else { // new post
            // add bonus
            KPS('+', $makepost_bonus, $userid);
            NexusDB::statement('UPDATE forums SET postcount = postcount + 1 WHERE id = '.(int) $forumid);
        }

        $postid = (int) NexusDB::insert('posts', [
            'topicid' => $topicid,
            'userid' => $userid,
            'added' => $date,
            'body' => $body,
            'ori_body' => $body,
        ]);
        if (! $postid) {
            exit($lang_forums['std_post_id_not_available']);
        }
        // send pm
        $topicInfo = Topic::query()->findOrFail($topicid);
        $postUrl = sprintf('[url=forums.php?action=viewtopic&topicid=%s&page=p%s#pid%s]%s[/url]', $topicid, $postid, $postid, $topicInfo->subject);

        if ($type == 'reply') {
            /** @var User $receiver */
            if (! empty($topicInfo->userid) && $topicInfo->userid != $CURUSER['id']) {
                $receiver = $topicInfo->user;
                if ($receiver && $receiver->acceptNotification('topic_reply')) {
                    $locale = $receiver->locale;
                    $notify = [
                        'sender' => 0,
                        'receiver' => $receiver->id,
                        'subject' => nexus_trans('forum.topic.replied_notify_subject', [], $locale),
                        'msg' => nexus_trans('forum.topic.replied_notify_body', ['topic_subject' => $postUrl], $locale),
                        'added' => now(),
                    ];
                    Message::add($notify);
                }
            }

            if (! empty($quotepostid)) {
                $quotePostInfo = Post::query()->find($quotepostid);
                if ($quotePostInfo && $quotePostInfo->userid != $CURUSER['id']) {
                    $receiver = $quotePostInfo->user;
                    if ($receiver && $receiver->acceptNotification('topic_reply')) {
                        $locale = $receiver->locale;
                        $notify = [
                            'sender' => 0,
                            'receiver' => $receiver->id,
                            'subject' => nexus_trans('forum.reply.replied_notify_subject', [], $locale),
                            'msg' => nexus_trans('forum.reply.replied_notify_body', ['topic_subject' => $postUrl, 'replyer' => $CURUSER['username']], $locale),
                            'added' => now(),
                        ];
                        Message::add($notify);
                    }
                }
            }
        }

        $Cache->delete_value('forum_'.$forumid.'_post_'.$today_date.'_count');
        $Cache->delete_value('today_'.$today_date.'_posts_count');
        $Cache->delete_value('forum_'.$forumid.'_last_replied_topic_content');
        $Cache->delete_value('topic_'.$topicid.'_post_count');
        $Cache->delete_value('user_'.$userid.'_post_count');

        if ($type == 'new') {
            // update the first post of topic
            NexusDB::statement('UPDATE topics SET firstpost = '.(int) $postid.', lastpost = '.(int) $postid.' WHERE id = '.(int) $topicid);
        } else {
            NexusDB::statement('UPDATE topics SET lastpost = '.(int) $postid.' WHERE id = '.(int) $topicid);
        }
        NexusDB::table('users')
            ->where('id', (int) $CURUSER['id'])
            ->update(['last_post' => (string) $date]);

        try {
            ForumPostAdded::dispatch(
                (int) $forumid,
                (int) $topicid,
                (int) $postid,
                (int) $CURUSER['id'],
                $type === 'new',
            );
        } catch (Throwable $e) {
            do_log('[forum] ForumPostAdded broadcast failed: '.$e->getMessage(), 'error');
        }
    }

    // ------ All done, redirect user to the post

    $headerstr = 'Location: '.get_protocol_prefix()."$BASEURL/forums.php?action=viewtopic&topicid=$topicid";

    if ($type == 'edit') {
        header($headerstr.'&page=p'.$postid.'#pid'.$postid);
    } else {
        header($headerstr."&page=last#pid$postid");
    }
    exit;
}

// -------- Action: View topic

if ($action == 'viewtopic') {
    $highlight = htmlspecialchars(trim($_GET['highlight'] ?? ''));

    $topicid = intval($_GET['topicid'] ?? 0);
    int_check($topicid, true);
    $page = $_GET['page'] ?? 0;
    $authorid = intval($_GET['authorid'] ?? 0);
    if ($authorid) {
        $where = 'WHERE topicid = '.(int) $topicid.' AND userid = '.(int) $authorid;
        $addparam = 'action=viewtopic&topicid='.$topicid.'&authorid='.$authorid;
    } else {
        $where = 'WHERE topicid = '.(int) $topicid;
        $addparam = 'action=viewtopic&topicid='.$topicid;
    }
    $userid = $CURUSER['id'];

    // ------ Get topic info

    $rows = NexusDB::select('SELECT * FROM topics WHERE id = '.(int) $topicid.' LIMIT 1');
    $arr = $rows[0] ?? null;
    if (! $arr) {
        stderr($lang_forums['std_forum_error'], $lang_forums['std_topic_not_found']);
    }

    $forumid = $arr['forumid'];
    $locked = $arr['locked'] == 'yes';
    $orgsubject = $arr['subject'];
    $subject = htmlspecialchars($arr['subject']);
    if ($highlight) {
        $subject = highlight($highlight, $orgsubject);
    }
    $sticky = $arr['sticky'] == 'yes';
    $hlcolor = $arr['hlcolor'];
    $views = $arr['views'];
    $forumid = $arr['forumid'];
    $base_posterid = $arr['userid'];

    $row = get_forum_row($forumid);
    // ------ Get forum name, moderators
    $forumname = $row['name'];
    $is_forummod = is_forum_moderator($forumid, 'forum');

    if (get_user_class() < $row['minclassread']) {
        stderr($lang_forums['std_error'], $lang_forums['std_unpermitted_viewing_topic']);
    }
    if (((get_user_class() >= $row['minclasswrite'] && ! $locked) || user_can('postmanage') || $is_forummod) && $CURUSER['forumpost'] == 'yes') {
        $maypost = true;
    } else {
        $maypost = false;
    }

    // ------ Update hits column
    NexusDB::statement('UPDATE topics SET views = views + 1 WHERE id = '.(int) $topicid);

    // ------ Get post count
    $postcount = (int) (NexusDB::select("SELECT COUNT(*) AS c FROM posts $where")[0]['c'] ?? 0);
    if (! $authorid) {
        $Cache->cache_value('topic_'.$topicid.'_post_count', $postcount, 3600);
    }

    // ------ Make page menu

    $pagerarr = [];

    $perpage = $postsperpage;

    $pages = ceil($postcount / $perpage);

    if (isset($page[0]) && $page[0] == 'p') {
        $findpost = substr($page, 1);
        $i = 0;
        foreach (NexusDB::select("SELECT id FROM posts $where ORDER BY added") as $arr) {
            if ($arr['id'] == $findpost) {
                break;
            }
            $i++;
        }
        $page = floor($i / $perpage);
    }
    if ($page === 'last') {
        $page = $pages - 1;
    } elseif (isset($page)) {
        if ($page < 0) {
            $page = 0;
        } elseif ($page > $pages - 1) {
            $page = $pages - 1;
        }
    } else {
        if ($CURUSER['clicktopic'] == 'firstpage') {
            $page = 0;
        } else {
            $page = $pages - 1;
        }
    }

    $offset = $page * $perpage;
    $dotted = 0;
    $dotspace = 3;
    $dotend = $pages - $dotspace;
    $curdotend = $page - $dotspace;
    $curdotstart = $page + $dotspace;
    for ($i = 0; $i < $pages; $i++) {
        if (($i >= $dotspace && $i <= $curdotend) || ($i >= $curdotstart && $i < $dotend)) {
            if (! $dotted) {
                $pagerarr[] = '...';
            }
            $dotted = 1;

            continue;
        }
        $dotted = 0;
        if ($i != $page) {
            $pagerarr[] = '<a href="'.htmlspecialchars('?'.$addparam.'&page='.$i).'"><b>'.($i + 1)."</b></a>\n";
        } else {
            $pagerarr[] = '<font class="gray"><b>'.($i + 1)."</b></font>\n";
        }
    }
    if ($page == 0) {
        $pager = '<font class="gray"><b>&lt;&lt;'.$lang_forums['text_prev'].'</b></font>';
    } else {
        $pager = '<a href="'.htmlspecialchars('?'.$addparam.'&page='.($page - 1)).
        '"><b>&lt;&lt;'.$lang_forums['text_prev'].'</b></a>';
    }
    $pager .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
    if ($page == $pages - 1) {
        $pager .= '<font class="gray"><b>'.$lang_forums['text_next']." &gt;&gt;</b></font>\n";
    } else {
        $pager .= '<a href="'.htmlspecialchars('?'.$addparam.'&page='.($page + 1)).
        '"><b>'.$lang_forums['text_next']." &gt;&gt;</b></a>\n";
    }

    $pagerstr = implode(' | ', $pagerarr);
    $pagertop = '<p align="center">'.$pager.'<br />'.$pagerstr."</p>\n";
    $pagerbottom = '<p align="center">'.$pagerstr.'<br />'.$pager."</p>\n";
    // ------ Get posts

    $postRows = NexusDB::select("SELECT * FROM posts $where ORDER BY id LIMIT $perpage offset $offset");

    stdhead($lang_forums['head_view_topic'].' "'.$orgsubject.'"');
    begin_main_frame('', true);

    echo '<h1 align="center"><a class="faqlink" href="forums.php">'.$SITENAME.'&nbsp;'.$lang_forums['text_forums'].'</a>--><a class="faqlink" href="'.htmlspecialchars('?action=viewforum&forumid='.$forumid).'">'.$forumname.'</a><b>--></b><span id="top">'.$subject.($locked ? '&nbsp;&nbsp;<b>[<font class="striking">'.$lang_forums['text_locked'].'</font>]</b>' : '')."</span></h1>\n";
    end_main_frame();
    echo $pagertop;

    // ------ Print table

    begin_main_frame();
    echo "<table border=\"0\" class=\"main\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\"><tr>\n";
    echo '<td class="embedded" width="99%">&nbsp;&nbsp;'.$lang_forums['there_is'].'<b>'.$views.'</b>'.$lang_forums['hits_on_this_topic'];
    echo "</td>\n";
    echo '<td class="embedded nowrap" width="1%" align="right">';
    if ($maypost) {
        echo '<a href="'.htmlspecialchars('?action=reply&topicid='.$topicid).'"><img class="f_reply" src="pic/trans.gif" alt="Add Reply" title="'.$lang_forums['title_reply_directly'].'" /></a>&nbsp;&nbsp;';
    }
    echo '</td>';
    echo "</tr></table>\n";
    begin_frame();

    $pc = count($postRows);
    $allPosts = $uidArr = [];
    foreach ($postRows as $arr) {
        $allPosts[] = $arr;
        $uidArr[$arr['userid']] = 1;
    }
    $uidArr = array_keys($uidArr);
    unset($arr);
    $neededColumns = ['id', 'noad', 'class', 'enabled', 'privacy', 'avatar', 'signature', 'uploaded', 'downloaded', 'last_access', 'username', 'donor', 'leechwarn', 'warned', 'title'];
    $userInfoArr = User::query()->find($uidArr, $neededColumns)->keyBy('id');
    $pn = 0;
    $lpr = get_last_read_post_id($topicid);
    if ($Advertisement->enable_ad()) {
        $forumpostad = $Advertisement->get_ad('forumpost');
    }

    // check if privacy protection enabled in this forum
    //	$protected_forums = Nexus\Database\NexusDB::remember("setting_protected_forum", 600, function () {
    //		return \App\Models\Setting::getByName('misc.protected_forum');
    //	});
    //
    //	if ($protected_forums and in_array(strval($forumid),explode(",",$protected_forums))){
    //		$protected_enabled=true;
    //	}else{
    //		$protected_enabled=false;
    //	}

    foreach ($allPosts as $arr) {
        if ($pn >= 1) {
            if ($Advertisement->enable_ad()) {
                if (! empty($forumpostad[$pn - 1])) {
                    echo '<div align="center" style="margin-top: 10px" id="">'.$forumpostad[$pn - 1].'</div>';
                }
            }
        }
        $pn++;

        $postid = $arr['id'];
        $posterid = $arr['userid'];

        $added = gettime($arr['added'], true, false);

        // ---- Get poster details

        //		$arr2 = get_user_row($posterid);
        $userInfo = $userInfoArr->get($posterid) ?: User::defaultUser();

        $arr2 = $userInfo->toArray();

        $uploaded = mksize($arr2['uploaded']);
        $downloaded = mksize($arr2['downloaded']);
        $ratio = get_ratio($arr2['id']);

        if (! $forumposts = $Cache->get_value('user_'.$posterid.'_post_count')) {
            $forumposts = NexusDB::table('posts')->where('userid', (int) $posterid)->count();
            $Cache->cache_value('user_'.$posterid.'_post_count', $forumposts, 3600);
        }

        $signature = ($CURUSER['signatures'] == 'yes' ? $arr2['signature'] : '');
        $avatar = ($CURUSER['avatars'] == 'yes' ? htmlspecialchars($arr2['avatar']) : '');

        $uclass = get_user_class_image($arr2['class']);
        $by = get_username($posterid, false, true, true, false, false, true);

        if (! $avatar) {
            $avatar = 'pic/default_avatar.png';
        }

        if ($pn == $pc) {
            echo "<span id=\"last\"></span>\n";
            if ($postid > $lpr) {
                if ($lpr == $CURUSER['last_catchup']) { // There is no record of this topic
                    NexusDB::insert('readposts', [
                        'userid' => $userid,
                        'topicid' => $topicid,
                        'lastpostread' => $postid,
                    ]);
                } elseif ($lpr > $CURUSER['last_catchup']) { // There is record of this topic
                    NexusDB::statement('UPDATE readposts SET lastpostread = '.(int) $postid.' WHERE userid = '.(int) $userid.' AND topicid = '.(int) $topicid);
                }
                $Cache->delete_value('user_'.$CURUSER['id'].'_last_read_post_list');
            }
        }

        echo '<div style="margin-top: 8pt; margin-bottom: 8pt;"><table id="pid'.$postid.'" border="0" cellspacing="0" cellpadding="0" width="100%"><tr><td class="embedded" width="99%"><a href="'.htmlspecialchars('forums.php?action=viewtopic&topicid='.$topicid.'&page=p'.$postid.'#pid'.$postid).'">#'.$postid.'</a>&nbsp;&nbsp;<font color="gray">'.$lang_forums['text_by'].'</font>'.$by.'&nbsp;&nbsp;<font color="gray">'.$lang_forums['text_at'].'</font>'.$added;
        if (is_valid_id($arr['editedby'])) {
            echo '';
        }
        echo '&nbsp;&nbsp;<font color="gray">|</font>&nbsp;&nbsp;';
        if ($authorid) {
            echo '<a href="?action=viewtopic&topicid='.$topicid.'">'.$lang_forums['text_view_all_posts'].'</a>';
        } else {
            echo '<a href="'.htmlspecialchars('?action=viewtopic&topicid='.$topicid.'&authorid='.$posterid).'">'.$lang_forums['text_view_this_author_only'].'</a>';
        }
        echo '</td><td class="embedded nowrap" width="1%"><font class="big">'.$lang_forums['text_number'].'<b>'.($pn + $offset).'</b>'.$lang_forums['text_lou'].'&nbsp;&nbsp;</font><a href="#top"><img class="top" src="pic/trans.gif" alt="Top" title="'.$lang_forums['text_back_to_top'].'" /></a>&nbsp;&nbsp;</td></tr>';

        echo "</table></div>\n";

        echo "<table class=\"main\" width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n";

        $body = '<div id="pid'.$postid.'body" style="word-break: break-all;">';
        // hidden content applied to second or higher floor post (for whose user class below Ad , not poster , not mods ,not reply's author)
        //		if ($protected_enabled && $pn+$offset>1 && get_user_class()<UC_ADMINISTRATOR && $userid != $base_posterid && $posterid!=$userid && !$is_forummod){
        if ($pn + $offset > 1 && ! can_view_post($userid, $arr)) {
            // enable content protection
            $bodyContent = format_comment($lang_forums['text_post_protected']);
            $canViewProtected = false;
        } else {
            // display normal content
            $bodyContent = format_comment($arr['body']);
            $canViewProtected = true;
        }
        if ($highlight) {
            $bodyContent = highlight($highlight, $bodyContent);
        }

        if (is_valid_id($arr['editedby'])) {
            $lastedittime = gettime($arr['editdate'], true, false);
            $bodyContent .= '<br /><p><font class="small">'.$lang_forums['text_last_edited_by'].get_username($arr['editedby']).$lang_forums['text_last_edit_at'].$lastedittime."</font></p>\n";
        }
        $bodyContent = apply_filter('post_body', $bodyContent, $arr, $allPosts);
        $body .= $bodyContent.'</div>';
        if ($signature) {
            $body .= "<p style='vertical-align:bottom'><br />____________________<br />".format_comment($signature, false, false, false, true, 500, true, false, 1, 200).'</p>';
        }

        $stats = '<br />'.'&nbsp;&nbsp;'.$lang_forums['text_posts']."$forumposts<br />".'&nbsp;&nbsp;'.$lang_forums['text_ul']."$uploaded <br />".'&nbsp;&nbsp;'.$lang_forums['text_dl']."$downloaded<br />".'&nbsp;&nbsp;'.$lang_forums['text_ratio']."$ratio";
        echo "<tr><td class=\"rowfollow\" width=\"150\" valign=\"top\" align=\"left\" style='padding: 0px'>".
        return_avatar_image($avatar).'<br /><br /><br />&nbsp;&nbsp;<img alt="'.get_user_class_name($arr2['class'], false, false, true).'" title="'.get_user_class_name($arr2['class'], false, false, true).'" src="'.$uclass.'" />'.$stats.'</td><td class="rowfollow" valign="top"><br />'.$body."</td></tr>\n";
        $secs = 900;
        $dt = "'".date('Y-m-d H:i:s', (TIMENOW - $secs))."'"; // calculate date.
        echo '<tr><td class="rowfollow" align="center" valign="middle">'.("'".$arr2['last_access']."'" > $dt ? '<img class="f_online" src="pic/trans.gif" alt="Online" title="'.$lang_forums['title_online'].'" />' : '<img class="f_offline" src="pic/trans.gif" alt="Offline" title="'.$lang_forums['title_offline'].'" />').'<a href="sendmessage.php?receiver='.htmlspecialchars(trim($arr2['id'])).'"><img class="f_pm" src="pic/trans.gif" alt="PM" title="'.$lang_forums['title_send_message_to'].htmlspecialchars($arr2['username'])."\" /></a><a href=\"report.php?forumpost=$postid\"><img class=\"f_report\" src=\"pic/trans.gif\" alt=\"Report\" title=\"".$lang_forums['title_report_this_post'].'" /></a></td>';
        echo '<td class="toolbox" align="right">';

        do_action('post_toolbox', $arr, $allPosts, $CURUSER['id']);

        if ($maypost && $canViewProtected) {
            echo '<a href="'.htmlspecialchars('?action=quotepost&postid='.$postid).'"><img class="f_quote" src="pic/trans.gif" alt="Quote" title="'.$lang_forums['title_reply_with_quote'].'" /></a>';
        }

        if (user_can('postmanage') || $is_forummod) {
            echo '<a href="'.htmlspecialchars('?action=deletepost&postid='.$postid).'"><img class="f_delete" src="pic/trans.gif" alt="Delete" title="'.$lang_forums['title_delete_post'].'" /></a>';
        }

        if (($CURUSER['id'] == $posterid && ! $locked) || user_can('postmanage') || $is_forummod) {
            echo '<a href="'.htmlspecialchars('?action=editpost&postid='.$postid).'"><img class="f_edit" src="pic/trans.gif" alt="Edit" title="'.$lang_forums['title_edit_post'].'" /></a>';
        }
        echo '</td></tr></table>';
    }

    // ------ Mod options

    if (user_can('postmanage') || $is_forummod) {
        echo "</td></tr><tr><td class=\"toolbox\" align=\"center\">\n";
        echo "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\" align=\"left\">\n";
        echo "<tr><td class=\"embedded\"><form method=\"post\" action=\"?action=setsticky\">\n";
        echo '<input type="hidden" name="topicid" value="'.$topicid."\" />\n";
        echo '<input type="hidden" name="returnto" value="'.htmlspecialchars($_SERVER['REQUEST_URI'])."\" />\n";
        echo '<input type="hidden" name="sticky" value="'.($sticky ? 'no' : 'yes').'" /><input type="submit" class="medium" value="'.($sticky ? $lang_forums['submit_unsticky'] : $lang_forums['submit_sticky'])."\" /></form></td>\n";
        echo "<td class=\"embedded\"><form method=\"post\" action=\"?action=setlocked\">\n";
        echo '<input type="hidden" name="topicid" value="'.$topicid."\" />\n";
        echo '<input type="hidden" name="returnto" value="'.htmlspecialchars($_SERVER['REQUEST_URI'])."\" />\n";
        echo '<input type="hidden" name="locked" value="'.($locked ? 'no' : 'yes').'" /><input type="submit" class="medium" value="'.($locked ? $lang_forums['submit_unlock'] : $lang_forums['submit_lock'])."\" /></form></td>\n";
        echo "<td class=\"embedded\"><form method=\"get\" action=\"?\">\n";
        echo "<input type=\"hidden\" name=\"action\" value=\"deletetopic\" />\n";
        echo '<input type="hidden" name="topicid" value="'.$topicid."\" />\n";
        echo '<input type="hidden" name="forumid" value="'.$forumid."\" />\n";
        echo '<input type="submit" class="medium" value="'.$lang_forums['submit_delete_topic']."\" /></form></td>\n";
        echo '<td class="embedded"><form method="post" action="'.htmlspecialchars('?action=movetopic&topicid='.$topicid)."\">\n".'&nbsp;'.$lang_forums['text_move_thread_to'].'&nbsp;<select class="med" name="forumid">';
        $forums = get_forum_row();
        foreach ($forums as $arr) {
            if ($arr['id'] != $forumid && get_user_class() >= $arr['minclasswrite']) {
                echo '<option value="'.$arr['id'].'">'.htmlspecialchars($arr['name'])."</option>\n";
            }
        }
        echo '</select> <input type="submit" class="medium" value="'.$lang_forums['submit_move'].'" /></form></td>';
        echo '<td class="embedded"><form method="post" action="'.htmlspecialchars('?action=hltopic&topicid='.$topicid)."\">\n".'&nbsp;'.$lang_forums['text_highlight_topic'].'&nbsp;<select class="med" name="color">';
        echo "<option value='0'>".$lang_forums['select_color']."</option>
<option style='background-color: black' value=\"1\">Black</option>
<option style='background-color: sienna' value=\"2\">Sienna</option>
<option style='background-color: darkolivegreen' value=\"3\">Dark Olive Green</option>
<option style='background-color: darkgreen' value=\"4\">Dark Green</option>
<option style='background-color: darkslateblue' value=\"5\">Dark Slate Blue</option>
<option style='background-color: navy' value=\"6\">Navy</option>
<option style='background-color: indigo' value=\"7\">Indigo</option>
<option style='background-color: darkslategray' value=\"8\">Dark Slate Gray</option>
<option style='background-color: darkred' value=\"9\">Dark Red</option>
<option style='background-color: darkorange' value=\"10\">Dark Orange</option>
<option style='background-color: olive' value=\"11\">Olive</option>
<option style='background-color: green' value=\"12\">Green</option>
<option style='background-color: teal' value=\"13\">Teal</option>
<option style='background-color: blue' value=\"14\">Blue</option>
<option style='background-color: slategray' value=\"15\">Slate Gray</option>
<option style='background-color: dimgray' value=\"16\">Dim Gray</option>
<option style='background-color: red' value=\"17\">Red</option>
<option style='background-color: sandybrown' value=\"18\">Sandy Brown</option>
<option style='background-color: yellowgreen' value=\"19\">Yellow Green</option>
<option style='background-color: seagreen' value=\"20\">Sea Green</option>
<option style='background-color: mediumturquoise' value=\"21\">Medium Turquoise</option>
<option style='background-color: royalblue' value=\"22\">Royal Blue</option>
<option style='background-color: purple' value=\"23\">Purple</option>
<option style='background-color: gray' value=\"24\">Gray</option>
<option style='background-color: magenta' value=\"25\">Magenta</option>
<option style='background-color: orange' value=\"26\">Orange</option>
<option style='background-color: yellow' value=\"27\">Yellow</option>
<option style='background-color: lime' value=\"28\">Lime</option>
<option style='background-color: cyan' value=\"29\">Cyan</option>
<option style='background-color: deepskyblue' value=\"30\">Deep Sky Blue</option>
<option style='background-color: darkorchid' value=\"31\">Dark Orchid</option>
<option style='background-color: silver' value=\"32\">Silver</option>
<option style='background-color: pink' value=\"33\">Pink</option>
<option style='background-color: wheat' value=\"34\">Wheat</option>
<option style='background-color: lemonchiffon' value=\"35\">Lemon Chiffon</option>
<option style='background-color: palegreen' value=\"36\">Pale Green</option>
<option style='background-color: paleturquoise' value=\"37\">Pale Turquoise</option>
<option style='background-color: lightblue' value=\"38\">Light Blue</option>
<option style='background-color: plum' value=\"39\">Plum</option>
<option style='background-color: white' value=\"40\">White</option>";
        echo '</select>';
        echo '<input type="hidden" name="returnto" value="'.htmlspecialchars($_SERVER['REQUEST_URI'])."\" />\n";
        echo '<input type="submit" class="medium" value="'.$lang_forums['submit_change'].'" /></form></td>';
        echo "</tr>\n";
        echo "</table>\n";
    }

    end_frame();

    end_main_frame();

    echo $pagerbottom;
    if ($maypost) {
        echo "<br /><table style='border:1px solid #000000;'><tr>".
'<td class="text" align="center"><b>'.$lang_forums['text_quick_reply'].'</b><br /><br />'.
'<form id="compose" name="compose" method="post" action="?action=post" onsubmit="return postvalid(this);">'.
'<input type="hidden" name="id" value="'.$topicid.'" /><input type="hidden" name="type" value="reply" /><br />';
        quickreply('compose', 'body', $lang_forums['submit_add_reply']);
        echo '</form></td></tr></table>';
        echo '<p align="center"><a class="index" href="'.htmlspecialchars('?action=reply&topicid='.$topicid).'">'.$lang_forums['text_add_reply']."</a></p>\n";
    } elseif ($locked) {
        echo $lang_forums['text_topic_locked_new_denied'];
    } else {
        echo $lang_forums['text_unpermitted_posting_here'];
    }

    echo key_shortcut($page, $pages - 1);
    stdfoot();
    exit;
}

// -------- Action: Move topic

if ($action == 'movetopic') {
    $forumid = intval($_POST['forumid'] ?? 0);

    $topicid = intval($_GET['topicid'] ?? 0);
    $ismod = is_forum_moderator($topicid, 'topic');
    if (! is_valid_id($forumid) || ! is_valid_id($topicid) || (! user_can('postmanage') && ! $ismod)) {
        permissiondenied();
    }

    // Make sure topic and forum is valid

    $rows = NexusDB::select('SELECT minclasswrite FROM forums WHERE id = '.(int) $forumid);

    if (count($rows) != 1) {
        stderr($lang_forums['std_error'], $lang_forums['std_forum_not_found']);
    }

    $arr = $rows[0];

    if (get_user_class() < $arr['minclasswrite']) {
        permissiondenied();
    }

    $rows = NexusDB::select('SELECT forumid FROM topics WHERE id = '.(int) $topicid);
    if (count($rows) != 1) {
        stderr($lang_forums['std_error'], $lang_forums['std_topic_not_found']);
    }
    $old_forumid = (int) $rows[0]['forumid'];

    // get posts count
    $rows = NexusDB::select('SELECT COUNT(id) AS nb_posts FROM posts WHERE topicid = '.(int) $topicid);
    if (count($rows) != 1) {
        stderr($lang_forums['std_error'], $lang_forums['std_cannot_get_posts_count']);
    }
    $nb_posts = (int) $rows[0]['nb_posts'];

    // move topic
    if ($old_forumid != $forumid) {
        NexusDB::statement('UPDATE topics SET forumid = '.(int) $forumid.' WHERE id = '.(int) $topicid);
        // update counts
        NexusDB::statement('UPDATE forums SET topiccount = topiccount - 1, postcount = postcount - '.(int) $nb_posts.' WHERE id = '.(int) $old_forumid);
        $Cache->delete_value('forum_'.$old_forumid.'_post_'.$today_date.'_count');
        $Cache->delete_value('forum_'.$old_forumid.'_last_replied_topic_content');
        NexusDB::statement('UPDATE forums SET topiccount = topiccount + 1, postcount = postcount + '.(int) $nb_posts.' WHERE id = '.(int) $forumid);
        $Cache->delete_value('forum_'.$forumid.'_post_'.$today_date.'_count');
        $Cache->delete_value('forum_'.$forumid.'_last_replied_topic_content');
    }

    // Redirect to forum page

    header('Location: '.get_protocol_prefix()."$BASEURL/forums.php?action=viewforum&forumid=$forumid");

    exit;
}

// -------- Action: Delete topic

if ($action == 'deletetopic') {
    $topicid = intval($_GET['topicid'] ?? 0);
    $rows1 = NexusDB::select('SELECT forumid, userid FROM topics WHERE id = '.(int) $topicid.' LIMIT 1');
    $row1 = $rows1[0] ?? null;
    if (! $row1) {
        exit;
    } else {
        $forumid = $row1['forumid'];
        $userid = $row1['userid'];
    }
    $ismod = is_forum_moderator($topicid, 'topic');
    if (! is_valid_id($topicid) || (! user_can('postmanage') && ! $ismod)) {
        permissiondenied();
    }

    $sure = intval($_GET['sure'] ?? 0);
    if (! $sure) {
        stderr($lang_forums['std_delete_topic'], $lang_forums['std_delete_topic_note'].
        "<a class=altlink href=?action=deletetopic&topicid=$topicid&sure=1>".$lang_forums['std_here_if_sure'], false);
    }

    $postcount = NexusDB::table('posts')->where('topicid', (int) $topicid)->count();

    NexusDB::statement('DELETE FROM topics WHERE id = '.(int) $topicid);
    NexusDB::statement('DELETE FROM posts WHERE topicid = '.(int) $topicid);
    NexusDB::statement('DELETE FROM readposts WHERE topicid = '.(int) $topicid);
    NexusDB::statement('UPDATE forums SET topiccount = topiccount - 1, postcount = postcount - '.(int) $postcount.' WHERE id = '.(int) $forumid);
    $Cache->delete_value('forum_'.$forumid.'_post_'.$today_date.'_count');
    $forum_last_replied_topic_row = $Cache->get_value('forum_'.$forumid.'_last_replied_topic_content');
    if ($forum_last_replied_topic_row && $forum_last_replied_topic_row['id'] == $topicid) {
        $Cache->delete_value('forum_'.$forumid.'_last_replied_topic_content');
    }

    // ===remove karma
    KPS('-', $starttopic_bonus, $userid);
    // ===end

    header('Location: '.get_protocol_prefix()."$BASEURL/forums.php?action=viewforum&forumid=$forumid");
    exit;
}

// -------- Action: Delete post

if ($action == 'deletepost') {
    $postid = intval($_GET['postid'] ?? 0);
    $sure = intval($_GET['sure'] ?? 0);

    $ismod = is_forum_moderator($postid, 'post');
    if ((! user_can('postmanage') && ! $ismod) || ! is_valid_id($postid)) {
        permissiondenied();
    }

    // ------- Get topic id
    $rows = NexusDB::select('SELECT topicid, userid FROM posts WHERE id = '.(int) $postid);
    $arr = $rows[0] ?? null;
    if (! $arr) {
        stderr($lang_forums['std_error'], $lang_forums['std_post_not_found']);
    }
    $topicid = $arr['topicid'];
    $userid = $arr['userid'];

    // ------- Get the id of the last post before the one we're deleting
    $rows = NexusDB::select('SELECT id FROM posts WHERE topicid = '.(int) $topicid.' AND id < '.(int) $postid.' ORDER BY id DESC LIMIT 1');
    if (count($rows) == 0) { // This is the first post of a topic
        stderr($lang_forums['std_error'], $lang_forums['std_cannot_delete_post'].
    "<a class=altlink href=?action=deletetopic&topicid=$topicid&sure=1>".$lang_forums['std_delete_topic_instead'], false);
    } else {
        $prevPostId = (int) $rows[0]['id'];
        $redirtopost = '&page=p'.$prevPostId.'#pid'.$prevPostId;
    }

    // ------- Make sure we know what we do :-)
    if (! $sure) {
        stderr($lang_forums['std_delete_post'], $lang_forums['std_delete_post_note'].
        "<a class=altlink href=?action=deletepost&postid=$postid&sure=1>".$lang_forums['std_here_if_sure'], false);
    }

    // ------- Delete post
    NexusDB::statement('DELETE FROM posts WHERE id = '.(int) $postid);
    $Cache->delete_value('user_'.$userid.'_post_count');
    $Cache->delete_value('topic_'.$topicid.'_post_count');
    // update forum
    $forumid = NexusDB::table('topics')->where('id', (int) $topicid)->value('forumid');
    if (! $forumid) {
        exit();
    } else {
        NexusDB::statement('UPDATE forums SET postcount = postcount - 1 WHERE id = '.(int) $forumid);
    }
    $forum_last_replied_topic_row = $Cache->get_value('forum_'.$forumid.'_last_replied_topic_content');
    if ($forum_last_replied_topic_row && $forum_last_replied_topic_row['lastpost'] == $postid) {
        $Cache->delete_value('forum_'.$forumid.'_last_replied_topic_content');
    }
    // ------- Update topic
    update_topic_last_post($topicid);

    // ===remove karma
    KPS('-', $makepost_bonus, $userid);

    header('Location: '.get_protocol_prefix()."$BASEURL/forums.php?action=viewtopic&topicid=$topicid$redirtopost");
    exit;
}

// -------- Action: Set locked on/off

if ($action == 'setlocked') {
    $topicid = intval($_POST['topicid'] ?? 0);
    $ismod = is_forum_moderator($topicid, 'topic');
    if (! $topicid || (! user_can('postmanage') && ! $ismod)) {
        permissiondenied();
    }

    $locked = $_POST['locked'] === 'yes' ? 'yes' : 'no';
    NexusDB::table('topics')
        ->where('id', (int) $topicid)
        ->update(['locked' => $locked]);

    header("Location: $_POST[returnto]");
    exit;
}

if ($action == 'hltopic') {
    $topicid = intval($_GET['topicid'] ?? 0);
    $ismod = is_forum_moderator($topicid, 'topic');
    if (! $topicid || (! user_can('postmanage') && ! $ismod)) {
        permissiondenied();
    }
    $color = $_POST['color'];
    if ($color == 0 || get_hl_color($color)) {
        NexusDB::statement('UPDATE topics SET hlcolor = '.(int) $color.' WHERE id = '.(int) $topicid);
    }

    $forumid = NexusDB::table('topics')->where('id', (int) $topicid)->value('forumid');
    $forum_last_replied_topic_row = $Cache->get_value('forum_'.$forumid.'_last_replied_topic_content');
    if ($forum_last_replied_topic_row && $forum_last_replied_topic_row['id'] == $topicid) {
        $Cache->delete_value('forum_'.$forumid.'_last_replied_topic_content');
    }
    header("Location: $_POST[returnto]");
    exit;
}

// -------- Action: Set sticky on/off

if ($action == 'setsticky') {
    $topicid = intval($_POST['topicid'] ?? 0);
    $ismod = is_forum_moderator($topicid, 'topic');
    if (! $topicid || (! user_can('postmanage') && ! $ismod)) {
        permissiondenied();
    }

    $sticky = $_POST['sticky'] === 'yes' ? 'yes' : 'no';
    NexusDB::table('topics')
        ->where('id', (int) $topicid)
        ->update(['sticky' => $sticky]);

    header("Location: $_POST[returnto]");
    exit;
}

// -------- Action: View forum

if ($action == 'viewforum') {
    $forumid = intval($_GET['forumid'] ?? 0);
    int_check($forumid, true);
    $userid = intval($CURUSER['id'] ?? 0);
    // ------ Get forum name, moderators
    $row = get_forum_row($forumid);
    if (! $row) {
        write_log('User '.$CURUSER['username'].','.$CURUSER['ip']." is trying to visit forum that doesn't exist", 'mod');
        stderr($lang_forums['std_forum_error'], $lang_forums['std_forum_not_found']);
    }
    if (get_user_class() < $row['minclassread']) {
        permissiondenied();
    }

    $forumname = $row['name'];
    $forummoderators = get_forum_moderators($forumid, false);
    $search = NexusDB::getInstance()->escapeString(trim($_GET['search'] ?? ''));
    if ($search) {
        $wherea = " AND subject LIKE '%$search%'";
        $addparam .= '&search='.rawurlencode($search);
    } else {
        $wherea = '';
        $addparam = '';
    }
    $num = (int) (NexusDB::select('SELECT COUNT(*) AS c FROM topics WHERE forumid = '.(int) $forumid.$wherea)[0]['c'] ?? 0);

    [$pagertop, $pagerbottom, $limit] = pager($topicsperpage, $num, '?'.'action=viewforum&forumid='.$forumid.$addparam.'&');
    if (isset($_GET['sort'])) {
        switch ($_GET['sort']) {
            case 'firstpostasc':

                $orderby = 'firstpost ASC';
                break;

            case 'firstpostdesc':

                $orderby = 'firstpost DESC';
                break;

            case 'lastpostasc':

                $orderby = 'lastpost ASC';
                break;

            case 'lastpostdesc':

                $orderby = 'lastpost DESC';
                break;

            default:

                $orderby = 'lastpost DESC';

        }
    } else {
        $orderby = 'lastpost DESC';
    }
    // ------ Get topics data
    $topicRows = NexusDB::select('SELECT * FROM topics WHERE forumid = '.(int) $forumid.$wherea.' ORDER BY sticky DESC,'.$orderby.' '.$limit);
    $numtopics = count($topicRows);
    stdhead($lang_forums['head_forum'].' '.$forumname);
    begin_main_frame('', true);
    echo '<h1 align="center"><a class="faqlink" href="forums.php">'.$SITENAME.'&nbsp;'.$lang_forums['text_forums'].'</a>--><a class="faqlink" href="'.htmlspecialchars('forums.php?action=viewforum&forumid='.$forumid).'">'.$forumname."</a></h1>\n";
    end_main_frame();
    echo '<br />';
    $maypost = get_user_class() >= $row['minclasswrite'] && get_user_class() >= $row['minclasscreate'] && $CURUSER['forumpost'] == 'yes';

    if (! $maypost) {
        echo '<p><i>'.$lang_forums['text_unpermitted_starting_new_topics']."</i></p>\n";
    }

    echo "<table border=\"0\" class=\"main\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\"><tr>\n";
    echo '<td class="embedded" width="90%">';
    echo $forummoderators ? '&nbsp;&nbsp;<img class="forum_mod" src="pic/trans.gif" alt="Moderator" title="'.$lang_forums['col_moderator'].'">&nbsp;'.$forummoderators : '';
    echo '</td><td class="embedded nowrap" width="1%">';
    if ($maypost) {
        echo '<a href="'.htmlspecialchars('?action=newtopic&forumid='.$forumid).'"><img class="f_new" src="pic/trans.gif" alt="New Topic" title="'.$lang_forums['title_new_topic'].'" /></a>&nbsp;&nbsp;';
    }
    echo '</td>';
    echo "</tr></table>\n";
    if ($numtopics > 0) {
        echo '<table border="1" cellspacing="0" cellpadding="5" width="97%">';

        echo '<tr><td class="colhead" align="center" width="99%">'.$lang_forums['col_topic'].'</td><td class="colhead" align="center"><a href="'.htmlspecialchars('?action=viewforum&forumid='.$forumid.$addparam.'&sort='.(isset($_GET['sort']) && $_GET['sort'] == 'firstpostdesc' ? 'firstpostasc' : 'firstpostdesc')).'" title="'.(isset($_GET['sort']) && $_GET['sort'] == 'firstpostdesc' ? $lang_forums['title_order_topic_asc'] : $lang_forums['title_order_topic_desc']).'">'.$lang_forums['col_author'].'</a></td><td class="colhead" align="center">'.$lang_forums['col_replies'].'/'.$lang_forums['col_views'].'</td><td class="colhead" align="center"><a href="'.htmlspecialchars('?action=viewforum&forumid='.$forumid.$addparam.'&sort='.(isset($_GET['sort']) && $_GET['sort'] == 'lastpostasc' ? 'lastpostdesc' : 'lastpostasc')).'" title="'.(isset($_GET['sort']) && $_GET['sort'] == 'lastpostasc' ? $lang_forums['title_order_post_desc'] : $lang_forums['title_order_post_asc']).'">'.$lang_forums['col_last_post']."</a></td>\n";

        echo "</tr>\n";
        $counter = 0;

        foreach ($topicRows as $topicarr) {
            $topicid = $topicarr['id'];

            $topic_userid = $topicarr['userid'];

            $topic_views = $topicarr['views'];

            $views = number_format($topic_views);

            $locked = $topicarr['locked'] == 'yes';

            $sticky = $topicarr['sticky'] == 'yes';

            $hlcolor = $topicarr['hlcolor'];

            // ---- Get reply count
            if (! $posts = $Cache->get_value('topic_'.$topicid.'_post_count')) {
                $posts = NexusDB::table('posts')->where('topicid', (int) $topicid)->count();
                $Cache->cache_value('topic_'.$topicid.'_post_count', $posts, 3600);
            }

            $replies = max(0, $posts - 1);

            $tpages = floor($posts / $postsperpage);

            if ($tpages * $postsperpage != $posts) {
                $tpages++;
            }

            if ($tpages > 1) {
                $topicpages = ' [<img class="multipage" src="pic/trans.gif" alt="multi-page" /> ';
                $dotted = 0;
                $dotspace = 4;
                $dotend = $tpages - $dotspace;
                for ($i = 1; $i <= $tpages; $i++) {
                    if ($i > $dotspace && $i <= $dotend) {
                        if (! $dotted) {
                            $topicpages .= ' ... ';
                        }
                        $dotted = 1;

                        continue;
                    }
                    $topicpages .= ' <a href="'.htmlspecialchars('?action=viewtopic&topicid='.$topicid.'&page='.($i - 1))."\">$i</a>";
                }

                $topicpages .= ' ]';
            } else {
                $topicpages = '';
            }

            // ---- Get userID and date of last post

            $arr = get_post_row($topicarr['lastpost']);
            $lppostid = intval($arr['id'] ?? 0);
            $lpuserid = intval($arr['userid'] ?? 0);
            $lpusername = get_username($lpuserid);
            $lpadded = gettime($arr['added'], true, false);
            $onmouseover = '';
            if ($enabletooltip_tweak == 'yes' && $CURUSER['showlastpost'] != 'no') {
                if ($CURUSER['timetype'] != 'timealive') {
                    $lastposttime = $lang_forums['text_at_time'].$arr['added'];
                } else {
                    $lastposttime = $lang_forums['text_blank'].gettime($arr['added'], true, false, true);
                }
                $lptext = format_comment(mb_substr($arr['body'], 0, 100, 'UTF-8').(mb_strlen($arr['body'], 'UTF-8') > 100 ? ' ......' : ''), true, false, false, true, 600, false, false);
                $lastpost_tooltip[$counter]['id'] = 'lastpost_'.$counter;
                $lastpost_tooltip[$counter]['content'] = $lang_forums['text_last_posted_by'].$lpusername.$lastposttime.'<br />'.$lptext;
                $onmouseover = "onmouseover=\"domTT_activate(this, event, 'content', document.getElementById('".$lastpost_tooltip[$counter]['id']."'), 'trail', false,'lifetime', 5000,'styleClass','niceTitle','fadeMax', 87,'maxWidth', 400);\"";
            }

            $arr = get_post_row($topicarr['firstpost']);
            $fpuserid = intval($arr['userid'] ?? 0);
            $fpauthor = get_username($arr['userid']);

            $subject = ($sticky ? '<img class="sticky" src="pic/trans.gif" alt="Sticky" title="'.$lang_forums['title_sticky'].'" />&nbsp;&nbsp;' : '').'<a href="'.htmlspecialchars('?action=viewtopic&forumid='.$forumid.'&topicid='.$topicid).'" '.$onmouseover.'>'.highlight_topic(highlight($search, htmlspecialchars($topicarr['subject'])), $hlcolor).'</a>'.$topicpages;
            $lastpostread = get_last_read_post_id($topicid);

            if ($lastpostread >= $lppostid) {
                $img = get_topic_image($locked ? 'locked' : 'read');
            } else {
                $img = get_topic_image($locked ? 'lockednew' : 'unread');
                if ($lastpostread != $CURUSER['last_catchup']) {
                    $subject .= '&nbsp;&nbsp;<a href="'.htmlspecialchars('?action=viewtopic&forumid='.$forumid.'&topicid='.$topicid.'&page=p'.$lastpostread.'#pid'.$lastpostread).'" title="'.$lang_forums['title_jump_to_unread'].'"><font class="small new"><b>'.$lang_forums['text_new'].'</b></font></a>';
                }
            }

            $topictime = substr($arr['added'], 0, 10);
            if (strtotime($arr['added']) + 86400 > TIMENOW) {
                $topictime = '<font class="new small">'.$topictime.'</font>';
            } else {
                $topictime = '<font color="gray" class="small">'.$topictime.'</font>';
            }

            echo '<tr><td class="rowfollow" align="left"><table border="0" cellspacing="0" cellpadding="0"><tr>'.
            "<td class=\"embedded\" style='padding-right: 10px'>".$img.
            "</td><td class=\"embedded\" align=\"left\">\n".
            $subject.'</td></tr></table></td><td class="rowfollow" align="center">'.get_username($fpuserid).'<br />'.$topictime.'</td><td class="rowfollow" align="center">'.$replies.' / <font color="gray">'.$views."</font></td>\n".
            '<td class="rowfollow nowrap" align="center">'.$lpadded.'<br />'.$lpusername."</td>\n";

            echo "</tr>\n";
            $counter++;

        } // while

        // print("</table>\n");
        // print("<table border=\"0\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\">");
        echo "<tr><td align=\"left\">\n";
        echo '<form method="get" action="forums.php"><b>'.$lang_forums['text_fast_search'].'</b><input type="hidden" name="action" value="viewforum" /><input type="hidden" name="forumid" value="'.$forumid.'" /><input type="text" style="width: 180px" name="search" />&nbsp;<input type="submit" value="'.$lang_forums['text_go'].'" /></form>';
        echo '</td>';
        ?>
<td align="left" colspan="3">
<span id="order" onclick="dropmenu(this);"><span style="cursor: pointer;"><b><?php echo $lang_forums['text_order']?></b></span>
<span id="orderlist" class="dropmenu" style="display: none"><ul>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam?>&amp;sort=firstpostdesc"><?php echo $lang_forums['text_topic_desc']?></a></li>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam?>&amp;sort=firstpostasc"><?php echo $lang_forums['text_topic_asc']?></a></li>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam?>&amp;sort=lastpostdesc"><?php echo $lang_forums['text_post_desc']?></a></li>
<li><a href="?action=viewforum&amp;forumid=<?php echo $forumid.$addparam?>&amp;sort=lastpostasc"><?php echo $lang_forums['text_post_asc']?></a></li>
</ul>
</span>
</span>
</td>
<?php
                echo '</tr></table>';
        echo $pagerbottom;
        if ($enabletooltip_tweak == 'yes' && $CURUSER['showlastpost'] != 'no') {
            create_tooltip_container($lastpost_tooltip, 400);
        }
    } // if
    else {
        echo '<p>'.$lang_forums['text_no_topics_found'].'</p>';
    }
    stdfoot();
    exit;
}

// -------- Action: View unread posts

if ($action == 'viewunread') {
    $userid = $CURUSER['id'];

    $beforepostid = intval($_GET['beforepostid'] ?? 0);
    $maxresults = 25;
    $unreadRows = NexusDB::select('SELECT id, forumid, subject, lastpost, hlcolor FROM topics WHERE lastpost > '.(int) $CURUSER['last_catchup'].($beforepostid ? ' AND lastpost < '.(int) $beforepostid : '').' ORDER BY lastpost DESC LIMIT 100');

    stdhead($lang_forums['head_view_unread']);
    echo '<h1 align="center"><a class="faqlink" href="forums.php">'.$SITENAME.'&nbsp;'.$lang_forums['text_forums'].'</a>-->'.$lang_forums['text_topics_with_unread_posts'].'</h1>';

    $n = 0;
    $uc = get_user_class();

    foreach ($unreadRows as $arr) {
        $topiclastpost = $arr['lastpost'];
        $topicid = $arr['id'];

        // ---- Check if post is read
        $lastpostread = get_last_read_post_id($topicid);

        if ($lastpostread >= $topiclastpost) {
            continue;
        }

        $forumid = $arr['forumid'];
        // ---- Check access & get forum name
        $a = get_forum_row($forumid);
        if ($uc < $a['minclassread']) {
            continue;
        }
        $n++;
        if ($n > $maxresults) {
            break;
        }

        $forumname = $a['name'];
        if ($n == 1) {
            echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n";
            echo '<tr><td class="colhead" align="left">'.$lang_forums['col_topic'].'</td><td class="colhead" align="left">'.$lang_forums['col_forum']."</td></tr>\n";
        }
        echo "<tr><td class=\"rowfollow\" align=\"left\"><table border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"embedded\" style='padding-right: 10px'>".
        get_topic_image('unread').'</td><td class="embedded">'.
        '<a href="'.htmlspecialchars('?action=viewtopic&topicid='.$topicid.($lastpostread > 0 && $lastpostread != $CURUSER['last_catchup'] ? '&page=p'.$lastpostread.'#pid'.$lastpostread : '')).'">'.highlight_topic(htmlspecialchars($arr['subject']), $arr['hlcolor']).
        '</a></td></tr></table></td><td class="rowfollow" align="left"><a href="'.htmlspecialchars('?action=viewforum&forumid='.$forumid).'"><b>'.$forumname."</b></a></td></tr>\n";
    }
    if ($n > 0) {
        echo "</table>\n";
        echo '<table border="0" class="main" cellspacing="0" cellpadding="5" width="1%"><tr><td class="embedded"><form method="get" action="?"><input type="hidden" name="catchup" value="1" /><input type="submit" value="'.$lang_forums['text_catch_up'].'" class="btn" /></form></td>';
        if ($n > $maxresults) {
            echo '<td class="embedded"><form method="get" action="?"><input type="hidden" name="action" value="viewunread" /><input type="hidden" name="beforepostid" value="'.$topiclastpost.'" /><input type="submit" value="'.$lang_forums['submit_show_more'].'" class="btn" /></form></td>';
        }
        echo '</tr></table>';
    } else {
        echo '<p>'.$lang_forums['text_nothing_found'].'</p>';
    }
    stdfoot();
    exit;
}

if ($action == 'search') {
    stdhead($lang_forums['head_forum_search']);
    unset($error);
    $error = true;
    $found = '';
    $keywords = htmlspecialchars(trim($_GET['keywords']));
    if ($keywords != '') {
        $extraSql = " LIKE '%".NexusDB::getInstance()->escapeString($keywords)."%'";

        $countRows = NexusDB::select('SELECT COUNT(posts.id) AS cnt FROM posts LEFT JOIN topics ON posts.topicid = topics.id LEFT JOIN forums ON topics.forumid = forums.id WHERE forums.minclassread <= '.(int) get_user_class()." AND ((topics.subject $extraSql AND posts.id=topics.firstpost) OR posts.body $extraSql)");
        $hits = (int) ($countRows[0]['cnt'] ?? 0);
        if ($hits) {
            $error = false;
            $found = '[<b><font class="striking"> '.$lang_forums['text_found'].$hits.$lang_forums['text_num_posts'].' </font></b>]';
        }
    }
    ?>
<style type="text/css">
.search{
	background-image:url(pic/search.gif);
	background-repeat:no-repeat;
	width:579px;
	height:95px;
	margin:5px 0 5px 0;
	text-align:left;
}
.search_title{
	color:#0062AE;
	background-color:#DAF3FB;
	font-size:12px;
	font-weight:bold;
	text-align:left;
	padding:7px 0 0 15px;
}

.search_table {
	border-collapse: collapse;
	border: none;
	background-color: #ffffff;
}

</style>
<div class="search">
	<div class="search_title"><?php echo $lang_forums['text_search_on_forum'] ?> <?php echo $error && $keywords != '' ? '[<b><font color=striking> '.$lang_forums['text_nothing_found'].'</font></b> ]' : $found?></div>
	<div style="margin-left: 53px; margin-top: 13px;">
		<form method="get" action="forums.php" id="search_form" style="margin: 0pt; padding: 0pt; font-family: Tahoma,Arial,Helvetica,sans-serif; font-size: 11px;">
		<input type="hidden" name="action" value="search" />
		<table border="0" cellpadding="0" cellspacing="0" width="512" class="search_table">
		<tbody>
		<tr>
		<td style="padding-bottom: 3px; border: 0;" valign="top"><?php echo $lang_forums['text_by_keyword'] ?></td>
		</tr>
		<tr>
		<td style="padding-bottom: 3px; border: 0;" valign="top">
			<input name="keywords" type="text" value="<?php echo $keywords?>" style="width: 400px;" /></td>
			<td style="padding-bottom: 3px; border: 0;" valign="top"><input name="image" type="image" style="vertical-align: middle; padding-bottom: 0px; margin-left: 0px;" src="<?php echo get_forum_pic_folder()?>/search_button.gif" alt="Search" /></td>
		</tr>
		</tbody>
		</table>
		</form>
	</div>
</div>
<?php

        if (! $error) {
            $perpage = $topicsperpage;
            [$pagertop, $pagerbottom, $limit] = pager($perpage, $hits, 'forums.php?action=search&keywords='.rawurlencode($keywords).'&');
            $searchRows = NexusDB::select('SELECT posts.id, posts.topicid, posts.userid, posts.added, topics.subject, topics.hlcolor, forums.id AS forumid, forums.name AS forumname FROM posts LEFT JOIN topics ON posts.topicid = topics.id LEFT JOIN forums ON topics.forumid = forums.id WHERE forums.minclassread <= '.(int) get_user_class()." AND ((topics.subject $extraSql AND posts.id=topics.firstpost) OR posts.body $extraSql) ORDER BY posts.id DESC $limit");

            echo $pagertop;
            echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\" width=\"97%\">\n";
            echo '<tr><td class="colhead" align="center">'.$lang_forums['col_post'].'</td><td class="colhead" align="center" width="70%">'.$lang_forums['col_topic'].'</td><td class="colhead" align="left">'.$lang_forums['col_forum'].'</td><td class="colhead" align="left">'.$lang_forums['col_posted_by']."</td></tr>\n";

            foreach ($searchRows as $post) {
                echo '<tr><td class="rowfollow" align="center" width="1%">'.$post['id'].'</td><td class="rowfollow" align="left"><a href="'.htmlspecialchars('?action=viewtopic&topicid='.$post['topicid'].'&highlight='.rawurlencode($keywords).'&page=p'.$post['id'].'#pid'.$post['id']).'">'.highlight_topic(highlight($keywords, htmlspecialchars($post['subject'])), $post['hlcolor']).'</a></td><td class="rowfollow nowrap" align="left"><a href="'.htmlspecialchars('?action=viewforum&forumid='.$post['forumid']).'"><b>'.htmlspecialchars($post['forumname']).'</b></a></td><td class="rowfollow nowrap" align="left">'.gettime($post['added'], true, false).'&nbsp;|&nbsp;'.get_username($post['userid'])."</td></tr>\n";
            }

            echo "</table>\n";
            echo $pagerbottom;
        }
    stdfoot();
    exit;
}

if (isset($_GET['catchup']) && $_GET['catchup'] == 1) {
    catch_up();
}

// -------- Handle unknown action
if ($action != '') {
    stderr($lang_forums['std_forum_error'], $lang_forums['std_unknown_action']);
}

// -------- Default action: View forums

// -------- Get forums
if ($CURUSER) {
    $USERUPDATESET['forum_access'] = date('Y-m-d H:i:s');
}

stdhead($lang_forums['head_forums']);
begin_main_frame();
echo '<h1 align="center">'.$SITENAME.'&nbsp;'.$lang_forums['text_forums'].'</h1>';
echo '<p align="center"><a href="?action=search"><b>'.$lang_forums['text_search'].'</b></a> | <a href="?action=viewunread"><b>'.$lang_forums['text_view_unread'].'</b></a> | <a href="?catchup=1"><b>'.$lang_forums['text_catch_up'].'</b></a> '.(user_can('forummanage') ? '| <a href="forummanage.php"><b>'.$lang_forums['text_forum_manager'].'</b></a>' : '').'</p>';
echo "<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\" width=\"100%\">\n";

if (! $overforums = $Cache->get_value('overforums_list')) {
    $overforums = [];
    foreach (NexusDB::select('SELECT * FROM overforums ORDER BY sort ASC') as $row) {
        $overforums[] = $row;
    }
    $Cache->cache_value('overforums_list', $overforums, 86400);
}
$count = 0;
if ($Advertisement->enable_ad()) {
    $interoverforumsad = $Advertisement->get_ad('interoverforums');
}

foreach ($overforums as $a) {
    if (get_user_class() < $a['minclassview']) {
        continue;
    }
    if ($count >= 1) {
        if ($Advertisement->enable_ad()) {
            if (! empty($interoverforumsad[$count - 1])) {
                echo '<tr><td colspan="5" align="center" id="">'.$interoverforumsad[$count - 1].'</td></tr>';
            }
        }
    }
    $forid = $a['id'];
    $overforumname = $a['name'];

    echo '<tr><td align="left" class="colhead" width="99%">'.htmlspecialchars($overforumname).'</td><td align="center" class="colhead">'.$lang_forums['col_topics'].'</td>'.
    '<td align="center" class="colhead">'.$lang_forums['col_posts'].'</td>'.
    '<td align="left" class="colhead">'.$lang_forums['col_last_post'].'</td><td class="colhead" align="left">'.$lang_forums['col_moderator']."</td></tr>\n";

    $forums = get_forum_row();
    foreach ($forums as $forums_arr) {
        if ($forums_arr['forid'] != $forid) {
            continue;
        }
        if (get_user_class() < $forums_arr['minclassread']) {
            continue;
        }

        $forumid = $forums_arr['id'];
        $forumname = htmlspecialchars($forums_arr['name']);
        $forumdescription = htmlspecialchars($forums_arr['description']);

        $forummoderators = get_forum_moderators($forums_arr['id'], false);
        if (! $forummoderators) {
            $forummoderators = '<a href="contactstaff.php"><i>'.$lang_forums['text_apply_now'].'</i></a>';
        }

        $topiccount = number_format($forums_arr['topiccount']);
        $postcount = number_format($forums_arr['postcount']);

        // Find last post ID
        // Returns the ID of the last post of a forum
        if (! $arr = $Cache->get_value('forum_'.$forumid.'_last_replied_topic_content')) {
            $lastTopicRows = NexusDB::select('SELECT * FROM topics WHERE forumid = '.(int) $forumid.' ORDER BY lastpost DESC LIMIT 1');
            $arr = $lastTopicRows[0] ?? [];
            $Cache->cache_value('forum_'.$forumid.'_last_replied_topic_content', $arr, 900);
        }

        if ($arr) {
            $lastpostid = $arr['lastpost'];
            // Get last post info
            $post_arr = get_post_row($lastpostid);
            $lastposterid = $post_arr['userid'];
            $lastpostdate = gettime($post_arr['added'], true, false);
            $lasttopicid = $arr['id'];
            $hlcolor = $arr['hlcolor'];
            $lasttopicdissubject = $lasttopicsubject = $arr['subject'];
            $max_length_of_topic_subject = 35;
            $count_dispname = mb_strlen($lasttopicdissubject, 'UTF-8');
            if ($count_dispname > $max_length_of_topic_subject) {
                $lasttopicdissubject = mb_substr($lasttopicdissubject, 0, $max_length_of_topic_subject - 2, 'UTF-8').'..';
            }
            $lasttopic = highlight_topic(htmlspecialchars($lasttopicdissubject), $hlcolor);

            $lastpost = '<a href="'.htmlspecialchars('?action=viewtopic&topicid='.$lasttopicid.'&page=last#last').'" title="'.htmlspecialchars($lasttopicsubject).'">'.$lasttopic.'</a><br />'.$lastpostdate.'&nbsp;|&nbsp;'.get_username($lastposterid);

            $lastreadpost = get_last_read_post_id($lasttopicid);

            if ($lastreadpost >= $lastpostid) {
                $img = get_topic_image('read');
            } else {
                $img = get_topic_image('unread');
            }
        } else {
            $lastpost = 'N/A';
            $img = get_topic_image('read');
        }
        $posttodaycount = $Cache->get_value('forum_'.$forumid.'_post_'.$today_date.'_count');
        if ($posttodaycount == '') {
            $row3Rows = NexusDB::select("SELECT COUNT(posts.id) AS cnt FROM posts LEFT JOIN topics ON posts.topicid = topics.id WHERE posts.added > '".date('Y-m-d')."' AND topics.forumid = ".(int) $forumid);
            $posttodaycount = (int) ($row3Rows[0]['cnt'] ?? 0);
            $Cache->cache_value('forum_'.$forumid.'_post_'.$today_date.'_count', $posttodaycount, 1800);
        }
        if ($posttodaycount > 0) {
            $posttoday = '&nbsp;&nbsp;('.$lang_forums['text_today'].'<b><font class="new">'.$posttodaycount.'</font></b>)';
        } else {
            $posttoday = '';
        }
        echo "<tr><td class=\"rowfollow\" align=\"left\"><table border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr><td class=\"embedded\" style='padding-right: 10px'>".$img.'</td><td class="embedded"><a href="'.htmlspecialchars('?action=viewforum&forumid='.$forumid).'"><font class="big"><b>'.$forumname.'</b></font></a>'.$posttoday.
        '<br />'.$forumdescription.'</td></tr></table></td><td class="rowfollow" align="center" width="1%">'.$topiccount.'</td><td class="rowfollow" align="center" width="1%">'.$postcount.'</td>'.
        '<td class="rowfollow nowrap" align="left">'.$lastpost.'</td><td class="rowfollow" align="left">'.$forummoderators."</td></tr>\n";
    }
    $count++;
}
// End Table Mod
echo '</table>';
if ($showforumstats_main == 'yes') {
    forum_stats();
}
end_main_frame();
stdfoot();
?>

<?php

use App\Models\Message;
use App\Models\StaffMessage;
use Nexus\Database\NexusDB;

require_once '../include/bittorrent.php';
dbconn();
require_once get_langfile_path();
// require_once(get_langfile_path("",true));
loggedinorreturn();
parked();
if ($enableoffer == 'no') {
    permissiondenied();
}
function bark($msg)
{
    global $lang_offers;
    stdhead($lang_offers['head_offer_error']);
    stdmsg($lang_offers['std_error'], $msg);
    stdfoot();
    exit;
}

if (isset($_GET['category']) && $_GET['category']) {
    $categ = isset($_GET['category']) ? (int) $_GET['category'] : 0;
    if (! is_valid_id($categ)) {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }
}

if (isset($_GET['id']) && $_GET['id']) {
    $id = htmlspecialchars(intval($_GET['id'] ?? 0));
    if (preg_match('/^[0-9]+$/', ! $id)) {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }
}

// ==== add offer
if (isset($_GET['add_offer']) && $_GET['add_offer']) {
    user_can('addoffer', true);
    $add_offer = intval($_GET['add_offer'] ?? 0);
    if ($add_offer != '1') {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    stdhead($lang_offers['head_offer']);

    echo '<p>'.$lang_offers['text_red_star_required'].'</p>';

    echo '<div align="center"><form id="compose" action="?new_offer=1" name="compose" method="post">'.
    '<table width=100% border=0 cellspacing=0 cellpadding=5><tr><td class=colhead align=center colspan=2>'.$lang_offers['text_offers_open_to_all']."</td></tr>\n";

    $s = "<select name=type>\n<option value=0>".$lang_offers['select_type_select']."</option>\n";
    $cats = genrelist($browsecatmode);
    foreach ($cats as $row) {
        $s .= '<option value='.$row['id'].'>'.htmlspecialchars($row['name'])."</option>\n";
    }
    $s .= "</select>\n";
    echo '<tr><td class=rowhead align=right><b>'.$lang_offers['row_type']."<font color=red>*</font></b></td><td class=rowfollow align=left> $s</td></tr>".
    '<tr><td class=rowhead align=right><b>'.$lang_offers['row_title'].'<font color=red>*</font></b></td><td class=rowfollow align=left><input type=text name=name style="width: 99%;" />'.
    '</td></tr><tr><td class=rowhead align=right><b>'.$lang_offers['row_post_or_photo'].'</b></td><td class=rowfollow align=left>'.
    '<input type=text name=picture style="width: 99%;"><br />'.$lang_offers['text_link_to_picture'].'</td></tr>'.
    '<tr><td class=rowhead align=right valign=top><b>'.$lang_offers['row_description']."<b><font color=red>*</font></td><td class=rowfollow align=left>\n";
    textbbcode('compose', 'body', $body, false, 130, true);
    echo '</td></tr><tr><td class=toolbox align=center colspan=2><input id=qr type=submit class=btn value='.$lang_offers['submit_add_offer']." ></td></tr></table></form><br />\n";
    stdfoot();
    exit;
}
// === end add offer

// === take new offer
if (isset($_GET['new_offer']) && $_GET['new_offer']) {
    user_can('addoffer', true);
    $new_offer = intval($_GET['new_offer'] ?? 0);
    if ($new_offer != '1') {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $userid = intval($CURUSER['id'] ?? 0);
    if (preg_match('/^[0-9]+$/', ! $userid)) {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $name = $_POST['name'];
    if ($name == '') {
        bark($lang_offers['std_must_enter_name']);
    }

    $cat = intval($_POST['type'] ?? 0);
    if (! is_valid_id($cat)) {
        bark($lang_offers['std_must_select_category']);
    }

    $descrmain = unesc($_POST['body']);
    if (! $descrmain) {
        bark($lang_offers['std_must_enter_description']);
    }

    if (! empty($_POST['picture'])) {
        $picture = unesc($_POST['picture']);
        if (! preg_match("/^https?:\/\/[^\s'\"<>]+\.(jpg|gif|png)$/i", $picture)) {
            stderr($lang_offers['std_error'], $lang_offers['std_wrong_image_format']);
        }
        $pic = '[img]'.$picture."[/img]\n";
    }

    $descr = $pic;
    $descr .= $descrmain;

    $existRow = NexusDB::table('offers')->where('name', (string) $_POST['name'])->select(['name'])->first();
    $arr = $existRow ? (array) $existRow : [];
    if (empty($arr['name'])) {

        $id = (int) NexusDB::insert('offers', [
            'userid' => (int) $CURUSER['id'],
            'name' => $name,
            'descr' => $descr,
            'category' => intval($_POST['type'] ?? 0),
            'added' => date('Y-m-d H:i:s'),
        ]);

        // add new offer message to staffmessage
        StaffMessage::query()->insert([
            'sender' => $CURUSER['id'],
            'subject' => nexus_trans('offer.msg_new_offer_subject'),
            'msg' => nexus_trans('offer.msg_new_offer_msg', [
                'username' => "[url=userdetails.php?id={$CURUSER['id']}]{$CURUSER['username']}[/url]",
                'offername' => "[url=offers.php?id={$id}&off_details=1]{$name}[/url]"]),
            'added' => now(),
        ]);
        clear_staff_message_cache();

        write_log("offer $name was added by ".$CURUSER['username'], 'normal');

        header("Location: offers.php?id=$id&off_details=1");

        stdhead($lang_offers['head_success']);
    } else {
        stderr($lang_offers['std_error'], $lang_offers['std_offer_exists'].'<a class=altlink href=offers.php>'.$lang_offers['text_view_all_offers'].'</a>', false);
    }
    stdfoot();
    exit;
}
// ==end take new offer

// === offer details
if (isset($_GET['off_details']) && $_GET['off_details']) {

    $off_details = intval($_GET['off_details'] ?? 0);
    if ($off_details != '1') {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $id = intval($_GET['id'] ?? 0);
    if (! $id) {
        exit();
    }
    // stderr("Error", "I smell a rat!");

    $detailRows = NexusDB::select('SELECT * FROM offers WHERE id = '.(int) $id);
    $num = $detailRows[0] ?? null;
    if (! $num) {
        bark($lang_offers['text_nothing_found']);
    }

    $s = $num['name'];

    stdhead($lang_offers['head_offer_detail_for'].' "'.$s.'"');
    echo '<h1 align="center" id="top">'.htmlspecialchars($s).'</h1>';

    echo '<table width="97%" cellspacing="0" cellpadding="5">';
    $offertime = gettime($num['added'], true, false);
    if ($CURUSER['timetype'] != 'timealive') {
        $offertime = $lang_offers['text_at'].$offertime;
    } else {
        $offertime = $lang_offers['text_blank'].$offertime;
    }
    tr($lang_offers['row_info'], $lang_offers['text_offered_by'].get_username($num['userid']).$offertime, 1);
    if ($num['allowed'] == 'pending') {
        $status = '<font color="red">'.$lang_offers['text_pending'].'</font>';
    } elseif ($num['allowed'] == 'allowed') {
        $status = '<font color="green">'.$lang_offers['text_allowed'].'</font>';
    } else {
        $status = '<font color="red">'.$lang_offers['text_denied'].'</font>';
    }
    tr($lang_offers['row_status'], $status, 1);
    // === if you want to have a pending thing for uploaders use this next bit
    if (user_can('offermanage') && $num['allowed'] == 'pending') {
        tr($lang_offers['row_allow'], '<table><tr><td class="embedded"><form method="post" action="?allow_offer=1"><input type="hidden" value="'.$id.'" name="offerid" />'.
        '<input class="btn" type="submit" value="'.$lang_offers['submit_allow'].'" />&nbsp;&nbsp;</form></td><td class="embedded"><form method="post" action="?id='.$id.'&amp;finish_offer=1">'.
        '<input type="hidden" value="'.$id.'" name="finish" /><input class="btn" type="submit" value="'.$lang_offers['submit_let_votes_decide'].'" /></form></td></tr></table>', 1);
    }

    $zres = NexusDB::select("SELECT COUNT(*) AS cnt from offervotes where vote='yeah' and offerid = ".(int) $id);
    $za = (int) ($zres[0]['cnt'] ?? 0);
    $pres = NexusDB::select("SELECT COUNT(*) AS cnt from offervotes where vote='against' and offerid = ".(int) $id);
    $protiv = (int) ($pres[0]['cnt'] ?? 0);
    // === in the following section, there is a line to report comment... either remove the link or change it to work with your report script :)

    // if pending
    if ($num['allowed'] == 'pending') {
        tr($lang_offers['row_vote'], '<b>'.
        '<a href="?id='.$id.'&amp;vote=yeah"><font color="green">'.$lang_offers['text_for'].'</font></a></b>'.(user_can('againstoffer') ? ' - <b><a href="?id='.$id.'&amp;vote=against">'.
        '<font color="red">'.$lang_offers['text_against'].'</font></a></b>' : ''), 1);
        tr($lang_offers['row_vote_results'],
            '<b>'.$lang_offers['text_for'].":</b> $za  <b>".$lang_offers['text_against']."</b> $protiv &nbsp; &nbsp; <a href=\"?id=".$id.'&amp;offer_vote=1"><i>'.$lang_offers['text_see_vote_detail'].'</i></a>', 1);
    }
    // ===upload torrent message
    if ($num['allowed'] == 'allowed' && $CURUSER['id'] != $num['userid']) {
        tr($lang_offers['row_offer_allowed'], $lang_offers['text_voter_receives_pm_note'], 1);
    }
    if ($num['allowed'] == 'allowed' && $CURUSER['id'] == $num['userid']) {
        tr($lang_offers['row_offer_allowed'],
            $lang_offers['text_urge_upload_offer_note'], 1);
    }
    if ($CURUSER['id'] == $num['userid'] || user_can('offermanage')) {
        $edit = '<a href="?id='.$id.'&amp;edit_offer=1"><img class="dt_edit" src="pic/trans.gif" alt="edit" />&nbsp;<b><font class="small">'.$lang_offers['text_edit_offer'].'</font></b></a>&nbsp;|&nbsp;';
        $delete = '<a href="?id='.$id.'&amp;del_offer=1&amp;sure=0"><img class="dt_delete" src="pic/trans.gif" alt="delete" />&nbsp;<b><font class="small">'.$lang_offers['text_delete_offer'].'</font></b></a>&nbsp;|&nbsp;';
    }
    $report = '<a href="report.php?reportofferid='.$id.'"><img class="dt_report" src="pic/trans.gif" alt="report" />&nbsp;<b><font class="small">'.$lang_offers['report_offer'].'</font></b></a>';
    tr($lang_offers['row_action'], $edit.$delete.$report, 1);
    if ($num['descr']) {
        $off_bb = format_comment($num['descr']);
        tr($lang_offers['row_description'], $off_bb, 1);
    }
    echo '</table>';
    // -----------------COMMENT SECTION ---------------------//
    $commentbar = '<p align="center"><a class="index" href="comment.php?action=add&amp;pid='.$id.'&amp;type=offer">'.$lang_offers['text_add_comment']."</a></p>\n";
    $subres = NexusDB::select('SELECT COUNT(*) AS cnt FROM comments WHERE offer = '.(int) $id);
    $count = (int) ($subres[0]['cnt'] ?? 0);
    if (! $count) {
        echo '<h1 id="startcomments" align="center">'.$lang_offers['text_no_comments']."</h1>\n";
    } else {
        [$pagertop, $pagerbottom, $limit] = pager(10, $count, "offers.php?id=$id&off_details=1&", ['lastpagedefault' => 1]);

        $allrows = NexusDB::select('SELECT id, text, user, added, editedby, editdate FROM comments  WHERE offer = '.(int) $id." ORDER BY id $limit");

        // end_frame();
        // print($commentbar);
        echo $pagertop;

        commenttable($allrows, 'offer', $id);
        echo $pagerbottom;
    }
    echo "<table style='border:1px solid #000000;'><tr>".
'<td class="text" align="center"><b>'.$lang_offers['text_quick_comment'].'</b><br /><br />'.
'<form id="compose" name="comment" method="post" action="comment.php?action=add&amp;type=offer" onsubmit="return postvalid(this);">'.
'<input type="hidden" name="pid" value="'.$id.'" /><br />';
    quickreply('comment', 'body', $lang_offers['submit_add_comment']);
    echo '</form></td></tr></table>';
    echo $commentbar;
    stdfoot();
    exit;
}
// === end offer details
// === allow offer by staff
if (isset($_GET['allow_offer']) && $_GET['allow_offer']) {

    if (! user_can('offermanage')) {
        stderr($lang_offers['std_access_denied'], $lang_offers['std_mans_job']);
    }

    $allow_offer = intval($_GET['allow_offer'] ?? 0);
    if ($allow_offer != '1') {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    // === to allow the offer  credit to S4NE for this next bit :)
    // if ($_POST["offerid"]){
    $offid = intval($_POST['offerid'] ?? 0);
    if (! is_valid_id($offid)) {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $allowRows = NexusDB::select('SELECT users.username, offers.userid, offers.name FROM offers inner join users on offers.userid = users.id where offers.id = '.(int) $offid);
    $arr = $allowRows[0] ?? [];
    $locale = get_user_locale($arr['userid'] ?? 0);
    if ($offeruptimeout_main) {
        $timeouthour = floor($offeruptimeout_main / 3600);
        $timeoutnote = nexus_trans('offer.msg_you_must_upload_in', [], $locale).$timeouthour.nexus_trans('offer.msg_hours_otherwise', [], $locale);
    } else {
        $timeoutnote = '';
    }
    $msg = $CURUSER['username'].nexus_trans('offer.msg_has_allowed', [], $locale).'[b][url='.get_protocol_prefix().$BASEURL."/offers.php?id=$offid&off_details=1]".$arr['name'].'[/url][/b]. '.nexus_trans('offer.msg_find_offer_option', [], $locale).$timeoutnote;

    $subject = nexus_trans('offer.msg_your_offer_allowed', [], $locale);
    $allowedtime = date('Y-m-d H:i:s');

    Message::add([
        'sender' => 0,
        'receiver' => $arr['userid'],
        'msg' => $msg,
        'subject' => $subject,
        'added' => $allowedtime,
    ]);

    NexusDB::table('offers')
        ->where('id', (int) $offid)
        ->update(['allowed' => 'allowed', 'allowedtime' => (string) $allowedtime]);

    write_log("{$CURUSER['username']} allowed offer {$arr['name']}", 'normal');
    header('Location: '.get_protocol_prefix()."$BASEURL/offers.php?id=$offid&off_details=1");
}
// === end allow the offer

// === allow offer by vote
if (isset($_GET['finish_offer']) && $_GET['finish_offer']) {

    if (! user_can('offermanage')) {
        stderr($lang_offers['std_access_denied'], $lang_offers['std_have_no_permission']);
    }

    $finish_offer = intval($_GET['finish_offer'] ?? 0);
    if ($finish_offer != '1') {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $offid = intval($_POST['finish'] ?? 0);
    if (! is_valid_id($offid)) {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $finishRows = NexusDB::select('SELECT users.username, offers.userid, offers.name FROM offers inner join users on offers.userid = users.id where offers.id = '.(int) $offid);
    $arr = $finishRows[0] ?? [];
    $locale = get_user_locale($arr['userid'] ?? 0);

    $voteresyes = NexusDB::select("SELECT COUNT(*) AS cnt from offervotes where vote='yeah' and offerid = ".(int) $offid);
    $yes = (int) ($voteresyes[0]['cnt'] ?? 0);
    $voteresno = NexusDB::select("SELECT COUNT(*) AS cnt from offervotes where vote='against' and offerid = ".(int) $offid);
    $no = (int) ($voteresno[0]['cnt'] ?? 0);

    if ($yes == '0' && $no == '0') {
        stderr($lang_offers['std_sorry'], $lang_offers['std_no_votes_yet']."<a  href=offers.php?id=$offid&off_details=1>".$lang_offers['std_back_to_offer_detail'].'</a>', false);
    }
    $finishvotetime = date('Y-m-d H:i:s');
    if (($yes - $no) >= $minoffervotes) {
        if ($offeruptimeout_main) {
            $timeouthour = floor($offeruptimeout_main / 3600);
            $timeoutnote = nexus_trans('offer.msg_you_must_upload_in', [], $locale).$timeouthour.nexus_trans('offer.msg_hours_otherwise', [], $locale);
        } else {
            $timeoutnote = '';
        }
        $msg = nexus_trans('offer.msg_offer_voted_on', [], $locale).'[b][url='.get_protocol_prefix().$BASEURL."/offers.php?id=$offid&off_details=1]".$arr['name'].'[/url][/b].'.nexus_trans('offer.msg_find_offer_option', [], $locale).$timeoutnote;
        NexusDB::table('offers')
            ->where('id', (int) $offid)
            ->update(['allowed' => 'allowed', 'allowedtime' => (string) $finishvotetime]);
    } elseif (($no - $yes) >= $minoffervotes) {
        $msg = nexus_trans('offer.msg_offer_voted_off', [], $locale).'[b][url='.get_protocol_prefix().$BASEURL."/offers.php?id=$offid&off_details=1]".$arr['name'].'[/url][/b].'.nexus_trans('offer.msg_offer_deleted', [], $locale);
        NexusDB::statement("UPDATE offers SET allowed = 'denied' WHERE id = ".(int) $offid);
    }
    // ===use this line if you DO HAVE subject in your PM system
    $subject = nexus_trans('offer.msg_your_offer', [], $locale).$arr['name'].nexus_trans('offer.msg_voted_on', [], $locale);

    Message::add([
        'sender' => 0,
        'subject' => $subject,
        'receiver' => $arr['userid'],
        'added' => $finishvotetime,
        'msg' => $msg,
    ]);

    write_log("{$CURUSER['username']} closed poll {$arr['name']}", 'normal');

    header('Location: '.get_protocol_prefix()."$BASEURL/offers.php?id=$offid&off_details=1");
    exit;
}
// ===end allow offer by vote

// === edit offer

if (isset($_GET['edit_offer']) && $_GET['edit_offer']) {

    $edit_offer = intval($_GET['edit_offer'] ?? 0);
    if ($edit_offer != '1') {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $id = intval($_GET['id'] ?? 0);

    $editRows = NexusDB::select('SELECT * FROM offers WHERE id = '.(int) $id);
    $num = $editRows[0] ?? [];

    $timezone = $num['added'];

    $s = $num['name'];
    $id2 = $num['category'];

    if ($CURUSER['id'] != $num['userid'] && ! user_can('offermanage')) {
        stderr($lang_offers['std_error'], $lang_offers['std_cannot_edit_others_offer']);
    }

    $body = htmlspecialchars(unesc($num['descr']));
    $s2 = "<select name=\"category\">\n";

    $cats = genrelist($browsecatmode);

    foreach ($cats as $row) {
        $s2 .= '<option value="'.$row['id'].'" '.($row['id'] == $id2 ? ' selected="selected"' : '').'>'.htmlspecialchars($row['name'])."</option>\n";
    }
    $s2 .= "</select>\n";

    stdhead($lang_offers['head_edit_offer'].": $s");
    $title = htmlspecialchars(trim($s));

    echo '<form id="compose" method="post" name="compose" action="?id='.$id.'&amp;take_off_edit=1">'.
    '<table width="97%" cellspacing="0" cellpadding="3"><tr><td class="colhead" align="center" colspan="2">'.$lang_offers['text_edit_offer'].'</td></tr>';
    tr($lang_offers['row_type'].'<font color="red">*</font>', $s2, 1);
    tr($lang_offers['row_title'].'<font color="red">*</font>', '<input type="text" style="width: 99%" name="name" value="'.$title.'" />', 1);
    tr($lang_offers['row_post_or_photo'], "<input type=\"text\" name=\"picture\" style=\"width: 99%\" value='' /><br />".$lang_offers['text_link_to_picture'], 1);
    echo '<tr><td class="rowhead" align="right" valign="top"><b>'.$lang_offers['row_description'].'<font color="red">*</font></b></td><td class="rowfollow" align="left">';
    textbbcode('compose', 'body', $body, false, 130, true);
    echo '</td></tr>';
    echo '<tr><td class="toolbox" style="vertical-align: middle; padding-top: 10px; padding-bottom: 10px;" align="center" colspan="2"><input id="qr" type="submit" value="'.$lang_offers['submit_edit_offer']."\" class=\"btn\" /></td></tr></table></form><br />\n";
    stdfoot();
    exit;
}
// === end edit offer

// ==== take offer edit
if (isset($_GET['take_off_edit']) && $_GET['take_off_edit']) {

    $take_off_edit = intval($_GET['take_off_edit'] ?? 0);
    if ($take_off_edit != '1') {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $id = intval($_GET['id'] ?? 0);

    $takeRows = NexusDB::select('SELECT userid FROM offers WHERE id = '.(int) $id);
    $num = $takeRows[0] ?? [];

    if ($CURUSER['id'] != $num['userid'] && ! user_can('offermanage')) {
        stderr($lang_offers['std_error'], $lang_offers['std_access_denied']);
    }

    $name = $_POST['name'];

    if (! empty($_POST['picture'])) {
        $picture = unesc($_POST['picture']);
        if (! preg_match("/^https?:\/\/[^\s'\"<>]+\.(jpg|gif|png)$/i", $picture)) {
            stderr($lang_offers['std_error'], $lang_offers['std_wrong_image_format']);
        }
        $pic = '[img]'.$picture."[/img]\n";
    }
    $descr = "$pic";
    $descr .= unesc($_POST['body']);
    if (! $name) {
        bark($lang_offers['std_must_enter_name']);
    }
    if (! $descr) {
        bark($lang_offers['std_must_enter_description']);
    }
    $cat = intval($_POST['category'] ?? 0);
    if (! is_valid_id($cat)) {
        bark($lang_offers['std_must_select_category']);
    }

    NexusDB::table('offers')
        ->where('id', (int) $id)
        ->update([
            'category' => (int) $cat,
            'name' => (string) $name,
            'descr' => (string) $descr,
        ]);

    // header("Location: offers.php?id=$id&off_details=1");
}
// ======end take offer edit

// === offer votes list
if (isset($_GET['offer_vote']) && $_GET['offer_vote']) {

    $offer_vote = intval($_GET['offer_vote'] ?? 0);
    if ($offer_vote != '1') {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $offerid = htmlspecialchars(intval($_GET['id'] ?? 0));

    $res2 = NexusDB::select('SELECT COUNT(*) AS cnt FROM offervotes WHERE offerid = '.(int) $offerid);
    $count = (int) ($res2[0]['cnt'] ?? 0);

    $offername = NexusDB::table('offers')->where('id', (int) $offerid)->value('name');
    stdhead($lang_offers['head_offer_voters'].' - "'.$offername.'"');

    echo '<h1 align=center>'.$lang_offers['text_vote_results_for']." <a  href=offers.php?id=$offerid&off_details=1><b>".htmlspecialchars($offername).'</b></a></h1>';

    $perpage = 25;
    [$pagertop, $pagerbottom, $limit] = pager($perpage, $count, $_SERVER['PHP_SELF'].'?id='.$offerid.'&offer_vote=1&');
    $voteListRows = NexusDB::select('SELECT * FROM offervotes WHERE offerid = '.(int) $offerid.' '.$limit);

    if (count($voteListRows) == 0) {
        echo '<p align=center><b>'.$lang_offers['std_no_votes_yet']."</b></p>\n";
    } else {
        echo $pagertop;
        echo '<table border=1 cellspacing=0 cellpadding=5><tr><td class=colhead>'.$lang_offers['col_user'].'</td><td class=colhead align=left>'.$lang_offers['col_vote']."</td>\n";

        foreach ($voteListRows as $arr) {
            if ($arr['vote'] == 'yeah') {
                $vote = '<b><font color=green>'.$lang_offers['text_for'].'</font></b>';
            } elseif ($arr['vote'] == 'against') {
                $vote = '<b><font color=red>'.$lang_offers['text_against'].'</font></b>';
            } else {
                $vote = 'unknown';
            }

            echo '<tr><td class=rowfollow>'.get_username($arr['userid']).'</td><td class=rowfollow align=left >'.$vote."</td></tr>\n";
        }
        echo "</table>\n";
        echo $pagerbottom;
    }

    stdfoot();
    exit;
}
// === end offer votes list

// === offer votes
if (isset($_GET['vote']) && $_GET['vote']) {
    $offerid = htmlspecialchars(intval($_GET['id'] ?? 0));
    $vote = htmlspecialchars($_GET['vote']);
    if ($vote == 'against' && ! user_can('againstoffer')) {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }
    if ($vote == 'yeah' || $vote == 'against') {
        $userid = intval($CURUSER['id'] ?? 0);
        $voteCheckRows = NexusDB::select('SELECT * FROM offervotes WHERE offerid = '.(int) $offerid.' AND userid = '.(int) $userid);
        $arr = $voteCheckRows[0] ?? null;
        $voted = $arr;
        $offer_userid = NexusDB::table('offers')->where('id', (int) $offerid)->value('userid');
        if ($offer_userid == $CURUSER['id']) {
            stderr($lang_offers['std_error'], $lang_offers['std_cannot_vote_youself']);
        } elseif ($voted) {
            stderr($lang_offers['std_already_voted'], $lang_offers['std_already_voted_note']."<a  href=offers.php?id=$offerid&off_details=1>".$lang_offers['std_back_to_offer_detail'], false);
        } else {
            $voteOfferRows = NexusDB::select('SELECT users.username, offers.userid, offers.name FROM offers LEFT JOIN users ON offers.userid = users.id WHERE offers.id = '.(int) $offerid);
            $arr = $voteOfferRows[0] ?? null;
            if (! $arr) {
                bark($lang_offers['text_nothing_found']);
            }
            NexusDB::statement("UPDATE offers SET $vote = $vote + 1 WHERE id = ".(int) $offerid);
            $locale = get_user_locale($arr['userid']);

            $yaRows = NexusDB::select('SELECT yeah, against, allowed FROM offers WHERE id = '.(int) $offerid);
            $ya_arr = $yaRows[0] ?? [];
            $yeah = $ya_arr['yeah'];
            $against = $ya_arr['against'];
            $finishtime = date('Y-m-d H:i:s');
            // allowed and send offer voted on message
            if (($yeah - $against) >= $minoffervotes && $ya_arr['allowed'] != 'allowed') {
                if ($offeruptimeout_main) {
                    $timeouthour = floor($offeruptimeout_main / 3600);
                    $timeoutnote = nexus_trans('offer.msg_you_must_upload_in', [], $locale).$timeouthour.nexus_trans('offer.msg_hours_otherwise', [], $locale);
                } else {
                    $timeoutnote = '';
                }
                NexusDB::table('offers')
                    ->where('id', (int) $offerid)
                    ->update(['allowed' => 'allowed', 'allowedtime' => (string) $finishtime]);
                $msg = nexus_trans('offer.msg_offer_voted_on', [], $locale).'[b][url='.get_protocol_prefix().$BASEURL."/offers.php?id=$offerid&off_details=1]".$arr['name'].'[/url][/b].'.nexus_trans('offer.msg_find_offer_option', [], $locale).$timeoutnote;
                $subject = nexus_trans('offer.msg_your_offer_allowed', [], $locale);

                Message::add([
                    'sender' => 0,
                    'receiver' => $arr['userid'],
                    'msg' => $msg,
                    'subject' => $subject,
                    'added' => now(),
                ]);

                write_log("System allowed offer {$arr['name']}", 'normal');
            }
            // denied and send offer voted off message
            if (($against - $yeah) >= $minoffervotes && $ya_arr['allowed'] != 'denied') {
                NexusDB::statement("UPDATE offers SET allowed = 'denied' WHERE id = ".(int) $offerid);
                $msg = nexus_trans('offer.msg_offer_voted_off', [], $locale).'[b][url='.get_protocol_prefix().$BASEURL."/offers.php?id=$offid&off_details=1]".$arr['name'].'[/url][/b].'.nexus_trans('offer.msg_offer_deleted', [], $locale);
                $subject = nexus_trans('offer.msg_offer_deleted', [], $locale);

                Message::add([
                    'sender' => 0,
                    'receiver' => $arr['userid'],
                    'msg' => $msg,
                    'subject' => $subject,
                    'added' => now(),
                ]);

                write_log("System denied offer {$arr['name']}", 'normal');
            }

            NexusDB::insert('offervotes', [
                'offerid' => (int) $offerid,
                'userid' => (int) $userid,
                'vote' => $vote,
            ]);
            KPS('+', $offervote_bonus, $CURUSER['id']);
            stdhead($lang_offers['head_vote_for_offer']);
            echo '<h1 align=center>'.$lang_offers['std_vote_accepted'].'</h1>';
            echo $lang_offers['std_vote_accepted_note']."<a  href=offers.php?id=$offerid&off_details=1>".$lang_offers['std_back_to_offer_detail'];
            stdfoot();
            exit;
        }
    } else {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }
}
// === end offer votes

// === delete offer
if (isset($_GET['del_offer']) && $_GET['del_offer']) {

    $del_offer = intval($_GET['del_offer'] ?? 0);
    if ($del_offer != '1') {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $offer = intval($_GET['id'] ?? 0);

    $userid = intval($CURUSER['id'] ?? 0);
    if (! is_valid_id($userid)) {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }

    $delRows = NexusDB::select('SELECT * FROM offers WHERE id = '.(int) $offer);
    $num = $delRows[0] ?? [];

    $name = $num['name'];

    if ($userid != $num['userid'] && ! user_can('offermanage')) {
        stderr($lang_offers['std_error'], $lang_offers['std_cannot_delete_others_offer']);
    }

    if ($_GET['sure']) {
        $sure = $_GET['sure'];
        if ($sure == '0' || $sure == '1') {
            $sure = intval($_GET['sure'] ?? 0);
        } else {
            stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
        }
    }

    if ($sure == 0) {
        stderr($lang_offers['std_delete_offer'], $lang_offers['std_delete_offer_note']."<br /><form method=post action=offers.php?id=$offer&del_offer=1&sure=1>".$lang_offers['text_reason_is'].'<input type=text style="width: 200px" name=reason><input type=submit value="'.$lang_offers['submit_confirm'].'"></form>', false);
    } elseif ($sure == 1) {
        $reason = $_POST['reason'];
        NexusDB::statement('DELETE FROM offers WHERE id = '.(int) $offer);
        NexusDB::statement('DELETE FROM offervotes WHERE offerid = '.(int) $offer);
        NexusDB::statement('DELETE FROM comments WHERE offer = '.(int) $offer);


        if ($CURUSER['id'] != $num['userid']) {
            $added = date('Y-m-d H:i:s');
            $locale = get_user_locale($num['userid']);
            $subject = nexus_trans('offer.msg_offer_deleted', [], $locale);
            $msg = nexus_trans('offer.msg_your_offer', [], $locale).$num['name'].nexus_trans('offer.msg_was_deleted_by', [], $locale).'[url=userdetails.php?id='.$CURUSER['id'].']'.$CURUSER['username'].'[/url]'.nexus_trans('offer.msg_blank', [], $locale).($reason != '' ? nexus_trans('offer.msg_reason_is', [], $locale).$reason : '');

            Message::add([
                'sender' => 0,
                'receiver' => $num['userid'],
                'msg' => $msg,
                'subject' => $subject,
                'added' => now(),
            ]);
        }
        write_log("Offer: $offer ({$num['name']}) was deleted by {$CURUSER['username']}".($reason != '' ? ' ('.$reason.')' : ''), 'normal');
        header('Location: offers.php');
        exit;
    } else {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }
}
// == end  delete offer

// === prolly not needed, but what the hell... basically stopping the page getting screwed up
$sort = '';
if (isset($_GET['sort']) && $_GET['sort']) {
    $sort = $_GET['sort'];
    if ($sort == 'cat' || $sort == 'name' || $sort == 'added' || $sort == 'comments' || $sort == 'yeah' || $sort == 'against' || $sort == 'v_res') {
        $sort = $_GET['sort'];
    } else {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }
}
// === end of prolly not needed, but what the hell :P

$categ = intval($_GET['category'] ?? 0);
$offerorid = 0;
if (isset($_GET['offerorid']) && $_GET['offerorid']) {
    $offerorid = htmlspecialchars(intval($_GET['offerorid'] ?? 0));
    if (preg_match('/^[0-9]+$/', ! $offerorid)) {
        stderr($lang_offers['std_error'], $lang_offers['std_smell_rat']);
    }
}

$search = ($_GET['search'] ?? '');

if ($search) {
    $search = ' AND offers.name like '.NexusDB::getPdo()->quote('%'.$search.'%');
} else {
    $search = '';
}

$cat_order_type = 'desc';
$name_order_type = 'desc';
$added_order_type = 'desc';
$comments_order_type = 'desc';
$v_res_order_type = 'desc';

/*
if ($cat_order_type == "") { $sort = " ORDER BY added " . $added_order_type; $cat_order_type = "asc"; } // for torrent name
if ($name_order_type == "") { $sort = " ORDER BY added " . $added_order_type; $name_order_type = "desc"; }
if ($added_order_type == "") { $sort = " ORDER BY added " . $added_order_type; $added_order_type = "desc"; }
if ($comments_order_type == "") { $sort = " ORDER BY added " . $added_order_type; $comments_order_type = "desc"; }
if ($v_res_order_type == "") { $sort = " ORDER BY added " . $added_order_type; $v_res_order_type = "desc"; }
*/

if ($sort == 'cat') {
    if ($_GET['type'] == 'desc') {
        $cat_order_type = 'asc';
    }
    $sort = ' ORDER BY category '.$cat_order_type;
} elseif ($sort == 'name') {
    if ($_GET['type'] == 'desc') {
        $name_order_type = 'asc';
    }
    $sort = ' ORDER BY name '.$name_order_type;
} elseif ($sort == 'added') {
    if ($_GET['type'] == 'desc') {
        $added_order_type = 'asc';
    }
    $sort = ' ORDER BY added '.$added_order_type;
} elseif ($sort == 'comments') {
    if ($_GET['type'] == 'desc') {
        $comments_order_type = 'asc';
    }
    $sort = ' ORDER BY comments '.$comments_order_type;
} elseif ($sort == 'v_res') {
    if ($_GET['type'] == 'desc') {
        $v_res_order_type = 'asc';
    }
    $sort = ' ORDER BY (yeah - against) '.$v_res_order_type;
}

if ($offerorid != null) {
    if (($categ != null) && ($categ != 0)) {
        $categ = 'WHERE offers.category = '.$categ.' AND offers.userid = '.$offerorid;
    } else {
        $categ = 'WHERE offers.userid = '.$offerorid;
    }
} elseif ($categ == 0) {
    $categ = '';
} else {
    $categ = 'WHERE offers.category = '.$categ;
}

$countRows = NexusDB::select("SELECT count(offers.id) AS cnt FROM offers inner join categories on offers.category = categories.id inner join users on offers.userid = users.id  $categ $search");
$count = (int) ($countRows[0]['cnt'] ?? 0);

$perpage = 25;

[$pagertop, $pagerbottom, $limit] = pager($perpage, $count, $_SERVER['PHP_SELF'].'?'.'category='.($_GET['category'] ?? '').'&sort='.($_GET['sort'] ?? '').'&');

// stderr("", $sort);
if ($sort == '') {
    $sort = 'ORDER BY added desc ';
}

$offerRows = NexusDB::select("SELECT offers.id, offers.userid, offers.name, offers.added, offers.allowedtime, offers.comments, offers.yeah, offers.against, offers.category as cat_id, offers.allowed, categories.image, categories.name as cat FROM offers inner join categories on offers.category = categories.id $categ $search $sort $limit");
$num = count($offerRows);

stdhead($lang_offers['head_offers']);
begin_main_frame();
begin_frame($lang_offers['text_offers_section'], true, 10, '100%', 'center');

echo '<p align="left"><b><font size="5">'.$lang_offers['text_rules']."</font></b></p>\n";
echo '<div align="left"><ul>';
echo '<li>'.$lang_offers['text_rule_one_one'].get_user_class_name($upload_class, false, true, true).$lang_offers['text_rule_one_two'].get_user_class_name($addoffer_class, false, true, true).$lang_offers['text_rule_one_three']."</li>\n";
$offerSkipApprovedCount = get_setting('main.offer_skip_approved_count');
if (is_numeric($offerSkipApprovedCount) && $offerSkipApprovedCount > 0) {
    echo '<li>'.sprintf($lang_offers['text_rule_skip_offer'], $offerSkipApprovedCount)."</li>\n";
}
echo '<li>'.$lang_offers['text_rule_two_one'].'<b>'.$minoffervotes.'</b>'.$lang_offers['text_rule_two_two']."</li>\n";
if ($offervotetimeout_main) {
    echo '<li>'.$lang_offers['text_rule_three_one'].'<b>'.($offervotetimeout_main / 3600).'</b>'.$lang_offers['text_rule_three_two']."</li>\n";
}
if ($offeruptimeout_main) {
    echo '<li>'.$lang_offers['text_rule_four_one'].'<b>'.($offeruptimeout_main / 3600).'</b>'.$lang_offers['text_rule_four_two']."</li>\n";
}
echo '</ul></div>';
if (user_can('addoffer')) {
    echo '<div align="center" style="margin-bottom: 8px;"><a href="?add_offer=1">'.
    '<b>'.$lang_offers['text_add_offer'].'</b></a></div>';
}
echo '<div align="center"><form method="get" action="?">'.$lang_offers['text_search_offers'].'&nbsp;&nbsp;<input type="text" id="specialboxg" name="search" />&nbsp;&nbsp;';
$cats = genrelist($browsecatmode);
$catdropdown = '';
foreach ($cats as $cat) {
    $catdropdown .= '<option value="'.$cat['id'].'"';
    $catdropdown .= '>'.htmlspecialchars($cat['name'])."</option>\n";
}
echo '<select name="category"><option value="0">'.$lang_offers['select_show_all'].'</option>'.$catdropdown.'</select>&nbsp;&nbsp;<input type="submit" class="btn" value="'.$lang_offers['submit_search'].'" /></form></div>';
end_frame();
echo '<br /><br />';

$last_offer = strtotime($CURUSER['last_offer']);
if (! $num) {
    stdmsg($lang_offers['text_nothing_found'], $lang_offers['text_nothing_found']);
} else {
    $catid = $_GET['category'];
    echo '<table class="torrents" cellspacing="0" cellpadding="5" width="100%">';
    echo '<tr><td class="colhead" style="padding: 0px"><a href="?category='.$catid.'&amp;sort=cat&amp;type='.$cat_order_type.'">'.$lang_offers['col_type'].'</a></td>'.
'<td class="colhead" width="100%"><a href="?category='.$catid.'&amp;sort=name&amp;type='.$name_order_type.'">'.$lang_offers['col_title'].'</a></td>'.
'<td colspan="3" class="colhead"><a href="?category='.$catid.'&amp;sort=v_res&amp;type='.$v_res_order_type.'">'.$lang_offers['col_vote_results'].'</a></td>'.
'<td class="colhead"><a href="?category='.$catid.'&amp;sort=comments&amp;type='.$comments_order_type.'"><img class="comments" src="pic/trans.gif" alt="comments" title="'.$lang_offers['title_comment'].'" />'.$lang_offers['col_comment'].'</a></td>'.
'<td class="colhead"><a href="?category='.$catid.'&amp;sort=added&amp;type='.$added_order_type.'"><img class="time" src="pic/trans.gif" alt="time" title="'.$lang_offers['title_time_added'].'" /></a></td>';
    if ($offervotetimeout_main > 0 && $offeruptimeout_main > 0) {
        echo '<td class="colhead">'.$lang_offers['col_timeout'].'</td>';
    }
    echo '<td class="colhead">'.$lang_offers['col_offered_by'].'</td>'.
    (user_can('offermanage') ? '<td class="colhead">'.$lang_offers['col_act'].'</td>' : '')."</tr>\n";
    $i = 0;
    foreach ($offerRows as $arr) {

        $addedby = get_username($arr['userid']);
        $comms = $arr['comments'];
        if ($comms == 0) {
            $comment = '<a href="comment.php?action=add&amp;pid='.$arr['id'].'&amp;type=offer" title="'.$lang_offers['title_add_comments'].'">0</a>';
        } else {
            if (! $lastcom = $Cache->get_value('offer_'.$arr['id'].'_last_comment_content')) {
                $lastcomRows = NexusDB::select('SELECT user, added, text FROM comments WHERE offer = '.(int) $arr['id'].' ORDER BY added DESC LIMIT 1');
                $lastcom = $lastcomRows[0] ?? [];
                $Cache->cache_value('offer_'.$arr['id'].'_last_comment_content', $lastcom, 1855);
            }
            $timestamp = strtotime($lastcom['added']);
            $hasnewcom = ($lastcom['user'] != $CURUSER['id'] && $timestamp >= $last_offer);
            if ($CURUSER['showlastcom'] != 'no') {
                if ($lastcom) {
                    $title = '';
                    if ($CURUSER['timetype'] != 'timealive') {
                        $lastcomtime = $lang_offers['text_at_time'].$lastcom['added'];
                    } else {
                        $lastcomtime = $lang_offers['text_blank'].gettime($lastcom['added'], true, false, true);
                    }
                    $counter = $i;
                    $lastcom_tooltip[$counter]['id'] = 'lastcom_'.$counter;
                    $lastcom_tooltip[$counter]['content'] = ($hasnewcom ? "<b>(<font class='new'>".$lang_offers['text_new'].'</font>)</b> ' : '').$lang_offers['text_last_commented_by'].get_username($lastcom['user']).$lastcomtime.'<br />'.format_comment(mb_substr($lastcom['text'], 0, 100, 'UTF-8').(mb_strlen($lastcom['text'], 'UTF-8') > 100 ? ' ......' : ''), true, false, false, true, 600, false, false);
                    $onmouseover = "onmouseover=\"domTT_activate(this, event, 'content', document.getElementById('".$lastcom_tooltip[$counter]['id']."'), 'trail', false, 'delay', 500,'lifetime',3000,'fade','both','styleClass','niceTitle','fadeMax', 87,'maxWidth', 400);\"";
                }
            } else {
                $title = ' title="'.($hasnewcom ? $lang_offers['title_has_new_comment'] : $lang_offers['title_no_new_comment']).'"';
                $onmouseover = '';
            }
            $comment = '<b><a'.$title.' href="?id='.$arr['id'].'&amp;off_details=1#startcomments" '.$onmouseover.'>'.($hasnewcom ? "<font class='new'>" : '').$comms.($hasnewcom ? '</font>' : '').'</a></b>';
        }

        // ==== if you want allow deny for offers use this next bit
        if ($arr['allowed'] == 'allowed') {
            $allowed = '&nbsp;<b>[<font color="green">'.$lang_offers['text_allowed'].'</font>]</b>';
        } elseif ($arr['allowed'] == 'denied') {
            $allowed = '&nbsp;<b>[<font color="red">'.$lang_offers['text_denied'].'</font>]</b>';
        } else {
            $allowed = '&nbsp;<b>[<font color="orange">'.$lang_offers['text_pending'].'</font>]</b>';
        }
        // ===end

        if ($arr['yeah'] == 0) {
            $zvote = $arr['yeah'];
        } else {
            $zvote = '<b><a href="?id='.$arr['id'].'&amp;offer_vote=1">'.$arr['yeah'].'</a></b>';
        }
        if ($arr['against'] == 0) {
            $pvote = $arr['against'];
        } else {
            $pvote = '<b><a href="?id='.$arr['id'].'&amp;offer_vote=1">'.$arr['against'].'</a></b>';
        }

        if ($arr['yeah'] == 0 && $arr['against'] == 0) {
            $v_res = '0';
        } else {

            $v_res = '<b><a href="?id='.$arr['id'].'&amp;offer_vote=1" title="'.$lang_offers['title_show_vote_details'].'"><font color="green">'.$arr['yeah'].'</font> - <font color="red">'.$arr['against'].'</font> = '.($arr['yeah'] - $arr['against']).'</a></b>';
        }
        $addtime = gettime($arr['added'], false, true);
        $dispname = $arr['name'];
        $count_dispname = mb_strlen($arr['name'], 'UTF-8');
        $max_length_of_offer_name = 70;
        if ($count_dispname > $max_length_of_offer_name) {
            $dispname = mb_substr($dispname, 0, $max_length_of_offer_name - 2, 'UTF-8').'..';
        }
        echo '<tr><td class="rowfollow" style="padding: 0px"><a href="?category='.$arr['cat_id'].'">'.return_category_image($arr['cat_id'], '')."</a></td><td style='text-align: left'><a href=\"?id=".$arr['id'].'&amp;off_details=1" title="'.htmlspecialchars($arr['name']).'"><b>'.htmlspecialchars($dispname).'</b></a>'.($CURUSER['appendnew'] != 'no' && strtotime($arr['added']) >= $last_offer ? "<b> (<font class='new'>".$lang_offers['text_new'].'</font>)</b>' : '').$allowed."</td><td class=\"rowfollow nowrap\" style='padding: 5px' align=\"center\">".$v_res.'</td><td class="rowfollow nowrap" '.(! user_can('againstoffer') ? ' colspan="2" ' : '')." style='padding: 5px'><a href=\"?id=".$arr['id'].'&amp;vote=yeah" title="'.$lang_offers['title_i_want_this'].'"><font color="green"><b>'.$lang_offers['text_yep'].'</b></font></a></td>'.(get_user_class() >= $againstoffer_class ? '<td class="rowfollow nowrap" align="center"><a href="?id='.$arr['id'].'&amp;vote=against" title="'.$lang_offers['title_do_not_want_it'].'"><font color="red"><b>'.$lang_offers['text_nah'].'</b></font></a></td>' : '');

        echo '<td class="rowfollow">'.$comment.'</td><td class="rowfollow nowrap">'.$addtime.'</td>';
        if ($offervotetimeout_main > 0 && $offeruptimeout_main > 0) {
            if ($arr['allowed'] == 'allowed') {
                $futuretime = strtotime($arr['allowedtime']) + $offeruptimeout_main;
                $timeout = gettime(date('Y-m-d H:i:s', $futuretime), false, true, true, false, true);
            } elseif ($arr['allowed'] == 'pending') {
                $futuretime = strtotime($arr['added']) + $offervotetimeout_main;
                $timeout = gettime(date('Y-m-d H:i:s', $futuretime), false, true, true, false, true);
            }
            if (! $timeout) {
                $timeout = 'N/A';
            }
            echo '<td class="rowfollow nowrap">'.$timeout.'</td>';
        }
        echo '<td class="rowfollow">'.$addedby.'</td>'.(user_can('offermanage') ? '<td class="rowfollow"><a href="?id='.$arr['id'].'&amp;del_offer=1"><img class="staff_delete" src="pic/trans.gif" alt="D" title="'.$lang_offers['title_delete'].'" /></a><br /><a href="?id='.$arr['id'].'&amp;edit_offer=1"><img class="staff_edit" src="pic/trans.gif" alt="E" title="'.$lang_offers['title_edit'].'" /></a></td>' : '').'</tr>';
        $i++;
    }
    echo "</table>\n";
    echo $pagerbottom;
    if (! isset($CURUSER) || $CURUSER['showlastcom'] == 'yes') {
        create_tooltip_container($lastcom_tooltip, 400);
    }
}
end_main_frame();
$USERUPDATESET['last_offer'] = date('Y-m-d H:i:s');
stdfoot();

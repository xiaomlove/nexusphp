<?php

use App\Models\Message;
use App\Models\StaffMessage;
use App\Repositories\MessageRepository;
use App\Repositories\ToolRepository;
use Nexus\Database\NexusDB;

require '../include/bittorrent.php';
dbconn();
require_once get_langfile_path();
loggedinorreturn();

$action = $_GET['action'] ?? '';

function can_access_staff_message($msg)
{
    global $CURUSER;
    if (user_can('staffmem')) {
        return true;
    }
    if (is_numeric($msg)) {
        $msg = StaffMessage::query()->findOrFail($msg)->toArray();
    }
    if (empty($msg['permission']) || ! in_array($msg['permission'], ToolRepository::listUserAllPermissions($CURUSER['id']))) {
        permissiondenied(get_setting('authority.staffmem'));
    }
}

// /////////////////////////
//        SHOW PM'S        //
// ///////////////////////

if (! $action) {
    stdhead($lang_staffbox['head_staff_pm']);
    $url = $_SERVER['PHP_SELF'].'?';
    $query = MessageRepository::buildStaffMessageQuery($CURUSER['id']);
    $count = $query->count();
    $perpage = 20;
    [$pagertop, $pagerbottom, $limit, $offset, $pageSize, $pageNum] = pager($perpage, $count, $url);
    echo '<h1 align=center>'.$lang_staffbox['text_staff_pm'].'</h1>';
    if ($count == 0) {
        do_log(last_query());
        stdmsg($lang_staffbox['std_sorry'], $lang_staffbox['std_no_messages_yet']);
    } else {
        begin_main_frame();
        echo '<form method=post action="?action=takecontactanswered">';
        echo "<table width=940 border=1 cellspacing=0 cellpadding=5 align=center>\n";
        echo '<tr>
			<td class=colhead align=left>'.$lang_staffbox['col_subject'].'</td>
			<td class=colhead align=center>'.$lang_staffbox['col_sender'].'</td>
			<td class=colhead align=center><nobr>'.$lang_staffbox['col_added'].'</nobr></td>
			<td class=colhead align=center>'.$lang_staffbox['col_answered'].'</td>
			<td class=colhead align=center><nobr>'.$lang_staffbox['col_action'].'</nobr></td>
		</tr>';

        $res = $query->forPage($pageNum + 1, $perpage)->orderBy('id', 'desc')->get()->toArray();
        do_log(last_query());
        foreach ($res as $arr) {
            if ($arr['answered']) {
                $answered = '<nobr><font color=green>'.$lang_staffbox['text_yes'].'</font> - '.get_username($arr['answeredby']).'</nobr>';
            } else {
                $answered = '<font color=red>'.$lang_staffbox['text_no'].'</font>';
            }

            $pmid = $arr['id'];
            echo "<tr><td width=100% class=rowfollow align=left><a href=staffbox.php?action=viewpm&pmid=$pmid&return=".urlencode($_SERVER['QUERY_STRING']).'>'.htmlspecialchars($arr['subject']).'</td><td class=rowfollow align=center>'.get_username($arr['sender']).'</td><td class=rowfollow align=center><nobr>'.gettime($arr['added'], true, false)."</nobr></td><td class=rowfollow align=center>$answered</td><td class=rowfollow align=center><input type=\"checkbox\" name=\"setanswered[]\" value=\"".$arr['id']."\" /></td></tr>\n";
        }
        $checkAll = $lang_functions['input_check_all'];
        $uncheckAll = $lang_functions['input_uncheck_all'];
        echo "<tr><td class=rowfollow align=right colspan=5><input type=\"button\" value=\"$checkAll\" onclick=\"this.value=check(form, '$checkAll', '$uncheckAll')\"/><input type=\"submit\" name=\"setdealt\" value=\"".$lang_staffbox['submit_set_answered'].'" /><input type="submit" name="delete" value="'.$lang_staffbox['submit_delete'].'" /></td></tr>';
        echo "</table>\n";
        echo '</form>';
        echo $pagerbottom;
        end_main_frame();
    }
    stdfoot();
}

// ////////////////////////
//        VIEW PM'S        //
// ////////////////////////

if ($action == 'viewpm') {
    $pmid = intval($_GET['pmid'] ?? 0);

    $ress4Rows = NexusDB::select('SELECT * FROM staffmessages WHERE id = '.(int) $pmid);
    $arr4 = $ress4Rows[0] ?? [];
    can_access_staff_message($arr4);
    $answeredby = get_username($arr4['answeredby']);

    if (is_valid_id($arr4['sender'])) {
        $sender = get_username($arr4['sender']);
    } else {
        $sender = $lang_staffbox['text_system'];
    }

    $subject = htmlspecialchars($arr4['subject']);
    if ($arr4['answered'] == 1) {
        $colspan = '3';
        $width = '33';
    } else {
        $colspan = '2';
        $width = '50';
    }
    stdhead($lang_staffbox['head_view_staff_pm']);
    echo '<h1 align="center"><a class="faqlink" href="staffbox.php">'.$lang_staffbox['text_staff_pm'].'</a>-->'.$subject.'</h1>';
    echo '<table width="737" border="0" cellpadding="4" cellspacing="0">';
    echo '<tr><td width="'.$width.'%" class="colhead" align="left">'.$lang_staffbox['col_from'].'</td>';
    if ($arr4['answered'] == 1) {
        echo '<td width="34%" class="colhead" align="left">'.$lang_staffbox['col_answered_by'].'</td>';
    }
    echo '<td width="'.$width.'%" class="colhead" align="left">'.$lang_staffbox['col_date'].'</td></tr>';
    echo '<tr><td class="rowfollow" align="left">'.$sender.'</td>';
    if ($arr4['answered'] == 1) {
        echo '<td class="rowfollow" align="left">'.$answeredby.'</td>';
    }
    echo '<td class="rowfollow" align="left">'.gettime($arr4['added']).'</td></tr>';
    echo '<tr><td colspan="'.$colspan.'" align="left">'.format_comment($arr4['msg']).'</td></tr>';
    if ($arr4['answered'] == 1 && $arr4['answer']) {
        echo '<tr><td colspan="'.$colspan.'" align="left">'.format_comment($arr4['answer']).'</td></tr>';
    }
    echo '<tr><td colspan="'.$colspan.'" align="right">';
    echo '<font color=white>';
    if ($arr4['answered'] == 0) {
        echo '[ <a href="staffbox.php?action=answermessage&receiver='.$arr4['sender'].'&answeringto='.$arr4['id'].'">'.$lang_staffbox['text_reply'].'</a> ] [ <a href="staffbox.php?action=setanswered&id='.$arr4['id'].'&return='.urlencode($_GET['return'] ?? '').'">'.$lang_staffbox['text_mark_answered'].'</a> ] ';
    }
    echo '[ <a href="staffbox.php?action=deletestaffmessage&id='.$arr4['id'].'">'.$lang_staffbox['text_delete'].'</a> ]';
    echo '</font>';
    echo '</td></tr>';
    echo '</table>';
    stdfoot();
}
// ////////////////////////
//        ANSWER MESSAGE        //
// ////////////////////////

if ($action == 'answermessage') {
    $answeringto = intval($_GET['answeringto'] ?? 0);
    $receiver = intval($_GET['receiver'] ?? 0);

    int_check($receiver, true);

    $userRows = NexusDB::select('SELECT * FROM users WHERE id = '.(int) $receiver);
    $user = $userRows[0] ?? null;

    if (! $user) {
        stderr($lang_staffbox['std_error'], $lang_staffbox['std_no_user_id']);
    }

    $staffMsgRows = NexusDB::select('SELECT * FROM staffmessages WHERE id = '.(int) $answeringto);
    $staffmsg = $staffMsgRows[0] ?? [];

    can_access_staff_message($staffmsg);

    stdhead($lang_staffbox['head_answer_to_staff_pm']);
    begin_main_frame();
    ?>
	<form method="post" id="compose" name="message" action="?action=takeanswer">
<?php if ($_GET['returnto'] || $_SERVER['HTTP_REFERER']) { ?>
        <input type=hidden name=returnto value="<?php echo htmlspecialchars($_GET['returnto'] ?? '') ? htmlspecialchars($_GET['returnto']) : htmlspecialchars($_SERVER['HTTP_REFERER'])?>">
<?php } ?>
        <input type=hidden name=receiver value=<?php echo $receiver?>>
        <input type=hidden name=answeringto value=<?php echo $answeringto?>>
<?php
    $title = $lang_staffbox['text_answering_to'].'<a href="staffbox.php?action=viewpm&pmid='.$staffmsg['id'].'">'.htmlspecialchars($staffmsg['subject']).'</a>'.$lang_staffbox['text_sent_by'].get_username($staffmsg['sender']);
    begin_compose($title, 'reply', '', false);
    end_compose();
    echo '</form>';
    end_main_frame();
    stdfoot();
}

// ////////////////////////
//        TAKE ANSWER        //
// ////////////////////////
if ($action == 'takeanswer') {
    if ($_SERVER['REQUEST_METHOD'] != 'POST') {
        exit();
    }

    $receiver = intval($_POST['receiver'] ?? 0);
    $answeringto = $_POST['answeringto'];

    int_check($receiver, true);

    $userid = $CURUSER['id'];

    $msg = trim($_POST['body']);

    $added = "'".date('Y-m-d H:i:s')."'";

    if (! $msg) {
        stderr($lang_staffbox['std_error'], $lang_staffbox['std_body_is_empty']);
    }

    can_access_staff_message($answeringto);

    $subject = StaffMessage::query()->findOrFail($answeringto)->toArray()['subject'];

    Message::add([
        'sender' => $userid,
        'receiver' => $receiver,
        'subject' => $subject,
        'added' => now(),
        'msg' => $msg,
    ]);

    NexusDB::table('staffmessages')
        ->where('id', (int) $answeringto)
        ->update([
            'answer' => (string) $msg,
            'answered' => '1',
            'answeredby' => (int) $userid,
        ]);
    $Cache->delete_value('staff_new_message_count');
    clear_staff_message_cache();
    header("Location: staffbox.php?action=viewpm&pmid=$answeringto");
    exit;
}
// ////////////////////////
// DELETE STAFF MESSAGE        //
// ////////////////////////

if ($action == 'deletestaffmessage') {

    $id = intval($_GET['id'] ?? 0);

    if (! is_numeric($id) || $id < 1 || floor($id) != $id) {
        exit;
    }

    can_access_staff_message($id);
    NexusDB::statement('DELETE FROM staffmessages WHERE id = '.(int) $id);
    $Cache->delete_value('staff_message_count');
    $Cache->delete_value('staff_new_message_count');
    clear_staff_message_cache();
    header('Location: '.get_protocol_prefix()."$BASEURL/staffbox.php");
}

// ////////////////////////
// MARK AS ANSWERED        //
// ////////////////////////

if ($action == 'setanswered') {

    $id = intval($_GET['id'] ?? 0);
    can_access_staff_message($id);
    NexusDB::statement('UPDATE staffmessages SET answered = 1, answeredby = '.(int) $CURUSER['id'].' WHERE id = '.(int) $id);
    $Cache->delete_value('staff_new_message_count');
    clear_staff_message_cache();
    header('Location: staffbox.php'.(! empty($_GET['return']) ? '?'.$_GET['return'] : ''));
}

// ////////////////////////
// MARK AS ANSWERED #2        //
// ////////////////////////

if ($action == 'takecontactanswered') {
    if (empty($_POST['setanswered'])) {
        stderr($lang_staffbox['std_sorry'], nexus_trans('nexus.select_one_please'));
    }

    if ($_POST['setdealt']) {
        $idList = implode(', ', array_map('intval', $_POST['setanswered']));
        foreach (NexusDB::select('SELECT * FROM staffmessages WHERE answered = 0 AND id IN ('.$idList.')') as $arr) {
            can_access_staff_message($arr);
            NexusDB::statement('UPDATE staffmessages SET answered = 1, answeredby = '.(int) $CURUSER['id'].' WHERE id = '.(int) $arr['id']);
        }
    } elseif ($_POST['delete']) {
        $idList = implode(', ', array_map('intval', $_POST['setanswered']));
        foreach (NexusDB::select('SELECT * FROM staffmessages WHERE id IN ('.$idList.')') as $arr) {
            can_access_staff_message($arr);
            NexusDB::statement('DELETE FROM staffmessages WHERE id = '.(int) $arr['id']);
        }
    }
    $Cache->delete_value('staff_new_message_count');
    clear_staff_message_cache();
    header('Location: staffbox.php');
}

?>

<?php

use App\Events\RequestFulfilled;
use App\Events\RequestSupplied;
use App\Models\Message;
use Nexus\Database\NexusDB;

require '../include/bittorrent.php';
dbconn();
require_once get_langfile_path();
require_once get_langfile_path('details.php');
loggedinorreturn();
parked();

if (isset($_GET['id'])) {
    $_GET['id'] = intval($_GET['id'] ?? 0);
}
$action = isset($_POST['action']) ? htmlspecialchars($_POST['action']) : (isset($_GET['action']) ? htmlspecialchars($_GET['action']) : '');
$allowed_actions = ['list', 'new', 'newmessage', 'view', 'edit', 'takeedit', 'takeadded', 'res', 'takeres', 'addamount', 'delete', 'confirm', 'message', 'search'];
if (! $action) {
    if (! empty($_GET['id'])) {
        $action = 'view';
    } else {
        $action = 'list';
    }
}
if (! in_array($action, $allowed_actions)) {
    $action = 'list';
} else {
    $limitorder = $limit = '';
    switch ($action) {
        case 'list':

            $finished = $_REQUEST['finished'] ?? '';
            $finishedlimit = isset($_GET['finished']) ? 'finished='.$_GET['finished'].'&' : '';
            $allowed_finished = ['yes', 'no', 'all', 'ing', 'my'];
            switch ($finished) {
                case 'yes':

                    $limit = "finish = 'yes'";
                    break;

                case 'no':

                    $limit = "finish = 'no'";
                    break;

                case 'all':

                    $limit = '1';
                    break;

                case 'my':

                    $limit = '1 and userid='.$CURUSER['id'];
                    break;

                case 'ing':

                    $limit = "(SELECT count(DISTINCT torrentid) FROM resreq  where reqid=requests.id )>=1 and finish = 'no'";
                    break;

                default:

                    $limit = "finish = 'no'";
                    break;

            }
            // if (!in_array($finished, $allowed_finished)){$limit = "finish = 'no'";(get_user_class() >= UC_UPLOADER?$limitorder="Totalreq DESC ,":"");}
            // else $limit = ( $finished=="all" ? "1" : ( $finished=="all" ? "1" : "finish ='".$finished."'"));

            if (! empty($_POST['query'])) {
                $likeQuoted = NexusDB::getPdo()->quote('%'.$_POST['query'].'%');
                $limit = $limit.' and (request like '.$likeQuoted.' or descr like '.$likeQuoted.')';
            }

            $rows = NexusDB::select('SELECT  requests.*  FROM requests WHERE '.$limit.' ORDER BY id DESC');
            [$pagertop, $pagerbottom, $limit2] = pager(20, count($rows), "?$finishedlimit");

            stdhead($lang_viewrequests['page_title']);

            $rows = NexusDB::select('SELECT requests.* ,(SELECT count(DISTINCT torrentid) FROM resreq  where reqid=requests.id ) as Totalreq FROM requests WHERE '.$limit." ORDER BY $limitorder id DESC $limit2");
            echo "<h1 align=center>{$lang_viewrequests['page_title']}</h1>";
            echo "<br><b><a href='viewrequests.php?action=new'>{$lang_viewrequests['add_request']}</a> | <a href='viewrequests.php?finished=all'>{$lang_viewrequests['view_request_all']}</a> | <a href='viewrequests.php?finished=yes'>{$lang_viewrequests['view_request_resolved']}</a> | <a href='viewrequests.php?finished=no'>{$lang_viewrequests['view_request_unresolved']}</a> | <a href='viewrequests.php?finished=ing'>{$lang_viewrequests['view_request_resolving']}</a> | <a href='viewrequests.php?finished=my' ".get_requestcount().">{$lang_viewrequests['view_request_my']}</a></b><p>\n";
            echo "<table width=98% border=1 cellspacing=0 cellpadding=5 style=border-collapse:collapse >\n";

            if (count($rows) == 0) {
                echo "<tr><td class=colhead align=center>Nothing</td></tr>\n";
            } else {
                echo "<tr><td class=colhead align=left>{$lang_viewrequests['thead_name']}</td><td class=colhead align=center>{$lang_viewrequests['thead_price_newest']}</td><td class=colhead align=center>{$lang_viewrequests['thead_price_original']}</td><td class=colhead  align=center>{$lang_viewrequests['thead_comment_count']}</td><td class=colhead  align=center>{$lang_viewrequests['thead_on_request_count']}</td><td class=colhead align=center>{$lang_viewrequests['thead_request_user']}</td><td class=colhead align=center>{$lang_viewrequests['thead_created_at']}</td><td class=colhead align=center>{$lang_viewrequests['thead_status']}</td></tr>\n";
                foreach ($rows as $row) {
                    echo "<tr>
                                <td align=left class='rowfollow'><a href='viewrequests.php?action=view&id=".$row['id']."'><b>".$row['request']."</b></a></td>
                                <td align=center class='rowfollow nowrap'><font color=#ff0000><b>".$row['amount']."</b></font></td>
                                <td align=center class='rowfollow nowrap'>".$row['ori_amount']."</td>
                                <td align=center class='rowfollow nowrap'>".($row['comments'])."</td>
                                <td align=center class='rowfollow nowrap'>".($row['Totalreq'])."</td>
                                <td align=center class='rowfollow nowrap'>".get_username($row['userid'])."</td>
                                <td align=center class='rowfollow nowrap'>".gettime($row['added'], true, false)."</td>
                                <td align=center class='rowfollow nowrap'>".($row['finish'] == 'yes' ? $lang_viewrequests['request_status_resolved'] : ($row['userid'] == $CURUSER['id'] ? $lang_viewrequests['request_status_resolving'] : "<a href='viewrequests.php?action=res&id=".$row['id']."'>{$lang_viewrequests['request_status_resolving']}</a>"))."</td>
                            </tr>\n";
                }
            }
            echo "</table>\n";
            echo $pagerbottom;
            // print("<br><b><a href=viewrequests.php?action=new>添加</a> <a href=viewrequests.php?finished=all>查看所有</a> <a href=viewrequests.php?finished=yes>查看已解决</a> <a href=viewrequests.php?finished=no>查看未解决</a></b>\n");
            echo "<table border=1 cellspacing=0  cellpadding=5>\n";
            echo "<tr><td class=toolbox align=left><form  method=\"post\" action='viewrequests.php'>\n";
            echo "<input type=\"text\" name=\"query\" style=\"width:500px\" >\n";
            echo "<input type=\"hidden\" name=\"action\" value='list'>";
            echo "<input type=\"hidden\" name=\"finished\" value='all'>";

            echo "<input type=submit value='{$lang_viewrequests['action_search']}'></form>\n";
            echo "</td></tr></table><br />\n";

            stdfoot();

            exit;
            break;

        case 'view':

            if (is_numeric($_GET['id'])) {
                $id = $_GET['id'];
                $reqRows = NexusDB::select('SELECT * FROM requests WHERE id = '.(int) $_GET['id']);
                if (count($reqRows) == 0) {
                    stderr($lang_functions['std_error'], $lang_functions['std_target_not_exists']);
                } else {
                    $arr = $reqRows[0];
                }
                stdhead($lang_viewrequests['page_title']);
                echo "<h1 align=center id=top>{$lang_viewrequests['request']}-".htmlspecialchars($arr['request'])."</h1>\n";
                echo "<table width=100% cellspacing=0 cellpadding=5>\n";
                $resRows = NexusDB::select('SELECT * FROM resreq WHERE reqid = '.(int) $_GET['id'].$limit);
                tr($lang_viewrequests['basic_info'], get_username($arr['userid']).$lang_viewrequests['created_at'].gettime($arr['added'], true, false)."\n", 1);
                tr($lang_viewrequests['reward'], $lang_viewrequests['newest_bidding'].$arr['amount']."     {$lang_viewrequests['original_bidding']}".$arr['ori_amount']."\n", 1);
                tr($lang_functions['std_action'], "<a href='report.php?reportrequestid=".$id."' >{$lang_functions['std_report']}</a>".
                    (($arr['userid'] == $CURUSER['id'] || get_user_class() >= UC_UPLOADER) && $arr['finish'] == 'no' ? " | <a href='viewrequests.php?action=edit&id=".$id."' >{$lang_functions['title_edit']}</a>" : '')."\n".
                    ($arr['userid'] == $CURUSER['id'] || $arr['finish'] == 'yes' ? '' : " | <a href='viewrequests.php?action=res&id=".$id."' >{$lang_viewrequests['on_request']}</a>\n").
                    ((get_user_class() >= UC_UPLOADER || $arr['userid'] == $CURUSER['id']) && $arr['finish'] == 'no' ? " | <a href='viewrequests.php?action=delete&id=".$id."' ".(count($resRows) ? ">{$lang_functions['title_delete']}" : "title='{$lang_viewrequests['recycle_title']}'>{$lang_viewrequests['recycle']}").'</a>' : '')."\n", 1);
                if ($arr['finish'] == 'no') {
                    tr($lang_viewrequests['add_reward'], '<form action=viewrequests.php method=post> <input type=hidden name=action value=addamount><input type=hidden name=reqid value='.$arr['id']."><input size=6 name=amount value=1000 ><input type=submit value={$lang_functions['submit_submit']} > {$lang_viewrequests['add_reward_desc']}</form>", 1);
                }
                tr($lang_functions['std_desc'], format_comment(unesc($arr['descr'])), 1);
                $limit = ($arr['finish'] == 'no' ? '' : " AND chosen = 'yes' ");
                $ress = '';
                if (count($resRows) == 0) {
                    $ress = $lang_viewrequests['no_request_yet'];
                } else {
                    if ($arr['userid'] == $CURUSER['id'] || get_user_class() >= UC_UPLOADER) {
                        $ress .= "<form action=viewrequests.php method=post>\n<input type=hidden name=action value=confirm > <input type=hidden name=id value=".$id." >\n";
                    }
                    foreach ($resRows as $row) {
                        $torRows = NexusDB::select('SELECT * FROM torrents WHERE id = '.(int) $row['torrentid']);
                        $each = $torRows[0] ?? [];
                        if (count($torRows) == 1) {
                            $ress .= (($arr['userid'] == $CURUSER['id'] || get_user_class() >= UC_UPLOADER) && $arr['finish'] == 'no' ? '<input type=checkbox name=torrentid[] value='.$each['id'].'>' : '')."<a href='details.php?id=".$each['id']."&hit=1' >".$each['name'].'</a> '.($arr['finish'] == 'no' ? '' : 'by '.get_username($each['owner']))."<br/>\n";
                        }
                    }
                    $ress .= '';

                    if (($arr['userid'] == $CURUSER['id'] || get_user_class() >= UC_UPLOADER) && $arr['finish'] == 'no') {
                        $ress .= "<input type=submit value={$lang_viewrequests['btn_select_text']}>\n";
                    }
                    $ress .= "</form>\n";
                }
                tr($lang_viewrequests['request'], $ress, 1);
                echo "</table><br/><br/>\n";

                $count = (int) NexusDB::table('comments')->where('request', (int) $_GET['id'])->count();
                if ($count) {
                    echo '<br /><br />';
                    echo "<h1 align=\"center\" id=\"startcomments\">{$lang_functions['std_comment']}</h1>\n";
                    [$pagertop, $pagerbottom, $limit] = pager(10, $count, 'viewrequests.php?action=view&id='.$_GET['id'].'&', ['lastpagedefault' => 1], 'page');

                    $allrows = NexusDB::select('SELECT * FROM comments WHERE request = '.(int) $_GET['id']." ORDER BY id $limit");
                    echo $pagertop;
                    commenttable($allrows, 'request', $_GET['id']);
                    echo $pagerbottom;
                }

                echo "
	<table style='border:1px solid #000000;'>
	<tr><td class=\"text\" align=\"center\"><b>".$lang_details['text_quick_comment'].'</b><br /><br />
	<form id="compose" name="comment" method="post" action="'.htmlspecialchars('comment.php?action=add&type=request').'" onsubmit="return postvalid(this);">
	<input type="hidden" name="pid" value="'.$id.'" /><br />';
                quickreply('comment', 'body', $lang_functions['std_quick_comment']);
                echo '</form></td></tr></table>';

                echo "

<a class=\"index\" href='comment.php?action=add&pid=$id&type=request'>{$lang_functions['title_add_comments']}</a></td></tr></table>";

                stdfoot();

            } else {
                stderr($lang_functions['std_error'], $lang_functions['std_target_not_exists']);
            }
            exit;
            break;

        case 'edit':

            if (! is_numeric($_GET['id'])) {
                stderr($lang_functions['std_error'], $lang_functions['std_target_not_exists']);
            }
            $editRows = NexusDB::select('SELECT * FROM requests WHERE id = '.(int) $_GET['id']);
            if (count($editRows) == 0) {
                stderr($lang_functions['std_error'], $lang_functions['std_target_not_exists']);
            }
            $arr = $editRows[0];
            if ($arr['finish'] == 'yes') {
                stderr($lang_functions['std_error'], $lang_viewrequests['request_already_resolved']);
            }
            if ($arr['userid'] == $CURUSER['id'] || get_user_class() >= UC_UPLOADER) {
                stdhead($lang_functions['title_edit'].$lang_viewrequests['request']);
                echo "<form id=edit method=post name=edit action=viewrequests.php >\n
		<input type=hidden name=action  value=takeedit >
		<input type=hidden name=reqid  value=".intval($_GET['id'] ?? 0).' >
		';
                echo "<table width=100% cellspacing=0 cellpadding=3><tr><td class=colhead align=center colspan=2>{$lang_functions['title_edit']}{$lang_viewrequests['request']}</td></tr>";
                tr("{$lang_functions['col_name']}：", '<input name=request value="'.$arr['request'].'" size=134 ><br/>', 1);
                echo "<tr><td class=rowhead align=right valign=top><b>{$lang_functions['std_desc']}：</b></td><td class=rowfollow align=left>";
                textbbcode('edit', 'descr', $arr['descr'], false, 130, true);
                echo '</td></tr>';
                echo "</td></tr><tr><td class=toolbox align=center colspan=2><input id=qr type=submit class=btn value={$lang_functions['text_edit']}{$lang_viewrequests['request']} ></td></tr></table></form><br />\n";
                stdfoot();
                exit;
            } else {
                stderr($lang_functions['std_error'], "{$lang_functioins['std_permission_denied']}<a href='viewrequests.php?action=view&id=".$_GET['id']."'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            }

        case 'new':

            if (get_user_class() >= 1) {
                stdhead($lang_viewrequests['add_request']);
                echo "<form id=edit method=post name=edit action=viewrequests.php >\n<input type=hidden name=action  value=takeadded >\n";
                echo "<table width=100% cellspacing=0 cellpadding=3><tr><td class=colhead align=center colspan=2>{$lang_viewrequests['add_request']}</td></tr>\n";
                tr("{$lang_functions['col_name']}：", '<input name=request size=134><br/>', 1);
                tr("{$lang_viewrequests['reward']}：", "<input name=amount size=11 value=2000>{$lang_viewrequests['add_request_desc']}<br/>", 1);
                echo "<tr><td class=rowhead align=right valign=top><b>{$lang_functions['std_desc']}：</b></td><td class=rowfollow align=left>";
                textbbcode('edit', 'descr', $arr['descr'], false, 130, true);
                echo '</td></tr>';
                echo "<tr><td class=toolbox style=vertical-align: middle; padding-top: 10px; padding-bottom: 10px; align=center colspan=2><input id=qr type=submit value={$lang_viewrequests['add_request']} class=btn /></td></tr></table></form><br />\n";

                stdfoot();
                exit;
            } else {
                stderr($lang_functions['std_error'], "{$lang_functions['std_permission_denied']}<a href='viewrequests.php'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            }

        case 'newmessage':

            stdhead($lang_functions['text_reply']);

            // <input type=hidden name=id value=$id ><br />");
            // quickreply('reply', 'message', "我要留言");
            // print("</form></td></tr></table>");

            $ruserid = 0 + $_GET['userid'];

            echo "<form id=reply name=reply method=post action=viewrequests.php >\n<input type=hidden name=action value=message ><input type=hidden name=id value=".intval($_GET['id'] ?? 0)." >\n";
            echo "<table width=100% cellspacing=0 cellpadding=3>\n";

            echo '<tr><td class=rowfollow align=left>';
            if ($ruserid) {
                textbbcode('reply', 'message', "[b]{$lang_functions['text_reply']}:".get_plain_username($ruserid)."[/b]\n");
                echo "<input id=ruserid type=hidden value=$ruserid />";
            } else {
                textbbcode('reply', 'message');
            }
            echo '</td></tr>';
            echo "</table><input id=qr type=submit value={$lang_functions['title_add_comments']} class=btn /></form><br />\n";

            stdfoot();
            exit;

        case 'search':

            stdhead($lang_functions['text_search']);

            echo "<table border=1 cellspacing=0  cellpadding=5>\n";
            echo "<tr><td class=colhead align=left>{$lang_functions['text_search']}</td></tr>\n";
            echo "<tr><td class=toolbox align=left><form  method=\"post\" action='viewrequests.php'>\n";
            echo "<input type=\"text\" name=\"query\" style=\"width:500px\" >\n";
            echo "<input type=\"hidden\" name=\"action\" value='list'>";
            echo "<input type=submit value='{$lang_functions['text_search']}'></form>\n";
            echo "</td></tr></table><br />\n";

            stdfoot();
            exit;

        case 'takeadded':

            if (! $_POST['descr']) {
                stderr($lang_functions['std_error'], "{$lang_viewrequests['description_required']}<a href='viewrequests.php?action=new'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            }
            if (! $_POST['request']) {
                stderr($lang_functions['std_error'], "{$lang_viewrequests['name_required']}<a href='viewrequests.php?action=new'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            }
            if (! $_POST['amount']) {
                stderr($lang_functions['std_error'], "{$lang_viewrequests['amount_required']}<a href='viewrequests.php?action=new'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            }
            if (! is_numeric($_POST['amount'])) {
                stderr($lang_functions['std_error'], "{$lang_viewrequests['amount_must_be_numeric']}<a href=viewrequests.php?action=new>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            }
            $amount = $_POST['amount'];
            if ($amount < 100) {
                stderr($lang_functions['std_error'], "{$lang_viewrequests['add_request_amount_minimum']}<a href='viewrequests.php?action=new'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            }
            if ($amount > 10000) {
                stderr($lang_functions['std_error'], "{$lang_viewrequests['add_request_amount_maximum']}<a href='viewrequests.php?action=new'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            }
            $amount += 100;
            if ($amount + 100 > $CURUSER['seedbonus']) {
                stderr($lang_functions['std_error'], "{$lang_viewrequests['bouns_not_enough']}<a href='viewrequests.php?action=new'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            }
            if (get_user_class() >= 1) {
                NexusDB::statement('UPDATE users SET seedbonus = seedbonus - '.(int) $amount.' WHERE id = '.(int) $CURUSER['id']);
                $id = (int) NexusDB::insert('requests', [
                    'request' => $_POST['request'],
                    'descr' => $_POST['descr'],
                    'ori_descr' => $_POST['descr'],
                    'amount' => $_POST['amount'],
                    'ori_amount' => $_POST['amount'],
                    'userid' => $CURUSER['id'],
                    'added' => date('Y-m-d H:i:s'),
                ]);
                stderr($lang_functions['std_success'], "{$lang_viewrequests['add_request_success']}，<a href='viewrequests.php?action=view&id=".$id."'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            } else {
                stderr($lang_functions['std_error'], "{$lang_functions['std_permission_denied']}<a href='viewrequests.php'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            }
            exit;
            break;

        case 'takeedit':

            if (! is_numeric($_POST['reqid'])) {
                stderr($lang_functions['std_error'], "{$lang_viewrequests['request_id_must_be_numeric']}<a href='viewrequests.php?action=edit&id=".$_POST['reqid']."'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            }
            $editRows = NexusDB::select('SELECT * FROM requests WHERE id = '.(int) $_POST['reqid']);
            if (! $_POST['descr']) {
                stderr($lang_functions['std_error'], "{$lang_viewrequests['description_required']}<a href='viewrequests.php?action=edit&id=".$_POST['reqid']."'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            }
            if (! $_POST['request']) {
                stderr($lang_functions['std_error'], "{$lang_viewrequests['name_required']}<a href='viewrequests.php?action=edit&id=".$_POST['reqid']."'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            }
            if (count($editRows) == 0) {
                stderr($lang_functions['std_error'], "{$lang_viewrequests['request_deleted']}<a href='viewrequests.php'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            }
            $arr = $editRows[0];
            if ($arr['finish'] == 'yes') {
                stderr($lang_functions['std_error'], "{$lang_viewrequests['request_already_resolved']}<a href='viewrequests.php?action=view&id=".$_POST['reqid']."'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            }
            if ($arr['userid'] == $CURUSER['id'] || get_user_class() >= UC_UPLOADER) {
                NexusDB::table('requests')
                    ->where('id', (int) $_POST['reqid'])
                    ->update([
                        'descr' => (string) $_POST['descr'],
                        'request' => (string) $_POST['request'],
                    ]);
                stderr($lang_functions['std_success'], "{$lang_viewrequests['edit_request_success']}，<a href='viewrequests.php?action=view&id=".$_POST['reqid']."'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            } else {
                stderr($lang_functions['std_error'], "{$lang_functions['std_permission_denied']}<a href='viewrequests.php?action=view&id=".$_POST['reqid']."'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            }
            exit;
            break;

        case 'res':

            stdhead($lang_viewrequests['request']);
            stdmsg($lang_viewrequests['do_request'], '
	<form action=viewrequests.php method=post>
	<input type=hidden name=action value=takeres />
	<input type=hidden name=reqid value="'.$_GET['id']."\" />
	{$lang_viewrequests['type_in_torrent_id']}:".getSchemeAndHttpHost()."/details.php?id=<input type=text name=torrentid size=11/>
	<input type=submit value={$lang_functions['submit_submit']}></form><a href='viewrequests.php?action=view&id=".$_GET['id']."'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            stdfoot();
            exit;
            break;

        case 'takeres':

            if (! is_numeric($_POST['reqid'])) {
                stderr($lang_functions['std_error'], $lang_viewrequests['request_id_must_be_numeric']);
            }
            $reqRows = NexusDB::select('SELECT * FROM requests WHERE id = '.(int) $_POST['reqid']);
            if (count($reqRows) == 0) {
                stderr($lang_functions['std_error'], "{$lang_viewrequests['request_deleted']}<a href='viewrequests.php'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            }
            $arr = $reqRows[0];
            if ($arr['finish'] == 'yes') {
                stderr($lang_functions['std_error'], "{$lang_viewrequests['request_already_resolved']}<a href='viewrequests.php?action=view&id=".$_POST['reqid']."'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            }
            if (! is_numeric($_POST['torrentid'])) {
                stderr($lang_functions['std_error'], "{$lang_viewrequests['request_id_must_be_numeric']}<a href='viewrequests.php?action=res&id=".$_POST['reqid']."'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            }
            $torRows = NexusDB::select('SELECT * FROM torrents WHERE id = '.(int) $_POST['torrentid']);
            if (count($torRows) == 0) {
                stderr($lang_functions['std_error'], "{$lang_functions['std_target_not_exists']}<a href='viewrequests.php?action=res&id=".$_POST['reqid']."'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            }
            $tor = $torRows[0];
            //            if ($tor['last_seed'] == "0000-00-00 00:00:00" || is_null(($tor['last_seed']))) stderr($lang_functions['std_error'], "{$lang_viewrequests['torrent_not_release_yet']}<a href='viewrequests.php?action=res&id=" . $_POST["reqid"] . "'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            if (NexusDB::table('resreq')->where('reqid', (int) $_POST['reqid'])->where('torrentid', (int) $_POST['torrentid'])->count()) {
                stderr($lang_functions['std_error'], "{$lang_viewrequests['supply_already_exists']}<a href='viewrequests.php?action=res&id=".$_POST['reqid']."'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            }
            NexusDB::insert('resreq', [
                'reqid' => (int) $_POST['reqid'],
                'torrentid' => (int) $_POST['torrentid'],
            ]);

            $subject = $lang_viewrequests['message_please_confirm_supply'];
            $notifs = "{$lang_viewrequests['request_name']}:[url=viewrequests.php?id=$arr[id]] ".$arr['request']."[/url],{$lang_viewrequests['please_confirm_supply']}.";

            Message::add([
                'sender' => 0,
                'receiver' => $arr['userid'],
                'subject' => $subject,
                'msg' => $notifs,
                'added' => now(),
            ]);

            try {
                RequestSupplied::dispatch(
                    (int) $arr['id'],
                    (int) $arr['userid'],
                    (int) $_POST['torrentid'],
                    (int) ($CURUSER['id'] ?? 0),
                    (string) ($arr['request'] ?? ''),
                );
            } catch (Throwable $e) {
                do_log('[viewrequests] RequestSupplied dispatch failed: '.$e->getMessage(), 'error');
            }

            stderr($lang_functions['std_success'], "{$lang_viewrequests['supply_success']}，<a href='viewrequests.php?action=view&id=".$_POST['reqid']."'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            exit;
            break;

        case 'addamount':

            if (! is_numeric($_POST['reqid'])) {
                stderr($lang_functions['std_error'], $lang_viewrequests['request_id_must_be_numeric']);
            }
            $reqRows = NexusDB::select('SELECT * FROM requests WHERE id = '.(int) $_POST['reqid']);
            if (count($reqRows) == 0) {
                stderr($lang_functions['std_error'], $lang_viewrequests['request_deleted']);
            }
            $arr = $reqRows[0];
            if ($arr['finish'] == 'yes') {
                stderr($lang_functions['std_error'], $lang_viewrequests['request_already_resolved']);
            }
            if (! is_numeric($_POST['amount'])) {
                stderr($lang_functions['std_error'], $lang_viewrequests['amount_must_be_numeric']);
            }
            $amount = $_POST['amount'];
            if ($amount < 100) {
                stderr($lang_functions['std_error'], $lang_viewrequests['add_reward_amount_minimum']);
            }
            if ($amount > 5000) {
                stderr($lang_functions['std_error'], $lang_viewrequests['add_reward_amount_maximum']);
            }
            $amount += 25;
            if ($amount > $CURUSER['seedbonus']) {
                stderr($lang_functions['std_error'], $lang_viewrequests['bouns_not_enough']);
            }
            NexusDB::statement('UPDATE users SET seedbonus = seedbonus - '.(int) $amount.' WHERE id = '.(int) $CURUSER['id']);
            NexusDB::statement('UPDATE requests SET amount = amount + '.(int) $_POST['amount'].' WHERE id = '.(int) $_POST['reqid']);
            stderr($lang_functions['std_success'], "{$lang_viewrequests['add_reward_success']}，<a href='viewrequests.php?action=view&id=".$_POST['reqid']."'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            exit;
            break;

        case 'delete':

            if (! is_numeric($_GET['id'])) {
                stderr($lang_functions['std_error'], $lang_viewrequests['request_id_must_be_numeric']);
            }
            $reqRows = NexusDB::select('SELECT * FROM requests WHERE id = '.(int) $_GET['id']);
            if (count($reqRows) == 0) {
                stderr($lang_functions['std_error'], $lang_viewrequests['request_deleted']);
            }
            $arr = $reqRows[0];
            if (get_user_class() >= UC_UPLOADER || $arr['userid'] == $CURUSER['id'] && $arr['finish'] == 'no') {
                if (! NexusDB::table('resreq')->where('reqid', (int) $_GET['id'])->count()) {
                    KPS('+', $arr['amount'] * 8 / 10, $arr['userid']);
                }
                NexusDB::statement('DELETE FROM requests WHERE id = '.(int) $_GET['id']);
                NexusDB::statement('DELETE FROM resreq WHERE reqid = '.(int) $_GET['id']);
                NexusDB::statement('DELETE FROM comments WHERE request = '.(int) $_GET['id']);
                stderr($lang_functions['std_success'], "{$lang_viewrequests['delete_request_success']}，<a href='viewrequests.php'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            } else {
                stderr($lang_functions['std_error'], "{$lang_functions['std_permission_denied']}");
            }
            exit;
            break;

        case 'confirm':

            if (! is_numeric($_POST['id'])) {
                stderr($lang_functions['std_error'], $lang_viewrequests['request_id_must_be_numeric']);
            }
            $reqRows = NexusDB::select('SELECT * FROM requests WHERE id = '.(int) $_POST['id']);
            if (count($reqRows) == 0) {
                stderr($lang_functions['std_error'], $lang_viewrequests['request_deleted']);
            }
            $arr = $reqRows[0];
            if (empty($_POST['torrentid'])) {
                stderr($lang_functions['std_error'], $lang_functions['std_target_not_exists']);
            } else {
                $torrentid = $_POST['torrentid'];
            }
            if ($arr['userid'] == $CURUSER['id'] || get_user_class() >= UC_UPLOADER) {
                $amount = $arr['amount'] / count($torrentid);
                $torrentIdList = implode(',', array_map('intval', (array) $torrentid));
                NexusDB::statement("UPDATE requests SET finish = 'yes' WHERE id = ".(int) $_POST['id']);
                NexusDB::statement("UPDATE resreq SET chosen = 'yes' WHERE reqid = ".(int) $_POST['id']." AND torrentid IN ($torrentIdList)");
                NexusDB::statement('DELETE FROM resreq WHERE reqid = '.(int) $_POST['id']." AND chosen = 'no'");
                $ownerRows = NexusDB::select("SELECT owner FROM torrents WHERE id IN ($torrentIdList)");
                $owner = [];
                foreach ($ownerRows as $row) {

                    $owner[] = $row['owner'];
                    $added = now();
                    $subject = $lang_viewrequests['torrent_is_picked_for_request'];
                    $notifs = "{$lang_viewrequests['request_name']}:[url=viewrequests.php?id=$arr[id]] ".$arr['request']."[/url].{$lang_functions['std_you_will_get']}: $amount {$lang_functions['text_bonus']}";
                    Message::add([
                        'sender' => 0,
                        'receiver' => $row['owner'],
                        'added' => now(),
                        'msg' => $notifs,
                        'subject' => $subject,
                    ]);
                }
                $ownerList = implode(',', array_map('intval', $owner));
                if ($ownerList !== '') {
                    NexusDB::statement('UPDATE users SET seedbonus = seedbonus + '.(float) $amount." WHERE id IN ($ownerList)");
                }
                try {
                    RequestFulfilled::dispatch(
                        (int) $arr['id'],
                        (int) $arr['userid'],
                        array_values(array_unique(array_map('intval', $owner))),
                        (string) ($arr['request'] ?? ''),
                        (float) $amount,
                    );
                } catch (Throwable $e) {
                    do_log('[viewrequests] RequestFulfilled dispatch failed: '.$e->getMessage(), 'error');
                }
                stderr($lang_functions['std_success'], "{$lang_viewrequests['confirm_request_success']}，<a href='viewrequests.php?action=view&id=".$_POST['id']."'>{$lang_functions['std_click_here_to_goback']}</a>", 0);

            }

        case 'message':

            if (! is_numeric($_POST['id'])) {
                stderr($lang_functions['std_error'], $lang_viewrequests['request_id_must_be_numeric']);
            }
            $reqRows = NexusDB::select('SELECT * FROM requests WHERE id = '.(int) $_POST['id']);
            if (count($reqRows) == 0) {
                stderr($lang_functions['std_error'], $lang_viewrequests['request_deleted']);
            }
            if (! $_POST['message']) {
                stderr($lang_functions['std_error'], $lang_viewrequests['message_required']);
            }
            $arr = $reqRows[0];
            $message = $arr['message'];
            $message .= "<tr><td width=240>{$lang_functions['std_by']}".$CURUSER['username'].$lang_viewrequests['request_created_at'].date('Y-m-d H:i:s').'</td><td>'.$_POST['message'].'</td></tr>';

            NexusDB::insert('comments', [
                'user' => $CURUSER['id'],
                'request' => (int) $_POST['id'],
                'added' => date('Y-m-d H:i:s'),
                'text' => $_POST['message'],
                'ori_text' => $_POST['message'],
            ]);
            $id = (int) ($_POST['id'] ?? 0);
            if ($CURUSER['id'] != $arr['userid']) {
                Message::add([
                    'sender' => 0,
                    'receiver' => $arr['userid'],
                    'subject' => $lang_viewrequests['request_get_new_reply'],
                    'msg' => " [url=viewrequests.php?action=view&id={$id}] ".$arr['request'].'[/url]',
                    'added' => now(),
                ]);
            }
            $ruserid = 0 + $_POST['ruserid'];
            if ($ruserid != $CURUSER['id'] && $ruserid != $arr['userid']) {
                Message::add([
                    'sender' => 0,
                    'receiver' => $ruserid,
                    'subject' => $lang_viewrequests['request_comment_get_new_reply'],
                    'msg' => " [url=viewrequests.php?action=view&id={$id}] ".$arr['request'].'[/url]',
                    'added' => now(),
                ]);
            }
            header('Location: viewrequests.php?action=view&id='.$_POST['id']);

    }

}
exit;

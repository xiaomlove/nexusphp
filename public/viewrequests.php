<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
require_once(get_langfile_path('details.php'));
loggedinorreturn();
parked();


$action = isset($_POST['action']) ? htmlspecialchars($_POST['action']) : (isset($_GET['action']) ? htmlspecialchars($_GET['action']) : '');
$allowed_actions = array("list", "new", "newmessage", "view", "edit", "takeedit", "takeadded", "res", "takeres", "addamount", "delete", "confirm", "message", "search");
if (!$action)
    if (!empty($_GET['id'])) $action = 'view';
    else $action = 'list';
if (!in_array($action, $allowed_actions))
    $action = 'list';
else {
    $limitorder = $limit = '';
    switch ($action) {
        case "list":
        {
            $finished = $_REQUEST['finished'] ?? '';
            $finishedlimit = isset($_GET['finished']) ? "finished=" . $_GET['finished'] . "&" : '';
            $allowed_finished = array("yes", "no", "all", "ing", "my");
            switch ($finished) {
                case "yes":
                {
                    $limit = "finish = 'yes'";
                    break;
                }
                case "no":
                {
                    $limit = "finish = 'no'";
                    break;
                }
                case "all":
                {
                    $limit = "1";
                    break;
                }
                case "my":
                {
                    $limit = "1 and userid=" . $CURUSER["id"];
                    break;
                }
                case "ing":
                {
                    $limit = "(SELECT count(DISTINCT torrentid) FROM resreq  where reqid=requests.id )>=1 and finish = 'no'";
                    break;
                }
                default:
                {
                    $limit = "finish = 'no'";
                    break;
                }
            }
            //if (!in_array($finished, $allowed_finished)){$limit = "finish = 'no'";(get_user_class() >= UC_UPLOADER?$limitorder="Totalreq DESC ,":"");}
            //else $limit = ( $finished=="all" ? "1" : ( $finished=="all" ? "1" : "finish ='".$finished."'"));


            if (!empty($_POST['query'])) $limit = $limit . " and (request like " . sqlesc("%" . $_POST['query'] . "%") . " or descr like " . sqlesc("%" . $_POST['query'] . "%") . ")";


            $rows = sql_query("SELECT  requests.*  FROM requests WHERE " . $limit . " ORDER BY id DESC") or sqlerr(__FILE__, __LINE__);
            list($pagertop, $pagerbottom, $limit2) = pager(20, mysql_num_rows($rows), "?$finishedlimit");
            //if (mysql_num_rows($rows) == 0) stderr( "没有求种" , "没有符合条件的求种项目，<a href=viewrequests.php?action=new>点击这里增加新求种</a>",0);
            //else
            {
                stdhead($lang_viewrequests['page_title']);

                $rows = sql_query("SELECT requests.* ,(SELECT count(DISTINCT torrentid) FROM resreq  where reqid=requests.id ) as Totalreq FROM requests WHERE " . $limit . " ORDER BY $limitorder id DESC $limit2") or sqlerr(__FILE__, __LINE__);
                print("<h1 align=center>{$lang_viewrequests['page_title']}</h1>");
                print("<br><b><a href='viewrequests.php?action=new'>{$lang_viewrequests['add_request']}</a> | <a href='viewrequests.php?finished=all'>{$lang_viewrequests['view_request_all']}</a> | <a href='viewrequests.php?finished=yes'>{$lang_viewrequests['view_request_resolved']}</a> | <a href='viewrequests.php?finished=no'>{$lang_viewrequests['view_request_unresolved']}</a> | <a href='viewrequests.php?finished=ing'>{$lang_viewrequests['view_request_resolving']}</a> | <a href='viewrequests.php?finished=my' " . get_requestcount() . ">{$lang_viewrequests['view_request_my']}</a></b><p>\n");
                print("<table width=98% border=1 cellspacing=0 cellpadding=5 style=border-collapse:collapse >\n");

                if (mysql_num_rows($rows) == 0) {
                    print("<tr><td class=colhead align=center>Nothing</td></tr>\n");
                } else {
                    print("<tr><td class=colhead align=left>{$lang_viewrequests['thead_name']}</td><td class=colhead align=center>{$lang_viewrequests['thead_price_newest']}</td><td class=colhead align=center>{$lang_viewrequests['thead_price_original']}</td><td class=colhead  align=center>{$lang_viewrequests['thead_comment_count']}</td><td class=colhead  align=center>{$lang_viewrequests['thead_on_request_count']}</td><td class=colhead align=center>{$lang_viewrequests['thead_request_user']}</td><td class=colhead align=center>{$lang_viewrequests['thead_created_at']}</td><td class=colhead align=center>{$lang_viewrequests['thead_status']}</td></tr>\n");
                    while ($row = mysql_fetch_array($rows)) {
                        print("<tr>
	<td align=left class='rowfollow'><a href='viewrequests.php?action=view&id=" . $row["id"] . "'><b>" . $row["request"] . "</b></a></td>
	<td align=center class='rowfollow nowrap'><font color=#ff0000><b>" . $row['amount'] . "</b></font></td>
	<td align=center class='rowfollow nowrap'>" . $row['ori_amount'] . "</td>
	<td align=center class='rowfollow nowrap'>" . ($row['comments']) . "</td><td align=center>" . ($row['Totalreq']) . "</td>
	<td align=center class='rowfollow nowrap'>" . get_username($row['userid']) . "</td>
	<td align=center class='rowfollow nowrap'>" . gettime($row['added'], true, false) . "</td>
	<td align=center class='rowfollow nowrap'>" . ($row['finish'] == "yes" ? $lang_viewrequests['request_status_resolved'] : ($row['userid'] == $CURUSER['id'] ? $lang_viewrequests['request_status_resolving'] : "<a href='viewrequests.php?action=res&id=" . $row["id"] . "'>{$lang_viewrequests['request_status_resolving']}</a>")) . "</td></tr>\n");
                    }
                }
                print("</table>\n");
                print($pagerbottom);
                //print("<br><b><a href=viewrequests.php?action=new>添加</a> <a href=viewrequests.php?finished=all>查看所有</a> <a href=viewrequests.php?finished=yes>查看已解决</a> <a href=viewrequests.php?finished=no>查看未解决</a></b>\n");
                print("<table border=1 cellspacing=0  cellpadding=5>\n");
                print("<tr><td class=toolbox align=left><form  method=\"post\" action='viewrequests.php'>\n");
                print("<input type=\"text\" name=\"query\" style=\"width:500px\" >\n");
                print("<input type=\"hidden\" name=\"action\" value='list'>");
                print("<input type=\"hidden\" name=\"finished\" value='all'>");

                print("<input type=submit value='{$lang_viewrequests['action_search']}'></form>\n");
                print("</td></tr></table><br />\n");


                stdfoot();
            }
            die;
            break;
        }

        case "view":
        {
            if (is_numeric($_GET["id"])) {
                $id = $_GET["id"];
                $res = sql_query("SELECT * FROM requests WHERE id ='" . $_GET["id"] . "'") or sqlerr(__FILE__, __LINE__);
                if (mysql_num_rows($res) == 0) stderr($lang_functions['std_error'], $lang_functions['std_target_not_exists']);
                else $arr = mysql_fetch_assoc($res);
                stdhead($lang_viewrequests['page_title']);
                print("<h1 align=center id=top>{$lang_viewrequests['request']}-" . htmlspecialchars($arr["request"]) . "</h1>\n");
                print("<table width=100% cellspacing=0 cellpadding=5>\n");
                $res = sql_query("SELECT * FROM resreq WHERE reqid ='" . $_GET["id"] . "'" . $limit) or sqlerr(__FILE__, __LINE__);
                tr($lang_viewrequests['basic_info'], get_username($arr['userid']) . $lang_viewrequests['created_at'] . gettime($arr["added"], true, false) . "\n", 1);
                tr($lang_viewrequests['reward'], $lang_viewrequests['newest_bidding'] . $arr['amount'] . "     {$lang_viewrequests['original_bidding']}" . $arr["ori_amount"] . "\n", 1);
                tr($lang_functions['std_action'], "<a href='report.php?reportrequestid=" . $id . "' >{$lang_functions['std_report']}</a>" .
                    (($arr['userid'] == $CURUSER['id'] || get_user_class() >= UC_UPLOADER) && $arr["finish"] == "no" ? " | <a href='viewrequests.php?action=edit&id=" . $id . "' >{$lang_functions['title_edit']}</a>" : "") . "\n" .
                    ($arr['userid'] == $CURUSER['id'] || $arr["finish"] == "yes" ? "" : " | <a href='viewrequests.php?action=res&id=" . $id . "' >{$lang_viewrequests['on_request']}</a>\n") .
                    ((get_user_class() >= UC_UPLOADER || $arr['userid'] == $CURUSER['id']) && $arr['finish'] == "no" ? " | <a href='viewrequests.php?action=delete&id=" . $id . "' " . (mysql_num_rows($res) ? ">{$lang_functions['title_delete']}" : "title='{$lang_viewrequests['recycle_title']}'>{$lang_viewrequests['recycle']}") . "</a>" : "") . "\n"
                    , 1);
                if ($arr["finish"] == "no") tr($lang_viewrequests['add_reward'], "<form action=viewrequests.php method=post> <input type=hidden name=action value=addamount><input type=hidden name=reqid value=" . $arr["id"] . "><input size=6 name=amount value=1000 ><input type=submit value={$lang_functions['submit_submit']} > {$lang_viewrequests['add_reward_desc']}</form>", 1);
                tr($lang_functions['std_desc'], format_comment(unesc($arr["descr"])), 1);
                $limit = ($arr['finish'] == "no" ? "" : " AND chosen = 'yes' ");
                $ress = "";
                if (mysql_num_rows($res) == 0) $ress = $lang_viewrequests['no_request_yet'];
                else {
                    if ($arr['userid'] == $CURUSER['id'] || get_user_class() >= UC_UPLOADER)
                        $ress .= "<form action=viewrequests.php method=post>\n<input type=hidden name=action value=confirm > <input type=hidden name=id value=" . $id . " >\n";
                    while ($row = mysql_fetch_array($res)) {
                        $each = mysql_fetch_assoc(sql_query("SELECT * FROM torrents WHERE id = '" . $row["torrentid"] . "'"));
                        if (mysql_num_rows(sql_query("SELECT * FROM torrents WHERE id = '" . $row["torrentid"] . "'")) == 1)
                            $ress .= (($arr['userid'] == $CURUSER['id'] || get_user_class() >= UC_UPLOADER) && $arr['finish'] == "no" ? "<input type=checkbox name=torrentid[] value=" . $each["id"] . ">" : "") . "<a href='details.php?id=" . $each["id"] . "&hit=1' >" . $each["name"] . "</a> " . ($arr['finish'] == "no" ? "" : "by " . get_username($each['owner'])) . "<br/>\n";
                    }
                    $ress .= "";

                    if (($arr['userid'] == $CURUSER['id'] || get_user_class() >= UC_UPLOADER) && $arr['finish'] == "no")
                        $ress .= "<input type=submit value={$lang_viewrequests['btn_select_text']}>\n";
                    $ress .= "</form>\n";
                }
                tr($lang_viewrequests['request'], $ress, 1);
                print("</table><br/><br/>\n");


                $count = get_row_count("comments", "WHERE request=" . sqlesc($_GET["id"]));
                if ($count) {
                    print("<br /><br />");
                    print("<h1 align=\"center\" id=\"startcomments\">{$lang_functions['std_comment']}</h1>\n");
                    list($pagertop, $pagerbottom, $limit) = pager(10, $count, "viewrequests.php?action=view&id=" . $_GET["id"] . "&", array('lastpagedefault' => 1), "page");

                    $subres = sql_query("SELECT * FROM comments WHERE request=" . sqlesc($_GET["id"]) . " ORDER BY id $limit") or sqlerr(__FILE__, __LINE__);

                    $allrows = array();
                    while ($subrow = mysql_fetch_array($subres)) {
                        $allrows[] = $subrow;
                    }
                    print($pagertop);
                    commenttable($allrows, 'request', $_GET["id"]);
                    print($pagerbottom);
                }


                print ("
	<table style='border:1px solid #000000;'>
	<tr><td class=\"text\" align=\"center\"><b>" . $lang_details['text_quick_comment'] . "</b><br /><br />
	<form id=\"compose\" name=\"comment\" method=\"post\" action=\"" . htmlspecialchars("comment.php?action=add&type=request") . "\" onsubmit=\"return postvalid(this);\">
	<input type=\"hidden\" name=\"pid\" value=\"" . $id . "\" /><br />");
                quickreply('comment', 'body', $lang_functions['std_quick_comment']);
                print("</form></td></tr></table>");


                print ("

<a class=\"index\" href='comment.php?action=add&pid=$id&type=request'>{$lang_functions['title_add_comments']}</a></td></tr></table>");

                stdfoot();

            } else stderr($lang_functions['std_error'], $lang_functions['std_target_not_exists']);
            die;
            break;
        }

        case "edit":
        {
            if (!is_numeric($_GET["id"])) stderr($lang_functions['std_error'], $lang_functions['std_target_not_exists']);
            $res = sql_query("SELECT * FROM requests WHERE id ='" . $_GET["id"] . "'") or sqlerr(__FILE__, __LINE__);
            if (mysql_num_rows($res) == 0) stderr($lang_functions['std_error'], $lang_functions['std_target_not_exists']);
            $arr = mysql_fetch_assoc($res);
            if ($arr["finish"] == "yes") stderr($lang_functions['std_error'], $lang_viewrequests['request_already_resolved']);
            if ($arr['userid'] == $CURUSER['id'] || get_user_class() >= UC_UPLOADER) {
                stdhead($lang_functions['title_edit'] . $lang_viewrequests['request']);
                print(
                    "<form id=edit method=post name=edit action=viewrequests.php >\n
		<input type=hidden name=action  value=takeedit >
		<input type=hidden name=reqid  value=" . $_GET["id"] . " >
		");
                print("<table width=100% cellspacing=0 cellpadding=3><tr><td class=colhead align=center colspan=2>{$lang_functions['title_edit']}{$lang_viewrequests['request']}</td></tr>");
                tr("{$lang_functions['col_name']}：", "<input name=request value=\"" . $arr["request"] . "\" size=134 ><br/>", 1);
                print("<tr><td class=rowhead align=right valign=top><b>{$lang_functions['std_desc']}：</b></td><td class=rowfollow align=left>");
                textbbcode("edit", "descr", $arr["descr"], false, 130, true);
                print("</td></tr>");
                print("</td></tr><tr><td class=toolbox align=center colspan=2><input id=qr type=submit class=btn value={$lang_functions['text_edit']}{$lang_viewrequests['request']} ></td></tr></table></form><br />\n");
                stdfoot();
                die;
            } else stderr($lang_functions['std_error'], "{$lang_functioins['std_permission_denied']}<a href='viewrequests.php?action=view&id=" . $_GET["id"] . "'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
        }

        case "new":
        {
            if (get_user_class() >= 1) {
                stdhead($lang_viewrequests['add_request']);
                print(
                "<form id=edit method=post name=edit action=viewrequests.php >\n<input type=hidden name=action  value=takeadded >\n");
                print("<table width=100% cellspacing=0 cellpadding=3><tr><td class=colhead align=center colspan=2>{$lang_viewrequests['add_request']}</td></tr>\n");
                tr("{$lang_functions['col_name']}：", "<input name=request size=134><br/>", 1);
                tr("{$lang_viewrequests['reward']}：", "<input name=amount size=11 value=2000>{$lang_viewrequests['add_request_desc']}<br/>", 1);
                print("<tr><td class=rowhead align=right valign=top><b>{$lang_functions['std_desc']}：</b></td><td class=rowfollow align=left>");
                textbbcode("edit", "descr", $arr["descr"], false, 130, true);
                print("</td></tr>");
                print("<tr><td class=toolbox style=vertical-align: middle; padding-top: 10px; padding-bottom: 10px; align=center colspan=2><input id=qr type=submit value={$lang_viewrequests['add_request']} class=btn /></td></tr></table></form><br />\n");

                stdfoot();
                die;
            } else stderr($lang_functions['std_error'], "{$lang_functions['std_permission_denied']}<a href='viewrequests.php'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
        }

        case "newmessage":
        {

            {
                stdhead($lang_functions['text_reply']);


                //<input type=hidden name=id value=$id ><br />");
//quickreply('reply', 'message', "我要留言");
//print("</form></td></tr></table>");

                $ruserid = 0 + $_GET["userid"];


                print(
                    "<form id=reply name=reply method=post action=viewrequests.php >\n<input type=hidden name=action value=message ><input type=hidden name=id value=" . $_GET["id"] . " >\n");
                print("<table width=100% cellspacing=0 cellpadding=3>\n");

                print("<tr><td class=rowfollow align=left>");
                if ($ruserid) {
                    textbbcode("reply", "message", "[b]{$lang_functions['text_reply']}:" . get_plain_username($ruserid) . "[/b]\n");
                    print("<input id=ruserid type=hidden value=$ruserid />");
                } else
                    textbbcode("reply", "message");
                print("</td></tr>");
                print("</table><input id=qr type=submit value={$lang_functions['title_add_comments']} class=btn /></form><br />\n");

                stdfoot();
                die;
            }

        }
        case "search":
        {

            {
                stdhead($lang_functions['text_search']);


                print("<table border=1 cellspacing=0  cellpadding=5>\n");
                print("<tr><td class=colhead align=left>{$lang_functions['text_search']}</td></tr>\n");
                print("<tr><td class=toolbox align=left><form  method=\"post\" action='viewrequests.php'>\n");
                print("<input type=\"text\" name=\"query\" style=\"width:500px\" >\n");
                print("<input type=\"hidden\" name=\"action\" value='list'>");
                print("<input type=submit value='{$lang_functions['text_search']}'></form>\n");
                print("</td></tr></table><br />\n");


                stdfoot();
                die;
            }

        }
        case "takeadded":
        {
            if (!$_POST["descr"]) stderr($lang_functions['std_error'], "{$lang_viewrequests['description_required']}<a href='viewrequests.php?action=new'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            if (!$_POST["request"]) stderr($lang_functions['std_error'], "{$lang_viewrequests['name_required']}<a href='viewrequests.php?action=new'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            if (!$_POST["amount"]) stderr($lang_functions['std_error'], "{$lang_viewrequests['amount_required']}<a href='viewrequests.php?action=new'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            if (!is_numeric($_POST["amount"])) stderr($lang_functions['std_error'], "{$lang_viewrequests['amount_must_be_numeric']}<a href=viewrequests.php?action=new>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            $amount = $_POST["amount"];
            if ($amount < 100) stderr($lang_functions['std_error'], "{$lang_viewrequests['add_request_amount_minimum']}<a href='viewrequests.php?action=new'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            if ($amount > 10000) stderr($lang_functions['std_error'], "{$lang_viewrequests['add_request_amount_maximum']}<a href='viewrequests.php?action=new'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            $amount += 100;
            if ($amount + 100 > $CURUSER['seedbonus']) stderr($lang_functions['std_error'], "{$lang_viewrequests['bouns_not_enough']}<a href='viewrequests.php?action=new'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            if (get_user_class() >= 1) {
                sql_query("UPDATE users SET seedbonus = seedbonus - " . $amount . " WHERE id = " . sqlesc($CURUSER['id']));
                sql_query("INSERT requests ( request , descr, ori_descr ,amount , ori_amount , userid ,added ) VALUES ( " . sqlesc($_POST["request"]) . " , " . sqlesc($_POST["descr"]) . " , " . sqlesc($_POST["descr"]) . " , " . sqlesc($_POST["amount"]) . " , " . sqlesc($_POST["amount"]) . " , " . sqlesc($CURUSER['id']) . " , '" . date("Y-m-d H:i:s") . "' )") or sqlerr(__FILE__, __LINE__);
//                shoutbox_into('[rid' . ($id = mysql_insert_id()) . ']');
                $id = mysql_insert_id();
                stderr($lang_functions['std_success'], "{$lang_viewrequests['add_request_success']}，<a href='viewrequests.php?action=view&id=" . $id . "'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            } else stderr($lang_functions['std_error'], "{$lang_functions['std_permission_denied']}<a href='viewrequests.php'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            die;
            break;
        }

        case "takeedit":
        {
            if (!is_numeric($_POST["reqid"])) stderr($lang_functions['std_error'], "{$lang_viewrequests['request_id_must_be_numeric']}<a href='viewrequests.php?action=edit&id=" . $_POST["reqid"] . "'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            $res = sql_query("SELECT * FROM requests WHERE id =" . sqlesc( $_POST["reqid"])) or sqlerr(__FILE__, __LINE__);
            if (!$_POST["descr"]) stderr($lang_functions['std_error'], "{$lang_viewrequests['description_required']}<a href='viewrequests.php?action=edit&id=" . $_POST["reqid"] . "'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            if (!$_POST["request"]) stderr($lang_functions['std_error'], "{$lang_viewrequests['name_required']}<a href='viewrequests.php?action=edit&id=" . $_POST["reqid"] . "'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            if (mysql_num_rows($res) == 0) stderr($lang_functions['std_error'], "{$lang_viewrequests['request_deleted']}<a href='viewrequests.php'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            $arr = mysql_fetch_assoc($res);
            if ($arr["finish"] == "yes") stderr($lang_functions['std_error'], "{$lang_viewrequests['request_already_resolved']}<a href='viewrequests.php?action=view&id=" . $_POST["reqid"] . "'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            if ($arr['userid'] == $CURUSER['id'] || get_user_class() >= UC_UPLOADER) {
                sql_query("UPDATE requests SET descr = " . sqlesc($_POST["descr"]) . " , request = " . sqlesc($_POST["request"]) . " WHERE id =" . sqlesc($_POST["reqid"])) or sqlerr(__FILE__, __LINE__);
                stderr($lang_functions['std_success'], "{$lang_viewrequests['edit_request_success']}，<a href='viewrequests.php?action=view&id=" . $_POST["reqid"] . "'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            } else stderr($lang_functions['std_error'], "{$lang_functions['std_permission_denied']}<a href='viewrequests.php?action=view&id=" . $_POST["reqid"] . "'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            die;
            break;
        }

        case "res":
        {
            stdhead($lang_viewrequests['request']);
            stdmsg($lang_viewrequests['do_request'], "
	<form action=viewrequests.php method=post>
	<input type=hidden name=action value=takeres />
	<input type=hidden name=reqid value=\"" . $_GET["id"] . "\" />
	{$lang_viewrequests['type_in_torrent_id']}:" . getSchemeAndHttpHost() . "/details.php?id=<input type=text name=torrentid size=11/>
	<input type=submit value={$lang_functions['submit_submit']}></form><a href='viewrequests.php?action=view&id=" . $_GET["id"] . "'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            stdfoot();
            die;
            break;
        }

        case "takeres":
        {
            if (!is_numeric($_POST["reqid"])) stderr($lang_functions['std_error'], $lang_viewrequests['request_id_must_be_numeric']);
            $res = sql_query("SELECT * FROM requests WHERE id ='" . $_POST["reqid"] . "'") or sqlerr(__FILE__, __LINE__);
            if (mysql_num_rows($res) == 0) stderr($lang_functions['std_error'], "{$lang_viewrequests['request_deleted']}<a href='viewrequests.php'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            $arr = mysql_fetch_assoc($res);
            if ($arr["finish"] == "yes") stderr($lang_functions['std_error'], "{$lang_viewrequests['request_already_resolved']}<a href='viewrequests.php?action=view&id=" . $_POST["reqid"] . "'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            if (!is_numeric($_POST["torrentid"])) stderr($lang_functions['std_error'], "{$lang_viewrequests['request_id_must_be_numeric']}<a href='viewrequests.php?action=res&id=" . $_POST["reqid"] . "'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            $res = sql_query("SELECT * FROM torrents WHERE id ='" . $_POST["torrentid"] . "'") or sqlerr(__FILE__, __LINE__);
            if (mysql_num_rows($res) == 0) stderr($lang_functions['std_error'], "{$lang_functions['std_target_not_exists']}<a href='viewrequests.php?action=res&id=" . $_POST["reqid"] . "'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            $tor = mysql_fetch_assoc($res);
//            if ($tor['last_seed'] == "0000-00-00 00:00:00" || is_null(($tor['last_seed']))) stderr($lang_functions['std_error'], "{$lang_viewrequests['torrent_not_release_yet']}<a href='viewrequests.php?action=res&id=" . $_POST["reqid"] . "'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            if (get_row_count('resreq', "where reqid ='" . $_POST["reqid"] . "' and torrentid='" . $_POST["torrentid"] . "'"))
                stderr($lang_functions['std_error'], "{$lang_viewrequests['supply_already_exists']}<a href='viewrequests.php?action=res&id=" . $_POST["reqid"] . "'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            sql_query("INSERT resreq (reqid , torrentid) VALUES ( '" . $_POST["reqid"] . "' , '" . $_POST["torrentid"] . "')");


            $added = sqlesc(date("Y-m-d H:i:s"));
            $subject = sqlesc($lang_viewrequests['message_please_confirm_supply']);
            $notifs = sqlesc("{$lang_viewrequests['request_name']}:[url=viewrequests.php?id=$arr[id]] " . $arr['request'] . "[/url],{$lang_viewrequests['please_confirm_supply']}.");
            sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) VALUES(0, " . $arr['userid'] . ", $subject, $notifs, $added)") or sqlerr(__FILE__, __LINE__);


            stderr($lang_functions['std_success'], "{$lang_viewrequests['supply_success']}，<a href='viewrequests.php?action=view&id=" . $_POST["reqid"] . "'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            die;
            break;
        }

        case "addamount":
        {
            if (!is_numeric($_POST["reqid"])) stderr($lang_functions['std_error'], $lang_viewrequests['request_id_must_be_numeric']);
            $res = sql_query("SELECT * FROM requests WHERE id ='" . $_POST["reqid"] . "'") or sqlerr(__FILE__, __LINE__);
            if (mysql_num_rows($res) == 0) stderr($lang_functions['std_error'], $lang_viewrequests['request_deleted']);
            $arr = mysql_fetch_assoc($res);
            if ($arr["finish"] == "yes") stderr($lang_functions['std_error'], $lang_viewrequests['request_already_resolved']);
            if (!is_numeric($_POST["amount"])) stderr($lang_functions['std_error'], $lang_viewrequests['amount_must_be_numeric']);
            $amount = $_POST["amount"];
            if ($amount < 100) stderr($lang_functions['std_error'], $lang_viewrequests['add_reward_amount_minimum']);
            if ($amount > 5000) stderr($lang_functions['std_error'], $lang_viewrequests['add_reward_amount_maximum']);
            $amount += 25;
            if ($amount > $CURUSER['seedbonus']) stderr($lang_functions['std_error'], $lang_viewrequests['bouns_not_enough']);
            sql_query("UPDATE users SET seedbonus = seedbonus - " . $amount . " WHERE id = " . $CURUSER['id']);
            sql_query("UPDATE requests SET amount = amount + " . $_POST["amount"] . " WHERE id = " . $_POST["reqid"]);
            stderr($lang_functions['std_success'], "{$lang_viewrequests['add_reward_success']}，<a href='viewrequests.php?action=view&id=" . $_POST["reqid"] . "'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            die;
            break;
        }

        case "delete":
        {
            if (!is_numeric($_GET["id"])) stderr($lang_functions['std_error'], $lang_viewrequests['request_id_must_be_numeric']);
            $res = sql_query("SELECT * FROM requests WHERE id ='" . $_GET["id"] . "'") or sqlerr(__FILE__, __LINE__);
            if (mysql_num_rows($res) == 0) stderr($lang_functions['std_error'], $lang_viewrequests['request_deleted']);
            $arr = mysql_fetch_assoc($res);
            if (get_user_class() >= UC_UPLOADER || $arr['userid'] == $CURUSER["id"] && $arr['finish'] == 'no') {
                if (!get_row_count("resreq", "WHERE reqid=" . sqlesc($_GET["id"]))) {
                    KPS("+", $arr['amount'] * 8 / 10, $arr['userid']);
                }
                sql_query("DELETE FROM requests WHERE id ='" . $_GET["id"] . "'") or sqlerr(__FILE__, __LINE__);
                sql_query("DELETE FROM resreq WHERE reqid ='" . $_GET["id"] . "'") or sqlerr(__FILE__, __LINE__);
                sql_query("DELETE FROM comments WHERE request ='" . $_GET["id"] . "'") or sqlerr(__FILE__, __LINE__);
                stderr($lang_functions['std_success'], "{$lang_viewrequests['delete_request_success']}，<a href='viewrequests.php'>{$lang_functions['std_click_here_to_goback']}</a>", 0);
            } else stderr($lang_functions['std_error'], "{$lang_functions['std_permission_denied']}");
            die;
            break;
        }

        case "confirm":
        {
            if (!is_numeric($_POST["id"])) stderr($lang_functions['std_error'], $lang_viewrequests['request_id_must_be_numeric']);
            $res = sql_query("SELECT * FROM requests WHERE id ='" . $_POST["id"] . "'") or sqlerr(__FILE__, __LINE__);
            if (mysql_num_rows($res) == 0) stderr($lang_functions['std_error'], $lang_viewrequests['request_deleted']);
            $arr = mysql_fetch_assoc($res);
            if (empty($_POST["torrentid"])) stderr($lang_functions['std_error'], $lang_functions['std_target_not_exists']);
            else $torrentid = $_POST["torrentid"];
            if ($arr['userid'] == $CURUSER['id'] || get_user_class() >= UC_UPLOADER) {
                $amount = $arr["amount"] / count($torrentid);
                sql_query("UPDATE requests SET finish = 'yes' WHERE id = " . $_POST["id"]);
                sql_query("UPDATE resreq SET chosen = 'yes' WHERE reqid = " . $_POST["id"] . " AND ( torrentid = '" . join("' OR torrentid = '", $torrentid) . "' )") or sqlerr(__FILE__, __LINE__);
                sql_query("DELETE FROM resreq WHERE reqid ='" . $_POST["id"] . "' AND chosen = 'no'") or sqlerr(__FILE__, __LINE__);
                $res = sql_query("SELECT owner FROM torrents WHERE ( id = '" . join("' OR id = '", $torrentid) . "' ) ") or sqlerr(__FILE__, __LINE__);
                while ($row = mysql_fetch_array($res)) {

                    $owner[] = $row[0];
                    $added = sqlesc(date("Y-m-d H:i:s"));
                    $subject = sqlesc($lang_viewrequests['torrent_is_picked_for_request']);
                    $notifs = sqlesc("{$lang_viewrequests['request_name']}:[url=viewrequests.php?id=$arr[id]] " . $arr['request'] . "[/url].{$lang_functions['std_you_will_get']}: $amount {$lang_functions['text_bonus']}");
                    sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) VALUES(0, " . $row[0] . ", $subject, $notifs, $added)") or sqlerr(__FILE__, __LINE__);

                }
                sql_query("UPDATE users SET seedbonus = seedbonus + $amount WHERE id = '" . join("' OR id = '", $owner) . "'") or sqlerr(__FILE__, __LINE__);
                stderr($lang_functions['std_success'], "{$lang_viewrequests['confirm_request_success']}，<a href='viewrequests.php?action=view&id=" . $_POST["id"] . "'>{$lang_functions['std_click_here_to_goback']}</a>", 0);

            }

        }

        case "message":
        {
            if (!is_numeric($_POST["id"])) stderr($lang_functions['std_error'], $lang_viewrequests['request_id_must_be_numeric']);
            $res = sql_query("SELECT * FROM requests WHERE id ='" . $_POST["id"] . "'") or sqlerr(__FILE__, __LINE__);
            if (mysql_num_rows($res) == 0) stderr($lang_functions['std_error'], $lang_viewrequests['request_deleted']);
            if (!$_POST["message"]) stderr($lang_functions['std_error'], $lang_viewrequests['message_required']);
            $arr = mysql_fetch_assoc($res);
            $message = $arr["message"];
            $message .= "<tr><td width=240>{$lang_functions['std_by']}" . $CURUSER["username"] . $lang_viewrequests['request_created_at']. date("Y-m-d H:i:s") . "</td><td>" . $_POST["message"] . "</td></tr>";


            //sql_query("UPDATE requests SET message = '".$message."' WHERE id = ".$_POST["id"])or sqlerr(__FILE__, __LINE__);

            //sql_query("INSERT reqcommen (user , added ,text ,reqid) VALUES ( '".$CURUSER["id"]."' , ".sqlesc(date("Y-m-d H:i:s"))." , ".sqlesc($_POST["message"])." , '".$_POST["id"]."'    )");
            sql_query("INSERT INTO comments (user, request, added, text, ori_text) VALUES (" . $CURUSER["id"] . ",{$_POST['id']}, '" . date("Y-m-d H:i:s") . "', " . sqlesc($_POST["message"]) . "," . sqlesc($_POST["message"]) . ")");

            if ($CURUSER["id"] <> $arr['userid']) sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) VALUES(0, " . $arr['userid'] . ", '{$lang_viewrequests['request_get_new_reply']}', " . sqlesc(" [url=viewrequests.php?action=view&id={$_POST['id']}] " . $arr['request'] . "[/url].") . ", " . sqlesc(date("Y-m-d H:i:s")) . ")") or sqlerr(__FILE__, __LINE__);

            $ruserid = 0 + $_POST["ruserid"];
            if ($ruserid <> $CURUSER["id"] && $ruserid <> $arr['userid']) sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) VALUES(0, " . $ruserid . ", '{$lang_viewrequests['request_comment_get_new_reply']}', " . sqlesc(" [url=viewrequests.php?action=view&id={$_POST['id']}] " . $arr['request'] . "[/url].") . ", " . sqlesc(date("Y-m-d H:i:s")) . ")") or sqlerr(__FILE__, __LINE__);

            header("Location: viewrequests.php?action=view&id=" . $_POST['id']);
        }
    }


}
die;


?>

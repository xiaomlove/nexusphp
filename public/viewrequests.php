<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
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
            //if (!in_array($finished, $allowed_finished)){$limit = "finish = 'no'";(get_user_class() >= 13?$limitorder="Totalreq DESC ,":"");}
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
                if (mysql_num_rows($res) == 0) stderr("错误", "ID不存在");
                else $arr = mysql_fetch_assoc($res);
                stdhead("求种区");
                print("<h1 align=center id=top>求种-" . htmlspecialchars($arr["request"]) . "</h1>\n");
                print("<table width=940 cellspacing=0 cellpadding=5>\n");
                $res = sql_query("SELECT * FROM resreq WHERE reqid ='" . $_GET["id"] . "'" . $limit) or sqlerr(__FILE__, __LINE__);
                tr("基本信息", get_username($arr['userid']) . "发表于" . gettime($arr["added"], true, false) . "\n", 1);
                tr("悬赏", "最新竞价为" . $arr['amount'] . "     原始竞价为" . $arr["ori_amount"] . "\n", 1);
                tr("操作", "<a href='report.php?reportrequestid=" . $id . "' >举报</a>" .
                    (($arr['userid'] == $CURUSER['id'] || get_user_class() >= 13) && $arr["finish"] == "no" ? " | <a href='viewrequests.php?action=edit&id=" . $id . "' >编辑</a>" : "") . "\n" .
                    ($arr['userid'] == $CURUSER['id'] || $arr["finish"] == "yes" ? "" : " | <a href='viewrequests.php?action=res&id=" . $id . "' >应求</a>\n") .
                    ((get_user_class() >= 13 || $arr['userid'] == $CURUSER['id']) && $arr['finish'] == "no" ? " | <a href='viewrequests.php?action=delete&id=" . $id . "' " . (mysql_num_rows($res) ? ">删除" : "title='回收返还80%魔力值'>回收") . "</a>" : "") . "\n"
                    , 1);
                if ($arr["finish"] == "no") tr("追加悬赏", "<form action=viewrequests.php method=post> <input type=hidden name=action value=addamount><input type=hidden name=reqid value=" . $arr["id"] . "><input size=6 name=amount value=1000 ><input type=submit value=提交 > 追加悬赏每次将扣减25个魔力值作为手续费</form>", 1);
                tr("介绍", format_comment(unesc($arr["descr"])), 1);
                $limit = ($arr['finish'] == "no" ? "" : " AND chosen = 'yes' ");
                $ress = "";
                if (mysql_num_rows($res) == 0) $ress = "还没有应求";
                else {
                    if ($arr['userid'] == $CURUSER['id'] || get_user_class() >= 13)
                        $ress .= "<form action=viewrequests.php method=post>\n<input type=hidden name=action value=confirm > <input type=hidden name=id value=" . $id . " >\n";
                    while ($row = mysql_fetch_array($res)) {
                        $each = mysql_fetch_assoc(sql_query("SELECT * FROM torrents WHERE id = '" . $row["torrentid"] . "'"));
                        if (mysql_num_rows(sql_query("SELECT * FROM torrents WHERE id = '" . $row["torrentid"] . "'")) == 1)
                            $ress .= (($arr['userid'] == $CURUSER['id'] || get_user_class() >= 13) && $arr['finish'] == "no" ? "<input type=checkbox name=torrentid[] value=" . $each["id"] . ">" : "") . "<a href='details.php?id=" . $each["id"] . "&hit=1' >" . $each["name"] . "</a> " . ($arr['finish'] == "no" ? "" : "by " . get_username($each[owner])) . "<br/>\n";
                    }
                    $ress .= "";

                    if (($arr['userid'] == $CURUSER['id'] || get_user_class() >= 13) && $arr['finish'] == "no")
                        $ress .= "<input type=submit value=使用勾选的资源作为所需资源>\n";
                    $ress .= "</form>\n";
                }
                tr("应求", $ress, 1);
                print("</table><br/><br/>\n");


                $count = get_row_count("comments", "WHERE request=" . sqlesc($_GET["id"]));
                if ($count) {
                    print("<br /><br />");
                    print("<h1 align=\"center\" id=\"startcomments\">评论</h1>\n");
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
                quickreply('comment', 'body', "添加");
                print("</form></td></tr></table>");


                print ("
	
<a class=\"index\" href='comment.php?action=add&pid=$id&type=request'>添加评论</a></td></tr></table>");

                stdfoot();

            } else stderr("出错了！！！", "ID不存在");
            die;
            break;
        }

        case "edit":
        {
            if (!is_numeric($_GET["id"])) stderr("出错了！！！", "求种ID必须为数字");
            $res = sql_query("SELECT * FROM requests WHERE id ='" . $_GET["id"] . "'") or sqlerr(__FILE__, __LINE__);
            if (mysql_num_rows($res) == 0) stderr("出错了！", "该求种已被删除！");
            $arr = mysql_fetch_assoc($res);
            if ($arr["finish"] == "yes") stderr("出错了！", "该求种已完成！");
            if ($arr['userid'] == $CURUSER['id'] || get_user_class() >= 13) {
                stdhead("编辑求种");
                print(
                    "<form id=edit method=post name=edit action=viewrequests.php >\n
		<input type=hidden name=action  value=takeedit >
		<input type=hidden name=reqid  value=" . $_GET["id"] . " >
		");
                print("<table width=940 cellspacing=0 cellpadding=3><tr><td class=colhead align=center colspan=2>编辑求种</td></tr>");
                tr("标题：", "<input name=request value=\"" . $arr["request"] . "\" size=134 ><br/>", 1);
                print("<tr><td class=rowhead align=right valign=top><b>介绍：</b></td><td class=rowfollow align=left>");
                textbbcode("edit", "descr", $arr["descr"]);
                print("</td></tr>");
                print("</td></tr><tr><td class=toolbox align=center colspan=2><input id=qr type=submit class=btn value=编辑求种 ></td></tr></table></form><br />\n");
                stdfoot();
                die;
            } else stderr("出错了！！！", "你没有该权限！！！<a href='viewrequests.php?action=view&id=" . $_GET["id"] . "'>点击这里返回</a>", 0);
        }

        case "new":
        {
            if (get_user_class() >= 1) {
                stdhead("新增求种");
                print(
                "<form id=edit method=post name=edit action=viewrequests.php >\n<input type=hidden name=action  value=takeadded >\n");
                print("<table width=940 cellspacing=0 cellpadding=3><tr><td class=colhead align=center colspan=2>新增求种</td></tr>\n");
                tr("标题：", "<input name=request size=134><br/>", 1);
                tr("悬赏：", "<input name=amount size=11 value=2000>赏金不得低于100魔力值，每次求种将扣去100魔力值作为手续费。<br/>", 1);
                print("<tr><td class=rowhead align=right valign=top><b>介绍：</b></td><td class=rowfollow align=left>");
                textbbcode("edit", "descr", $arr["descr"]);
                print("</td></tr>");
                print("<tr><td class=toolbox style=vertical-align: middle; padding-top: 10px; padding-bottom: 10px; align=center colspan=2><input id=qr type=submit value=新增求种 class=btn /></td></tr></table></form><br />\n");

                stdfoot();
                die;
            } else stderr("出错了！！！", "你没有该权限！！！<a href='viewrequests.php'>点击这里返回</a>", 0);
        }

        case "newmessage":
        {

            {
                stdhead("回复");


                //<input type=hidden name=id value=$id ><br />");
//quickreply('reply', 'message', "我要留言");
//print("</form></td></tr></table>");

                $ruserid = 0 + $_GET["userid"];


                print(
                    "<form id=reply name=reply method=post action=viewrequests.php >\n<input type=hidden name=action value=message ><input type=hidden name=id value=" . $_GET["id"] . " >\n");
                print("<table width=940 cellspacing=0 cellpadding=3>\n");

                print("<tr><td class=rowfollow align=left>");
                if ($ruserid) {
                    textbbcode("reply", "message", "[b]回复:" . get_plain_username($ruserid) . "[/b]\n");
                    print("<input id=ruserid type=hidden value=$ruserid />");
                } else
                    textbbcode("reply", "message");
                print("</td></tr>");
                print("</table><input id=qr type=submit value=添加评论 class=btn /></form><br />\n");

                stdfoot();
                die;
            }

        }
        case "search":
        {

            {
                stdhead("搜索");


                print("<table border=1 cellspacing=0  cellpadding=5>\n");
                print("<tr><td class=colhead align=left>搜索</td></tr>\n");
                print("<tr><td class=toolbox align=left><form  method=\"post\" action='viewrequests.php'>\n");
                print("<input type=\"text\" name=\"query\" style=\"width:500px\" >\n");
                print("<input type=\"hidden\" name=\"action\" value='list'>");
                print("<input type=submit value='搜索'></form>\n");
                print("</td></tr></table><br />\n");


                stdfoot();
                die;
            }

        }
        case "takeadded":
        {
            if (!$_POST["descr"]) stderr("出错了！", "介绍未填！<a href='viewrequests.php?action=new'>点击这里返回</a>", 0);
            if (!$_POST["request"]) stderr("出错了！", "名称未填！<a href='viewrequests.php?action=new'>点击这里返回</a>", 0);
            if (!$_POST["amount"]) stderr("出错了！", "赏金未填！<a href='viewrequests.php?action=new'>点击这里返回</a>", 0);
            if (!is_numeric($_POST["amount"])) stderr("出错了！！！", "赏金必须为数字！<a href=viewrequests.php?action=new>点击这里返回</a>", 0);
            $amount = $_POST["amount"];
            if ($amount < 100) stderr("出错了！", "发布求种赏金不得小于100个魔力值！<a href='viewrequests.php?action=new'>点击这里返回</a>", 0);
            if ($amount > 10000) stderr("出错了！", "发布求种赏金不得大于10000个魔力值！<a href='viewrequests.php?action=new'>点击这里返回</a>", 0);
            $amount += 100;
            if ($amount + 100 > $CURUSER['seedbonus']) stderr("出错了！", "你没有那么多魔力值！！！<a href='viewrequests.php?action=new'>点击这里返回</a>", 0);
            if (get_user_class() >= 1) {
                sql_query("UPDATE users SET seedbonus = seedbonus - " . $amount . " WHERE id = " . $CURUSER['id']);
                sql_query("INSERT requests ( request , descr, ori_descr ,amount , ori_amount , userid ,added ) VALUES ( " . sqlesc($_POST["request"]) . " , " . sqlesc($_POST["descr"]) . " , " . sqlesc($_POST["descr"]) . " , " . sqlesc($_POST["amount"]) . " , " . sqlesc($_POST["amount"]) . " , " . sqlesc($CURUSER['id']) . " , '" . date("Y-m-d H:i:s") . "' )") or sqlerr(__FILE__, __LINE__);
//                shoutbox_into('[rid' . ($id = mysql_insert_id()) . ']');
                $id = mysql_insert_id();
                stderr("成功", "新增求种成功，<a href='viewrequests.php?action=view&id=" . $id . "'>点击这里返回</a>", 0);
            } else stderr("出错了！！！", "你没有该权限！！！<a href='viewrequests.php'>点击这里返回</a>", 0);
            die;
            break;
        }

        case "takeedit":
        {
            if (!is_numeric($_POST["reqid"])) stderr("出错了！！！", "求种ID必须为数字！<a href='viewrequests.php?action=edit&id=" . $_POST["reqid"] . "'>点击这里返回</a>", 0);
            $res = sql_query("SELECT * FROM requests WHERE id ='" . $_POST["reqid"] . "'") or sqlerr(__FILE__, __LINE__);
            if (!$_POST["descr"]) stderr("出错了！！！", "介绍未填！<a href='viewrequests.php?action=edit&id=" . $_POST["reqid"] . "'>点击这里返回</a>", 0);
            if (!$_POST["request"]) stderr("出错了！！！", "名称未填！<a href='viewrequests.php?action=edit&id=" . $_POST["reqid"] . "'>点击这里返回</a>", 0);
            if (mysql_num_rows($res) == 0) stderr("出错了！", "该求种已被删除！<a href='viewrequests.php'>点击这里返回</a>", 0);
            $arr = mysql_fetch_assoc($res);
            if ($arr["finish"] == "yes") stderr("出错了！", "该求种已完成！<a href='viewrequests.php?action=view&id=" . $_POST["reqid"] . "'>点击这里返回</a>", 0);
            if ($arr['userid'] == $CURUSER['id'] || get_user_class() >= 13) {
                sql_query("UPDATE requests SET descr = " . sqlesc($_POST["descr"]) . " , request = " . sqlesc($_POST["request"]) . " WHERE id ='" . $_POST["reqid"] . "'") or sqlerr(__FILE__, __LINE__);
                stderr("成功", "编辑成功，<a href='viewrequests.php?action=view&id=" . $_POST["reqid"] . "'>点击这里返回</a>", 0);
            } else stderr("出错了！！！", "你没有该权限！！！<a href='viewrequests.php?action=view&id=" . $_POST["reqid"] . "'>点击这里返回</a>", 0);
            die;
            break;
        }

        case "res":
        {
            stdhead("应求");
            stdmsg("我要应求", "
	<form action=viewrequests.php method=post>
	<input type=hidden name=action value=takeres />
	<input type=hidden name=reqid value=\"" . $_GET["id"] . "\" />
	请输入种子的ID:http://$BASEURL/details.php?id=<input type=text name=torrentid size=11/>
	<input type=submit value=提交></form><a href='viewrequests.php?action=view&id=" . $_GET["id"] . "'>点击这里返回</a>", 0);
            stdfoot();
            die;
            break;
        }

        case "takeres":
        {
            if (!is_numeric($_POST["reqid"])) stderr("出错了！！！", "不要试图入侵系统！");
            $res = sql_query("SELECT * FROM requests WHERE id ='" . $_POST["reqid"] . "'") or sqlerr(__FILE__, __LINE__);
            if (mysql_num_rows($res) == 0) stderr("出错了！", "该求种已被删除！<a href='viewrequests.php'>点击这里返回</a>", 0);
            $arr = mysql_fetch_assoc($res);
            if ($arr["finish"] == "yes") stderr("出错了！", "该求种已完成！<a href='viewrequests.php?action=view&id=" . $_POST["reqid"] . "'>点击这里返回</a>", 0);
            if (!is_numeric($_POST["torrentid"])) stderr("出错了！！！", "种子ID必须为数字！<a href='viewrequests.php?action=res&id=" . $_POST["reqid"] . "'>点击这里返回</a>", 0);
            $res = sql_query("SELECT * FROM torrents WHERE id ='" . $_POST["torrentid"] . "'") or sqlerr(__FILE__, __LINE__);
            if (mysql_num_rows($res) == 0) stderr("出错了！", "该种子不存在！<a href='viewrequests.php?action=res&id=" . $_POST["reqid"] . "'>点击这里返回</a>", 0);
            $tor = mysql_fetch_assoc($res);
            if ($tor[last_seed] == "0000-00-00 00:00:00") stderr("出错了！！！", "该种子尚未正式发布！<a href='viewrequests.php?action=res&id=" . $_POST["reqid"] . "'>点击这里返回</a>", 0);
            if (get_row_count('resreq', "where reqid ='" . $_POST["reqid"] . "' and torrentid='" . $_POST["torrentid"] . "'"))
                stderr("出错了！！！", "该应求已经存在！<a href='viewrequests.php?action=res&id=" . $_POST["reqid"] . "'>点击这里返回</a>", 0);
            sql_query("INSERT resreq (reqid , torrentid) VALUES ( '" . $_POST["reqid"] . "' , '" . $_POST["torrentid"] . "')");


            $added = sqlesc(date("Y-m-d H:i:s"));
            $subject = sqlesc("有人应求你的求种请求,请及时确认该应求");
            $notifs = sqlesc("求种名称:[url=viewrequests.php?id=$arr[id]] " . $arr['request'] . "[/url],请及时确认该应求.");
            sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) VALUES(0, " . $arr['userid'] . ", $subject, $notifs, $added)") or sqlerr(__FILE__, __LINE__);


            stderr("成功", "应求成功，<a href='viewrequests.php?action=view&id=" . $_POST["reqid"] . "'>点击这里返回</a>", 0);
            die;
            break;
        }

        case "addamount":
        {
            if (!is_numeric($_POST["reqid"])) stderr("出错了！！！", "不要试图入侵系统");
            $res = sql_query("SELECT * FROM requests WHERE id ='" . $_POST["reqid"] . "'") or sqlerr(__FILE__, __LINE__);
            if (mysql_num_rows($res) == 0) stderr("出错了！", "该求种已被删除！");
            $arr = mysql_fetch_assoc($res);
            if ($arr["finish"] == "yes") stderr("出错了！", "该求种已完成！");
            if (!is_numeric($_POST["amount"])) stderr("出错了！", "赏金必须为数字！");
            $amount = $_POST["amount"];
            if ($amount < 100) stderr("出错了！", "追加悬赏赏金不得小于100个魔力值！");
            if ($amount > 5000) stderr("出错了！", "追加悬赏赏金不得大于5000个魔力值！");
            $amount += 25;
            if ($amount > $CURUSER['seedbonus']) stderr("出错了！", "你没有那么多魔力值！");
            sql_query("UPDATE users SET seedbonus = seedbonus - " . $amount . " WHERE id = " . $CURUSER['id']);
            sql_query("UPDATE requests SET amount = amount + " . $_POST["amount"] . " WHERE id = " . $_POST["reqid"]);
            stderr("成功", "追加悬赏成功，<a href='viewrequests.php?action=view&id=" . $_POST["reqid"] . "'>点击这里返回</a>", 0);
            die;
            break;
        }

        case "delete":
        {
            if (!is_numeric($_GET["id"])) stderr("出错了！！！", "求种ID必须为数字");
            $res = sql_query("SELECT * FROM requests WHERE id ='" . $_GET["id"] . "'") or sqlerr(__FILE__, __LINE__);
            if (mysql_num_rows($res) == 0) stderr("出错了！", "该求种已被删除！");
            $arr = mysql_fetch_assoc($res);
            if (get_user_class() >= 13 || $arr['userid'] == $CURUSER["id"] && $arr['finish'] == 'no') {
                if (!get_row_count("resreq", "WHERE reqid=" . sqlesc($_GET["id"]))) {
                    KPS("+", $arr['amount'] * 8 / 10, $arr['userid']);
                }
                sql_query("DELETE FROM requests WHERE id ='" . $_GET["id"] . "'") or sqlerr(__FILE__, __LINE__);
                sql_query("DELETE FROM resreq WHERE reqid ='" . $_GET["id"] . "'") or sqlerr(__FILE__, __LINE__);
                sql_query("DELETE FROM comments WHERE request ='" . $_GET["id"] . "'") or sqlerr(__FILE__, __LINE__);
                stderr("成功", "删除求种成功，<a href='viewrequests.php'>点击这里返回</a>", 0);
            } else stderr("出错了！！！", "你没有该权限！！！");
            die;
            break;
        }

        case "confirm":
        {
            if (!is_numeric($_POST["id"])) stderr("出错了！！！", "不要试图入侵系统");
            $res = sql_query("SELECT * FROM requests WHERE id ='" . $_POST["id"] . "'") or sqlerr(__FILE__, __LINE__);
            if (mysql_num_rows($res) == 0) stderr("出错了！", "该求种已被删除！");
            $arr = mysql_fetch_assoc($res);
            if (empty($_POST["torrentid"])) stderr("出错了！", "你没有选择符合条件的应求！");
            else $torrentid = $_POST["torrentid"];
            if ($arr['userid'] == $CURUSER['id'] || get_user_class() >= 13) {
                $amount = $arr["amount"] / count($torrentid);
                sql_query("UPDATE requests SET finish = 'yes' WHERE id = " . $_POST["id"]);
                sql_query("UPDATE resreq SET chosen = 'yes' WHERE reqid = " . $_POST["id"] . " AND ( torrentid = '" . join("' OR torrentid = '", $torrentid) . "' )") or sqlerr(__FILE__, __LINE__);
                sql_query("DELETE FROM resreq WHERE reqid ='" . $_POST["id"] . "' AND chosen = 'no'") or sqlerr(__FILE__, __LINE__);
                $res = sql_query("SELECT owner FROM torrents WHERE ( id = '" . join("' OR id = '", $torrentid) . "' ) ") or sqlerr(__FILE__, __LINE__);
                while ($row = mysql_fetch_array($res)) {

                    $owner[] = $row[0];
                    $added = sqlesc(date("Y-m-d H:i:s"));
                    $subject = sqlesc("你的种子被人应求");
                    $notifs = sqlesc("求种名称:[url=viewrequests.php?id=$arr[id]] " . $arr['request'] . "[/url].你获得: $amount 魔力值");
                    sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) VALUES(0, " . $row[0] . ", $subject, $notifs, $added)") or sqlerr(__FILE__, __LINE__);

                }
                sql_query("UPDATE users SET seedbonus = seedbonus + $amount WHERE id = '" . join("' OR id = '", $owner) . "'") or sqlerr(__FILE__, __LINE__);
                stderr("成功", "确认成功，<a href='viewrequests.php?action=view&id=" . $_POST["id"] . "'>点击这里返回</a>", 0);

            }

        }

        case "message":
        {
            if (!is_numeric($_POST["id"])) stderr("出错了！！！", "不要试图入侵系统");
            $res = sql_query("SELECT * FROM requests WHERE id ='" . $_POST["id"] . "'") or sqlerr(__FILE__, __LINE__);
            if (mysql_num_rows($res) == 0) stderr("出错了！", "该求种已被删除！");
            if (!$_POST["message"]) stderr("出错了！", "留言不能为空！");
            $arr = mysql_fetch_assoc($res);
            $message = $arr["message"];
            $message .= "<tr><td width=240>由" . $CURUSER["username"] . "添加于" . date("Y-m-d H:i:s") . "</td><td>" . $_POST["message"] . "</td></tr>";


            //sql_query("UPDATE requests SET message = '".$message."' WHERE id = ".$_POST["id"])or sqlerr(__FILE__, __LINE__);

            //sql_query("INSERT reqcommen (user , added ,text ,reqid) VALUES ( '".$CURUSER["id"]."' , ".sqlesc(date("Y-m-d H:i:s"))." , ".sqlesc($_POST["message"])." , '".$_POST["id"]."'    )");
            sql_query("INSERT INTO comments (user, request, added, text, ori_text) VALUES (" . $CURUSER["id"] . ",{$_POST['id']}, '" . date("Y-m-d H:i:s") . "', " . sqlesc($_POST["message"]) . "," . sqlesc($_POST["message"]) . ")");

            if ($CURUSER["id"] <> $arr['userid']) sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) VALUES(0, " . $arr['userid'] . ", '你的求种请求收到新回复', " . sqlesc(" [url=viewrequests.php?action=view&id={$_POST['id']}] " . $arr['request'] . "[/url].") . ", " . sqlesc(date("Y-m-d H:i:s")) . ")") or sqlerr(__FILE__, __LINE__);

            $ruserid = 0 + $_POST["ruserid"];
            if ($ruserid <> $CURUSER["id"] && $ruserid <> $arr['userid']) sql_query("INSERT INTO messages (sender, receiver, subject, msg, added) VALUES(0, " . $ruserid . ", '你的求种评论收到新回复', " . sqlesc(" [url=viewrequests.php?action=view&id={$_POST['id']}] " . $arr['request'] . "[/url].") . ", " . sqlesc(date("Y-m-d H:i:s")) . ")") or sqlerr(__FILE__, __LINE__);

            header("Location: viewrequests.php?action=view&id=" . $_POST['id']);
        }
    }


}
die;


?>
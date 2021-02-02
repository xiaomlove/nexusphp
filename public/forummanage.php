<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
//lots of place use this variable
$prefix = '';
$user = $CURUSER;
$PHP_SELF = $_SERVER['PHP_SELF'];

if (get_user_class() < $forummanage_class)
    permissiondenied();

// DELETE FORUM ACTION
if (isset($_GET['action']) && $_GET['action'] == "del") {
	$id = intval($_GET['id'] ?? 0);
	if (!$id) {
		header("Location: forummanage.php");
		die();
	}
	$result = sql_query ("SELECT * FROM topics where forumid = ".sqlesc($id));
	if ($row = mysql_fetch_array($result)) {
		do {
			sql_query ("DELETE FROM posts where topicid = ".$row["id"]) or sqlerr(__FILE__, __LINE__);
		} while($row = mysql_fetch_array($result));
	}
	sql_query ("DELETE FROM topics where forumid = ".sqlesc($id)) or sqlerr(__FILE__, __LINE__);
	sql_query ("DELETE FROM forums where id = ".sqlesc($id)) or sqlerr(__FILE__, __LINE__);
	sql_query ("DELETE FROM forummods where forumid = ".sqlesc($id)) or sqlerr(__FILE__, __LINE__);
	$Cache->delete_value('forums_list');
	$Cache->delete_value('forum_moderator_array');
	header("Location: forummanage.php");
	die();
}

//EDIT FORUM ACTION
elseif (isset($_POST['action']) && $_POST['action'] == "editforum") {
	$name = $_POST['name'];
	$desc = $_POST['desc'];
	$id = $_POST['id'];
	if (!$name && !$desc && !$id) {
		header("Location: " . get_protocol_prefix() . "$BASEURL/forummanage.php");
		die();
	}
	if (!empty($_POST["moderator"])) {
	$moderator = $_POST["moderator"];
	set_forum_moderators($moderator,$id);
	}
	else{
		sql_query("DELETE FROM forummods WHERE forumid=".sqlesc($id)) or sqlerr(__FILE__, __LINE__);
	}
	sql_query("UPDATE forums SET sort = " . sqlesc($_POST['sort']) . ", name = " . sqlesc($_POST['name']). ", description = " . sqlesc($_POST['desc']). ", forid = ".sqlesc(($_POST['overforums'])).", minclassread = " . sqlesc($_POST['readclass']) . ", minclasswrite = " . sqlesc($_POST['writeclass']) . ", minclasscreate = " . sqlesc($_POST['createclass']) . " where id = ".sqlesc($id)) or sqlerr(__FILE__, __LINE__);
	$Cache->delete_value('forums_list');
	$Cache->delete_value('forum_moderator_array');
	header("Location: forummanage.php");
	die();
}

//ADD FORUM ACTION
elseif (isset($_POST['action']) && $_POST['action'] == "addforum") {
	$name = ($_POST['name']);
	$desc = ($_POST['desc']);
	if (!$name && !$desc) {
		header("Location: " . get_protocol_prefix() . "$BASEURL/forummanage.php");
		die();
	}
	sql_query("INSERT INTO forums (sort, name,  description, minclassread,  minclasswrite, minclasscreate, forid) VALUES(" . sqlesc($_POST['sort']) . ", " . sqlesc($_POST['name']). ", " . sqlesc($_POST['desc']). ", " . sqlesc($_POST['readclass']) . ", " . sqlesc($_POST['writeclass']) . ", " . sqlesc($_POST['createclass']) . ", ".sqlesc(($_POST['overforums'])).")") or sqlerr(__FILE__, __LINE__);
	$Cache->delete_value('forums_list');
	if ($_POST["moderator"]){
	$id = mysql_insert_id();
	$moderator = $_POST["moderator"];
	set_forum_moderators($moderator,$id);
	}
	header("Location: forummanage.php");
	die();
}

// SHOW FORUMS WITH FORUM MANAGMENT TOOLS
stdhead($lang_forummanage['head_forum_management']);
begin_main_frame();
if (isset($_GET['action']) && $_GET['action'] == "editforum") {
	//EDIT PAGE FOR THE FORUMS
	$id = intval($_GET["id"] ?? 0);
	$result = sql_query ("SELECT * FROM forums where id = ".sqlesc($id));
	if ($row = mysql_fetch_array($result)) {
		do {
?>
<h1 align=center><a class=faqlink href=forummanage.php><?php echo $lang_forummanage['text_forum_management']?></a><b>--></b><?php echo $lang_forummanage['text_edit_forum']?></h2>
<br />
<form method=post action="<?php echo $_SERVER["PHP_SELF"];?>">
<table width="100%"  border="0" cellspacing="0" cellpadding="3" align="center">
<tr align="center">
    <td colspan="2" class=colhead><?php echo $lang_forummanage['text_edit_forum']?> -- <?php echo htmlspecialchars($row["name"]);?></td>
  </tr>

    <td><b><?php echo $lang_forummanage['row_forum_name']?></td>
    <td><input name="name" type="text" style="width: 200px" maxlength="60" value="<?php echo $row["name"];?>"></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_forum_description']?></td>
    <td><input name="desc" type="text" style="width: 400px" maxlength="200" value="<?php echo $row["description"];?>"></td>
  </tr>


    <tr>
    <td><b><?php echo $lang_forummanage['row_overforum']?></td>
    <td>
    <select name=overforums>
    <?php
            $forid = $row["forid"];
            $res = sql_query("SELECT * FROM overforums");
             while ($arr = mysql_fetch_array($res)) {

             $name = $arr["name"];
             $i = $arr["id"];

            print("<option value=$i" . ($forid == $i ? " selected" : "") . ">$prefix" . $name . "\n");
            }


?>
        </select>
    </td>
  </tr>
<?php
		$username = get_forum_moderators($row['id'],true);
?>
  <tr><td><b><?php echo $lang_forummanage['row_moderator']?></b></td><td><input name="moderator" type="text" style="width: 200px" maxlength="200" value="<?php echo $username?>">&nbsp;<?php echo $lang_forummanage['text_moderator_note']?></td></tr>
    <tr>
    <td><b><?php echo $lang_forummanage['row_minimum_read_permission']?></td>
    <td>
    <select name=readclass>
<?php
             $maxclass = get_user_class();
          for ($i = 0; $i <= $maxclass; ++$i)
            print("<option value=$i" . ($row["minclassread"] == $i ? " selected" : "") . ">$prefix" . get_user_class_name($i,false,true,true));
?>
        </select>
    </td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_minimum_write_permission']?></td>
    <td><select name=writeclass>
<?php
              $maxclass = get_user_class();
          for ($i = 0; $i <= $maxclass; ++$i)
            print("<option value=$i" . ($row["minclasswrite"] == $i ? " selected" : "") . ">$prefix" . get_user_class_name($i,false,true,true) . "\n");
?>
        </select></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_minimum_create_topic_permission']?></td>
    <td><select name=createclass>
<?php
            $maxclass = get_user_class();
          for ($i = 0; $i <= $maxclass; ++$i)
            print("<option value=$i" . ($row["minclasscreate"] == $i ? " selected" : "") . ">$prefix" . get_user_class_name($i,false,true,true) . "\n");
?>
        </select></td>
  </tr>
    <tr>
    <td><b><?php echo $lang_forummanage['row_forum_order']?></td>
    <td>
    <select name=sort>
<?php
$res = sql_query ("SELECT sort FROM forums");
$nr = mysql_num_rows($res);
            $maxclass = $nr + 1;
          for ($i = 0; $i <= $maxclass; ++$i)
            print("<option value=$i" . ($row["sort"] == $i ? " selected" : "") . ">$i \n");
?>
        </select>
    <?php echo $lang_forummanage['text_forum_order_note']?></td>
  </tr>

  <tr align="center">
    <td colspan="2"><input type="hidden" name="action" value="editforum"><input type="hidden" name="id" value="<?php echo $id;?>"><input type="submit" name="Submit" value="<?php echo $lang_forummanage['submit_edit_forum']?>" class="btn"></td>
  </tr>
</table>

<?php
		} while($row = mysql_fetch_array($result));
	} 
	else 
	{
	print ($lang_forummanage['text_no_records_found']);
	}
}
//
elseif (isset($_GET['action']) && $_GET['action'] == "newforum"){
?>
<h2 class=transparentbg align=center><a class=faqlink href=forummanage.php><?php echo $lang_forummanage['text_forum_management']?></a><b>--></b><?php echo $lang_forummanage['text_add_forum']?></h2>
<br />
<form method=post action="<?php echo $_SERVER["PHP_SELF"];?>">
<table width="100%"  border="0" cellspacing="0" cellpadding="3" align="center">
<tr align="center">
    <td colspan="2" class=colhead><?php echo $lang_forummanage['text_make_new_forum']?></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_forum_name']?></td>
    <td><input name="name" type="text" style="width: 200px" maxlength="60"></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_forum_description']?></td>
    <td><input name="desc" type="text" style="width: 400px" maxlength="200"></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_overforum']?></td>
    <td>
    <select name=overforums>
<?php
            $forid = $row["forid"] ?? 0;
            $res = sql_query("SELECT * FROM overforums");
             while ($arr = mysql_fetch_array($res)) {

             $name = $arr["name"];
             $i = $arr["id"];

            print("<option value=$i" . ($forid == $i ? " selected" : "") . ">$prefix" . $name . "\n");
            }
?>
        </select>
    </td>
  </tr>
	<tr><td><b><?php echo $lang_forummanage['row_moderator']?></b></td><td><input name="moderator" type="text" style="width: 200px" maxlength="200">&nbsp;<?php echo $lang_forummanage['text_moderator_note']?></td></tr>
    <tr>
    <td><b><?php echo $lang_forummanage['row_minimum_read_permission']?></td>
    <td>
    <select name=readclass>
<?php
             $maxclass = get_user_class();
          for ($i = 0; $i <= $maxclass; ++$i)
            print("<option value=$i" . ($user["class"] == $i ? " selected" : "") . ">$prefix" . get_user_class_name($i,false,true,true) . "\n");
?>
        </select>
    </td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_minimum_write_permission']?></td>
    <td><select name=writeclass>
<?php
              $maxclass = get_user_class();
          for ($i = 0; $i <= $maxclass; ++$i)
            print("<option value=$i" . ($user["class"] == $i ? " selected" : "") . ">$prefix" . get_user_class_name($i,false,true,true) . "\n");
?>
        </select></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_forummanage['row_minimum_create_topic_permission']?></td>
    <td><select name=createclass>
<?php
            $maxclass = get_user_class();
          for ($i = 0; $i <= $maxclass; ++$i)
            print("<option value=$i" . ($user["class"] == $i ? " selected" : "") . ">$prefix" . get_user_class_name($i,false,true,true) . "\n");
?>
        </select></td>
  </tr>
    <tr>
    <td><b><?php echo $lang_forummanage['row_forum_order']?></td>
    <td>
    <select name=sort>
<?php
$res = sql_query ("SELECT sort FROM forums");
$nr = mysql_num_rows($res);
            $maxclass = $nr + 1;
          for ($i = 0; $i <= $maxclass; ++$i)
            print("<option value=$i>$i \n");
?>
        </select>
    <?php echo $lang_forummanage['text_forum_order_note']?></td>
  </tr>

  <tr align="center">
    <td colspan="2"><input type="hidden" name="action" value="addforum"><input type="submit" name="Submit" value="<?php echo $lang_forummanage['submit_make_forum']?>" class=btn></td>
  </tr>
</table>
<?php
}
else {
?>
<h2 class=transparentbg align=center><?php echo $lang_forummanage['text_forum_management']?></h2>
<table border=0 class=main cellspacing=0 cellpadding=5 width=1%><tr>
<td class=embedded align=left><form method="get" action="moforums.php"><input type="submit" value="<?php echo $lang_forummanage['submit_overforum_management']?>" class="btn"></form></td><td class=embedded align=left><form method="get" action="forummanage.php"><input type=hidden name="action" value="newforum"><input type="submit" value="<?php echo $lang_forummanage['submit_add_forum']?>" class="btn"></form></td>
</tr></table>
<?php
echo '<table width="100%"  border="0" align="center" cellpadding="2" cellspacing="0">';
echo "<tr><td class=colhead align=left>".$lang_forummanage['col_name']."</td><td class=colhead>".$lang_forummanage['col_overforum']."</td><td class=colhead>".$lang_forummanage['col_read']."</td><td class=colhead>".$lang_forummanage['col_write']."</td><td class=colhead>".$lang_forummanage['col_create_topic']."</td><td class=colhead>".$lang_forummanage['col_moderator']."</td><td class=colhead>".$lang_forummanage['col_modify']."</td></tr>";
$result = sql_query ("SELECT forums.*, overforums.name AS of_name FROM forums LEFT JOIN overforums ON forums.forid=overforums.id ORDER BY forums.sort ASC");
if ($row = mysql_fetch_array($result)) {
do {
$name = $row['of_name'];
$moderators = get_forum_moderators($row['id'],false);
if (!$moderators)
	$moderators = $lang_forummanage['text_not_available'];
echo "<tr><td><a href=forums.php?action=viewforum&forumid=".$row["id"]."><b>".htmlspecialchars($row["name"])."</b></a><br />".htmlspecialchars($row["description"])."</td>";
echo "<td>".htmlspecialchars($name)."</td><td>" . get_user_class_name($row["minclassread"],false,true,true) . "</td><td>" . get_user_class_name($row["minclasswrite"],false,true,true) . "</td><td>" . get_user_class_name($row["minclasscreate"],false,true,true) . "</td><td>".$moderators."</td><td><b><a href=\"".$PHP_SELF."?action=editforum&id=".$row["id"]."\">".$lang_forummanage['text_edit']."</a>&nbsp;|&nbsp;<a href=\"javascript:confirm_delete('".$row["id"]."', '".$lang_forummanage['js_sure_to_delete_forum']."', '');\"><font color=red>".$lang_forummanage['text_delete']."</font></a></b></td></tr>";
} while($row = mysql_fetch_array($result));
} else {print "<tr><td colspan=6>".$lang_forummanage['text_no_records_found']."</td></tr>";}
echo "</table>";
}

end_main_frame();
stdfoot();

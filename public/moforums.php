<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
user_can('forummanage', true);

//Presets
$act = $_GET['action'] ?? '';
$id = intval($_GET['id'] ?? 0);
$PHP_SELF = $_SERVER['PHP_SELF'];
$user = $CURUSER;
$prefix = '';

if (!$act) {
$act = "forum";
}

// DELETE FORUM ACTION
if ($act == "del") {
user_can('forummanage', true);

if (!$id) { header("Location: $PHP_SELF?action=forum"); die();}

\Nexus\Database\NexusDB::table('overforums')->where('id', (int) $id)->delete();
$Cache->delete_value('overforums_list');
header("Location: $PHP_SELF?action=forum");
die();
}

//EDIT FORUM ACTION
if (isset($_POST['action']) && $_POST['action'] == "editforum") {
user_can('forummanage', true);

$name = $_POST['name'];
$desc = $_POST['desc'];

if (!$name && !$desc && !$id) { header("Location: $PHP_SELF?action=forum"); die();}

\Nexus\Database\NexusDB::table('overforums')->where('id', (int) $_POST['id'])->update([
	'sort' => (int) $_POST['sort'],
	'name' => (string) $_POST['name'],
	'description' => (string) $_POST['desc'],
	'minclassview' => (int) $_POST['viewclass'],
]);
$Cache->delete_value('overforums_list');
header("Location: $PHP_SELF?action=forum");
die();
}

//ADD FORUM ACTION
if (isset($_POST['action']) && $_POST['action'] == "addforum") {
user_can('forummanage', true);

$name = trim($_POST['name']);
$desc = trim($_POST['desc']);

if (!$name && !$desc)
{
	header("Location: $PHP_SELF?action=forum");
    die();
}

\Nexus\Database\NexusDB::insert('overforums', [
	'sort' => (int) $_POST['sort'],
	'name' => (string) $_POST['name'],
	'description' => (string) $_POST['desc'],
	'minclassview' => (int) $_POST['viewclass'],
]);
$Cache->delete_value('overforums_list');

header("Location: $PHP_SELF?action=forum");
die();
}



stdhead($lang_moforums['head_overforum_management']);
begin_main_frame();

if ($act == "forum")
{

// SHOW FORUMS WITH FORUM MANAGMENT TOOLS

?>
<h2 class=transparentbg align=center><a class=faqlink href=forummanage.php><?php echo $lang_moforums['text_forum_management']?></a><b>--></b><?php echo $lang_moforums['text_overforum_management']?></h2>
<br />
<?php
echo '<table width="100%"  border="0" align="center" cellpadding="2" cellspacing="0">';
echo "<tr><td class=colhead align=left>".$lang_moforums['col_name']."</td><td class=colhead>".$lang_moforums['col_viewed_by']."</td><td class=colhead>".$lang_moforums['col_modify']."</td></tr>";
$forumRows = \Nexus\Database\NexusDB::table('overforums')->orderBy('sort')->get();
if (count($forumRows) > 0) {
foreach ($forumRows as $row) {
	$row = (array) $row;

echo "<tr><td><a href=forums.php?action=forumview&forid=".$row["id"]."><b>".htmlspecialchars($row["name"])."</b></a><br />".$row["description"]."</td>";
echo "<td>" . get_user_class_name($row["minclassview"],false,true,true) . "</td><td><b><a href=\"".$PHP_SELF."?action=editforum&id=".$row["id"]."\">".$lang_moforums['text_edit']."</a>&nbsp;|&nbsp;<a href=\"javascript:confirm_delete('".$row["id"]."', '".$lang_moforums['js_sure_to_delete_overforum']."', '');\"><font color=red>".$lang_moforums['text_delete']."</font></a></b></td></tr>";

}
} else {print "<tr><td colspan=3>".$lang_moforums['text_no_records_found']."</td></tr>";}
echo "</table>";
?>
<br /><br />
<form method=post action="<?php echo $PHP_SELF;?>">
<table width="100%"  border="0" cellspacing="0" cellpadding="3" align="center">
<tr align="center">
    <td colspan="2" class=colhead><?php echo $lang_moforums['text_new_overforum']?></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_moforums['text_overforum_name']?></td>
    <td><input name="name" type="text" style="width: 200px" maxlength="60"></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_moforums['text_overforum_description']?></td>
    <td><input name="desc" type="text" style="width: 400px" maxlength="200"></td>
  </tr>

    <tr>
    <td><b><?php echo $lang_moforums['text_minimum_view_permission']?></td>
    <td>
    <select name=viewclass>\n
<?php
	     $maxclass = get_user_class();
	  for ($i = 0; $i <= $maxclass; ++$i)
	    print("<option value=$i" . ($user["class"] == $i ? " selected" : "") . ">$prefix" . get_user_class_name($i,false,true,true) . "\n");
?>
	</select>
    </td>
  </tr>

    <tr>
    <td><b><?php echo $lang_moforums['text_overforum_order']?></td>
    <td>
    <select name=sort>
<?php
$nr = (int) \Nexus\Database\NexusDB::table('overforums')->count();
	    $maxclass = $nr + 1;
	  for ($i = 0; $i <= $maxclass; ++$i)
	    print("<option value=$i>$i \n");
?>
	</select>
    <?php echo $lang_moforums['text_overforum_order_note']?></td>
  </tr>

  <tr align="center">
    <td colspan="2"><input type="hidden" name="action" value="addforum"><input type="submit" name="Submit" value="<?php echo $lang_moforums['submit_make_overforum']?>"></td>
  </tr>
</table>

<?php
}
?>

<?php if ($act == "editforum") {

//EDIT PAGE FOR THE FORUMS
$id = intval($_GET["id"] ?? 0);

$rowObj = \Nexus\Database\NexusDB::table('overforums')->where('id', (int) $id)->first();
if ($rowObj) {
$row = (array) $rowObj;

// Get OverForum Name - To Be Written

?>
<h2 class=transparentbg align=center><a class=faqlink href=forummanage.php><?php echo $lang_moforums['text_forum_management']?></a><b>--></b><a class=faqlink href=moforums.php><?php echo $lang_moforums['text_overforum_management']?></a><b>--></b><?php echo $lang_moforums['text_edit_overforum']?></h2><br />
<form method=post action="<?php echo $PHP_SELF;?>">
<table width="100%"  border="0" cellspacing="0" cellpadding="3" align="center">
<tr align="center">
    <td colspan="2" class=colhead><?php echo $lang_moforums['text_edit_overforum']?> -- <?php echo htmlspecialchars($row["name"]);?></td>
  </tr>

    <td><b><?php echo $lang_moforums['text_overforum_name']?></td>
    <td><input name="name" type="text" style="width: 200px" maxlength="60" value="<?php echo $row["name"];?>"></td>
  </tr>
  <tr>
    <td><b><?php echo $lang_moforums['text_overforum_description']?></td>
    <td><input name="desc" type="text" style="width: 400px" maxlength="200" value="<?php echo $row["description"];?>"></td>
  </tr>


    <tr>
    <td><b><?php echo $lang_moforums['text_minimum_view_permission']?></td>
    <td>
    <select name=viewclass>
<?php
	     $maxclass = get_user_class();
	  for ($i = 0; $i <= $maxclass; ++$i)
	    print("<option value=$i" . ($row["minclassview"] == $i ? " selected" : "") . ">$prefix" . get_user_class_name($i,false,true,true) . "\n");
?>
	</select>
    </td>
  </tr>


    <tr>
    <td><b><?php echo $lang_moforums['text_overforum_order']?></td>
    <td>
    <select name=sort>
<?php
$nr = (int) \Nexus\Database\NexusDB::table('overforums')->count();
	    $maxclass = $nr + 1;
	  for ($i = 0; $i <= $maxclass; ++$i)
	    print("<option value=$i" . ($row["sort"] == $i ? " selected" : "") . ">$i \n");
?>
	</select>
	<?php echo $lang_moforums['text_overforum_order_note']?>
    </td>
  </tr>

  <tr align="center">
    <td colspan="2"><input type="hidden" name="action" value="editforum"><input type="hidden" name="id" value="<?php echo $id;?>"><input type="submit" name="Submit" value="<?php echo $lang_moforums['submit_edit_overforum']?>"></td>
  </tr>
</table>

<?php
} else {print $lang_moforums['text_no_records_found'];}
}
end_main_frame();
stdfoot();

<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
parked();
$id = intval($_GET["id"] ?? 0);
int_check($id,true);
function bark($msg)
{
  global $lang_checkuser;
  stdhead();
  stdmsg($lang_checkuser['std_error'], $msg);
  stdfoot();
  exit;
}

$r = @sql_query("SELECT * FROM users WHERE status = 'pending' AND id = ".sqlesc($id)) or sqlerr(__FILE__, __LINE__);
$user = mysql_fetch_array($r) or bark($lang_checkuser['std_no_user_id']);

if (get_user_class() < UC_MODERATOR) {
	if ($user['invited_by'] != $CURUSER['id'])
		bark($lang_checkuser['std_no_permission']);
}

if ($user["gender"] == "Male") $gender = '<img class="male" src="pic/trans.gif" alt="Male" title="Male" style="margin-left: 4pt">';
elseif ($user["gender"] == "Female") $gender = '<img class="female" src="pic/trans.gif" alt="Female" title="Female" style="margin-left: 4pt">';
elseif ($user["gender"] == "N/A") $gender = '<img class="no_gender" src="pic/trans.gif" alt="N/A" title="No gender" style="margin-left: 4pt">';

if ($user['added'] == "0000-00-00 00:00:00" || $user['added'] == null)
  $joindate = 'N/A';
else
  $joindate = "$user[added] (" . get_elapsed_time(strtotime($user["added"])) . " ago)";

$res = sql_query("SELECT name,flagpic FROM countries WHERE id=$user[country] LIMIT 1") or sqlerr();
if (mysql_num_rows($res) == 1)
{
  $arr = mysql_fetch_assoc($res);
  $country = "<td class=embedded><img src=pic/flag/{$arr['flagpic']} alt=\"$arr[name]\" style='margin-left: 8pt'></td>";
}

stdhead($lang_checkuser['head_detail_for'] . $user["username"]);

$enabled = $user["enabled"] == 'yes';
print("<p><table class=main border=0 cellspacing=0 cellpadding=0>".
"<tr><td class=embedded><h1 style='margin:0px'>" . get_username($user['id'], true, false) . "</h1></td>$country</tr></table></p><br />\n");

if (!$enabled)
  print($lang_checkuser['text_account_disabled']);
?>
<table width=737 border=1 cellspacing=0 cellpadding=5>
<tr><td class=rowhead width=1%><?php echo $lang_checkuser['row_join_date'] ?></td><td align=left width=99%><?php echo $joindate;?></td></tr>
<tr><td class=rowhead width=1%><?php echo $lang_checkuser['row_gender'] ?></td><td align=left width=99%><?php echo $gender;?></td></tr>
<tr><td class=rowhead width=1%><?php echo $lang_checkuser['row_email'] ?></td><td align=left width=99%><a href=mailto:<?php echo $user['email'];?>><?php echo $user['email'];?></a></td></tr>
<?php
if (get_user_class() >= UC_MODERATOR AND $user['ip'] != '')
	print ("<tr><td class=rowhead width=1%>".$lang_checkuser['row_ip']."</td><td align=left width=99%>{$user['ip']}</td></tr>");
print("<form method=post action=takeconfirm.php?id=".htmlspecialchars($id).">");
print("<input type=hidden name=email value={$user['email']}>");
print("<tr><td class=rowhead width=1%><input type=\"checkbox\" name=\"conusr[]\" value=\"" . $id . "\" checked/></td>");
print("<td align=left width=99%><input type=submit style='height: 20px' value=\"".$lang_checkuser['submit_confirm_this_user'] ."\"></form></tr></td></table>");
stdfoot();

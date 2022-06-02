<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
require_once(get_langfile_path());
if (get_user_class() < UC_SYSOP)
	permissiondenied();

$shownotice=false;
stderr("Error", "Hard deletion of users is not recommended and can cause many problems.");
if ($_SERVER["REQUEST_METHOD"] == "POST")
{
	if ($_POST['sure'])
	{
		$res=sql_query("DELETE FROM users WHERE enabled='no'");
		$deletecount=mysql_affected_rows();
		$shownotice=true;
	}
}
stdhead($lang_deletedisabled['head_delete_diasabled']);
begin_main_frame();
?>
<h1 align="center"><?php echo $lang_deletedisabled['text_delete_diasabled']?></h1>
<?php
if ($shownotice)
{
?>
<div style="text-align: center;"><?php echo $deletecount.$lang_deletedisabled['text_users_are_disabled']?></div>
<?php
}
else
{
?>
<div style="text-align: center;"><?php echo $lang_deletedisabled['text_delete_disabled_note']?></div>
<div style="text-align: center; margin-top: 10px;">
<form method="post" action="?">
<input type="hidden" name="sure" value="1" />
<input type="submit" value="<?php echo $lang_deletedisabled['submit_delete_all_disabled_users']?>" />
</form>
</div>
<?php
}
end_main_frame();
stdfoot();
?>

<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
if (get_user_class() < UC_MODERATOR)
stderr("Error", "Access denied.");
$seedBonusAll = [
    1 => 100,
    2 => 1000,
    3 => 10000,
];
$selectName = 'seed_bonus_type';
$select = '<select name="' . $selectName . '">';
foreach ($seedBonusAll as $key => $value) {
    $select .= sprintf('<option value="%s">%s</option>', $key, $value);
}
$select .= '</select>';

if ($_SERVER["REQUEST_METHOD"] == "POST")
{
	if (isset($_POST['doit']) && $_POST['doit'] == 'yes') {
	    if (empty($_POST[$selectName]) || !isset($seedBonusAll[$_POST[$selectName]])) {
            stderr("Error", "Invalid form data: $selectName.");
        }
	    $seedBonusIncrease = $seedBonusAll[$_POST[$selectName]];
		sql_query("UPDATE users SET seedbonus = seedbonus + $seedBonusIncrease WHERE status='confirmed'");
		stderr("Bonus", "$seedBonusIncrease bonus point is sent to everyone...");
		die;
	}

	if ($_POST["username"] == "" || $_POST["seedbonus"] == "" || $_POST["seedbonus"] == "")
	stderr("Error", "Missing form data.");
	$username = sqlesc($_POST["username"]);
	$seedbonus = sqlesc($_POST["seedbonus"]);

	sql_query("UPDATE users SET seedbonus=seedbonus + $seedbonus WHERE username=$username") or sqlerr(__FILE__, __LINE__);
	$res = sql_query("SELECT id FROM users WHERE username=$username");
	$arr = mysql_fetch_row($res);
	if (!$arr)
	stderr("Error", "Unable to update account.");
	header("Location: " . get_protocol_prefix() . "$BASEURL/userdetails.php?id=".htmlspecialchars($arr[0]));
	die;
}
stdhead("Update Users Upload Amounts");
?>
<h1>Update Users Bonus Amounts</h1>
<?php
begin_main_frame("",false, 30);
begin_main_frame("Add to Specific User",false,30);
echo "<form method=\"post\" action=\"amountbonus.php\">";
print("<table width=100% border=1 cellspacing=0 cellpadding=5>\n");
?>
<tr><td class="rowhead">User name</td><td class="rowfollow"><input type="text" name="username" size="30"/></td></tr>
<tr><td class="rowhead">Bonus</td><td class="rowfollow"><input type="text" name="seedbonus" size="5"/></td></tr>
<tr><td colspan="2" class="toolbox" align="center"><input type="submit" value="Okay" class="btn"/></td></tr>
<?php end_table();?>
</form>
<?php end_main_frame();?>
<?php begin_main_frame("Send bonus point to everyone",false,30);?>
<form action="amountbonus.php" method="post">
<table width=100% border=1 cellspacing=0 cellpadding=5>
    <tr>
        <td class="rowhead">Bonus</td>
        <td class="rowfollow"><?php echo $select?></td>
    </tr>
    <tr>
        <td class="toolbox" align="center" colspan="2">
            <input type = "hidden" name = "doit" value = "yes" />
            <input type="submit" class="btn" value="Okay" />
        </td>
    </tr>
<?php end_table();?>
</form>
<?php
end_main_frame();
end_main_frame();
stdfoot();

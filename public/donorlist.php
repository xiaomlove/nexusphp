<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();

if (get_user_class() > UC_MODERATOR) {
	$res = sql_query("SELECT COUNT(*) FROM users WHERE donor='yes'");
	$row = mysql_fetch_array($res);
	$count = $row[0];

	list($pagertop, $pagerbottom, $limit) = pager(50, $count, "donorlist.php?");
	stdhead("Donorlist");
	if (mysql_num_rows($res) == 0)
	begin_main_frame();
	// ===================================
	$users = number_format(get_row_count("users", "WHERE donor='yes'"));
	begin_frame("Donor List ($users)", true);
	begin_table();
	echo $pagerbottom;
?>
<form method="post">
<tr><td class="colhead">ID</td><td class="colhead" align="left">Username</td><td class="colhead" align="left">e-mail</td><td class="colhead" align="left">Joined</td><td class="colhead" align="left">How much?</td></tr>
<?php

$res=sql_query("SELECT id,username,email,added,donated FROM users WHERE donor='yes' ORDER BY id DESC $limit") or print(mysql_error());
// ------------------
while ($arr = @mysql_fetch_assoc($res)) {
	echo "<tr><td>" . $arr['id'] . "</td><td align=\"left\">" . get_username($arr['id']) . "</td><td align=\"left\"><a href=mailto:" . $arr['email'] . ">" . $arr['email'] . "</a></td><td align=\"left\">" . $arr['added'] . "</a></td><td align=\"left\">$" . $arr['donated'] . "</td></tr>";
}
?>

</form>
<?php
// ------------------
end_table();
end_frame();
// ===================================
end_main_frame();
stdfoot();
}
else {
	stderr("Sorry", "Access denied!");
}

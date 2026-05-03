<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();

if (get_user_class() > UC_MODERATOR) {
	$count = (int) \Nexus\Database\NexusDB::table('users')->where('donor', 'yes')->count();

	[$pagertop, $pagerbottom, $limit, $offsetStart, $rowsPerPage] = pager(50, $count, "donorlist.php?");
	stdhead("Donorlist");
	if ($count == 0)
	begin_main_frame();
	// ===================================
	$users = number_format($count);
	begin_frame("Donor List ($users)", true);
	begin_table();
	echo $pagerbottom;
?>
<form method="post">
<tr><td class="colhead">ID</td><td class="colhead" align="left">Username</td><td class="colhead" align="left">e-mail</td><td class="colhead" align="left">Joined</td><td class="colhead" align="left">How much?</td></tr>
<?php

$donorRows = \Nexus\Database\NexusDB::table('users')->where('donor', 'yes')->orderByDesc('id')->offset((int) $offsetStart)->limit((int) $rowsPerPage)->select(['id', 'username', 'email', 'added', 'donated'])->get();
// ------------------
foreach ($donorRows as $arr) {
	$arr = (array) $arr;
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

<?php
ob_start();
require_once("include/bittorrent.php");
dbconn();

if (get_user_class() < UC_SYSOP) {
die('forbidden');
}
echo "<html><head><title>Do Clean-up</title></head><body>";
echo "<p>";
echo "clean-up in progress...please wait<br />";
ob_flush();
flush();
if ($_GET['forceall']) {
	$forceall = 1;
} else {
	$forceall = 0;
echo "you may force full clean-up by adding the parameter 'forceall=1' to URL<br />";
}
echo "</p>";
$tstart = getmicrotime();
require_once("include/cleanup.php");
print("<p>".docleanup($forceall, 1)."</p>");
$tend = getmicrotime();
$totaltime = ($tend - $tstart);
printf ("Time consumed:  %f sec<br />", $totaltime);
echo "Done<br />";
echo "</body></html>";

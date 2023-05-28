<?php
ob_start();
require_once("../include/bittorrent.php");
dbconn();

if (get_user_class() < UC_SYSOP) {
die('forbidden');
}
require get_langfile_path();

echo "<html><head><title>".$lang_docleanup['title']."</title></head><body>";
echo "<p>";
echo $lang_docleanup['running'] . "<br />";
ob_flush();
flush();
if (isset($_GET['forceall']) && $_GET['forceall']) {
	$forceall = 1;
} else {
	$forceall = 0;
    echo $lang_docleanup['force'] . '<br />';
}
echo "</p>";
$tstart = getmicrotime();
require_once("include/cleanup.php");
print("<p>".docleanup($forceall, 1)."</p>");
$tend = getmicrotime();
$totaltime = ($tend - $tstart);
printf ($lang_docleanup['time_consumed']."<br />", $totaltime);
echo $lang_docleanup['done']."<br />";
echo "</body></html>";

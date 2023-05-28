<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
$body = $_POST['body'];
print ("<table width=100% border=1 cellspacing=0 cellpadding=10 align=left>\n");
print ("<tr><td align=left>".format_comment($body)."<br /><br /></td></tr></table>");
?>

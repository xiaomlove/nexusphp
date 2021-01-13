<?php
require_once("../include/bittorrent.php");
dbconn();
logoutcookie();
//logoutsession();
//header("Refresh: 0; url=./");
Header("Location: " . get_protocol_prefix() . "$BASEURL/");
?>
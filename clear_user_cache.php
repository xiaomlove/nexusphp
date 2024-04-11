<?php
require_once("./include/bittorrent.php");
dbconn();
$res=sql_query("SELECT id,passkey FROM users");
while($arr = mysql_fetch_assoc($res)){
    clear_user_cache($arr['id'], $arr['passkey']);
}
die("Done.");
?>
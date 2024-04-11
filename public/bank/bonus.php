<?php
require "../../include/bittorrent.php";
dbconn(true);
loggedinorreturn(true);
$userid = $CURUSER["id"];
$sql='SELECT seedbonus FROM users WHERE id='.sqlesc($userid);
$res=sql_query($sql);
if (mysql_num_rows($res)>0){
    while($arr=mysql_fetch_array($res)){
        $bonus=$arr['seedbonus'];
    }
}else{
    die('Error.');
}

    die($bonus); 
?>
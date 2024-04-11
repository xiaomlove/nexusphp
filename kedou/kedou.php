<?php
require "../../include/bittorrent.php";
dbconn(true);
loggedinorreturn(true);
$userid = $CURUSER["id"];
$sql='SELECT seedbonus2 FROM users WHERE id='.sqlesc($userid);
$res=sql_query($sql);
if (mysql_num_rows($res)>0){
    while($arr=mysql_fetch_array($res)){
        $bonus=$arr['seedbonus2'];
    }
}else{
    die('Error.');
}
    $bonus2=$bonus/10000;
    sql_query("UPDATE users SET seedbonus=".sqlesc($bonus2)." WHERE id=".sqlesc($userid));
    sql_query("UPDATE users SET seedbonus2=0 WHERE id=".sqlesc($userid));
    die("成功兑换".$bonus2."蝌蚪。"); 
?>
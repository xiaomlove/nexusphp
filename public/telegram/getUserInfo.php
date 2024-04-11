<?php
require "../../include/bittorrent.php";
dbconn(true);

if(!isset($_GET['telegram_id'])){
    die("参数错误。");
}

$telegram_id=$_GET['telegram_id'];

$sql="SELECT userid FROM telegram WHERE telegram_id=".sqlesc($telegram_id);
$res=sql_query($sql);
if(mysql_num_rows($res) == 0){
    die("telegram_id错误。");
}

$userid=mysql_fetch_assoc($res);
$userid=$userid['userid'];
$ret['UID']=$userid;
$sql="SELECT * FROM users WHERE id=".sqlesc($userid);
$res=sql_query($sql);

if(mysql_num_rows($res) != 1){
    die("发生错误，请检查帐号状态。");
}

$arr=mysql_fetch_assoc($res);
$ret['username']=$arr['username'];
$ret['email']=$arr['email'];
$ret['bonus']=$arr['seedbonus'];
$ret['uploaded']=$arr['uploaded'];
$ret['downloaded']=$arr['downloaded'];
$ret['added']=$arr['added'];
$ret['class']=$arr['class'];
$ret['donor']=$arr['donor'];
$ret['ip']=$arr['ip'];
die(json_encode($ret))
?>
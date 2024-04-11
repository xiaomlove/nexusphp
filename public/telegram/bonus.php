<?php
require "../../include/bittorrent.php";
dbconn(true);

if(!isset($_GET['telegram_id']) || !isset($_GET['to']) || !isset($_GET['bonus'])){
    die("参数错误。");
}

$telegram_id=$_GET['telegram_id'];
$to=(int)$_GET['to'];
$bonus=(int)$_GET['bonus'];

$sql="SELECT userid FROM telegram WHERE telegram_id=".sqlesc($telegram_id);
$res=sql_query($sql);
if(mysql_num_rows($res) == 0){
    die("telegram_id错误。");
}

$userid=mysql_fetch_assoc($res);
$userid=$userid['userid'];
$ret['UID']=$userid;
$sql="SELECT seedbonus FROM users WHERE id=".sqlesc($userid);
$res=sql_query($sql);

if(mysql_num_rows($res) != 1){
    die("发生错误，请检查帐号状态。");
}


$arr=mysql_fetch_assoc($res);
$mybonus=$arr['seedbonus'];

$sql="SELECT * FROM users WHERE id=".sqlesc($to);
if(mysql_num_rows($res) == 0){
    die("转账对象不存在。");
}

$sql="UPDATE users SET seedbonus=seedbonus-".$bonus." WHERE id=".sqlesc($userid);
sql_query($sql);
$sql="UPDATE users SET seedbonus=seedbonus+".$bonus." WHERE id=".sqlesc($to);
sql_query($sql);
die("转账成功。");
?>
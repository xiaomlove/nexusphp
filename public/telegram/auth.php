<?php
require "../../include/bittorrent.php";
dbconn(true);

if(!isset($_GET['passkey']) || !isset($_GET['telegram_id'])){
    die("参数错误。");
}
$passkey=$_GET['passkey'];
$telegram_id=$_GET['telegram_id'];

$sql="SELECT id FROM users WHERE passkey=".sqlesc($passkey);
$res=sql_query($sql);
if(mysql_num_rows($res) == 0){
    die("Passkey错误。");
}

$userid=mysql_fetch_assoc($res);
$userid=$userid['id'];

$sql="SELECT userid FROM telegram WHERE userid=".sqlesc($userid);
$res=sql_query($sql);

if(mysql_num_rows($res) != 0){
    die("请勿重复认证Telegram。");
}

$sql="SELECT userid FROM telegram WHERE telegram_id=".sqlesc($telegram_id);
$res=sql_query($sql);

if(mysql_num_rows($res) != 0){
    die("请勿重复认证Telegram。");
}

$sql="INSERT INTO telegram (userid, telegram_id) VALUES (".sqlesc($userid).",".sqlesc($telegram_id).")";
$res=sql_query($sql);
die('认证成功');
?>
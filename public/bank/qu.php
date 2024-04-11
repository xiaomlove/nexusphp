<?php
//die("取款功能关闭，2024.3.25将按照存款天数以日利率0.12%自动结算魔力值，期间请勿挤兑魔力银行。");
if(!isset($_GET['id'])){
    die('Error.');
}
require "../../include/bittorrent.php";
dbconn(true);
loggedinorreturn(true);
$userid = $CURUSER["id"];
$id=(int)$_GET['id'];
$sql='SELECT type,bonus,uid,ddl,begin_time FROM bank WHERE id='.sqlesc($id);
$res=sql_query($sql);
if (mysql_num_rows($res)>0){
    while($arr=mysql_fetch_array($res)){
        $bonus=$arr['bonus'];
        $userid2=$arr['uid'];
        $type=$arr['type'];
        $begin_time=strtotime($arr['begin_time']);
        $ddl=strtotime($arr['ddl']);
    }
}else{
    die('Error.');
}
if($userid!=$userid2){
    die('Error.');
}

if(time()<$ddl){
    //不到取款日期扣除一半本金
    //$bonus*=.5;
    ////////
    $interval=time()-$begin_time;
    //die($interval."");
    $bonus+=$bonus*floor($interval/86400)*0.0012;
    ////////
    $sql="UPDATE users SET seedbonus2=seedbonus2+".sqlesc($bonus)." WHERE id=".sqlesc($userid);
    sql_query($sql);
    $sql="DELETE FROM bank WHERE id=".sqlesc($id);
    sql_query($sql);
    //die("由于未到取款日期，扣除一半本金，取款".$bonus."魔力值，欢迎再次使用青蛙银行。");
    die("存款".floor($interval/86400)."天，日利率0.12%，取出".$bonus."魔力值，欢迎再次使用青蛙银行。");
}

require_once("lilv.php");

$lilv=$lilv[$type];
$days=array(30,90,180,360,0)[$type];
$bonus+=$bonus*$lilv*$days;

$sql="UPDATE users SET seedbonus2=seedbonus2+".sqlesc($bonus)." WHERE id=".sqlesc($userid);
sql_query($sql);
$sql="DELETE FROM bank WHERE id=".sqlesc($id);
sql_query($sql);
die("取款".$bonus."蝌蚪，欢迎再次使用青蛙银行。");
?>
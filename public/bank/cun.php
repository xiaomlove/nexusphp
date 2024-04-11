<?php
if(!isset($_GET['cun']) || !isset($_GET['ddl'])){
    die('Error.');
}
die('存款功能关闭。');
$ddl=$_GET['ddl'];
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
$cun = (int)$_GET['cun'];
if($cun==0){
    die('Error.');
}
if($cun>$bonus){
    die('蝌蚪不足！');
}
$time = time();
switch ($ddl){
    case "0":
        //1个月
        $ddl=$time+2626560;
        $type=0;
        break;
    case "1":
        //3个月
        $ddl=$time+7879680;
        $type=1;
        break;
    case "2":
        //6个月
        $ddl=$time+15759360;
        $type=2;
        break;
    case "3":
        //1年
        $ddl=$time+31518720;
        $type=3;
        break;
    case "4":
        $ddl=$time;
        $type=4;
        break;
    default:
        die('Error.');
}
$time=date('Y-m-d H:i:s', $time);
$ddl=date('Y-m-d H:i:s', $ddl);
$sql="INSERT INTO bank (uid,type,bonus,begin_time,ddl) VALUES ('".$userid."','".$type."','".$cun."','".$time."','".$ddl."')";
sql_query($sql);
$sql="UPDATE users SET seedbonus=seedbonus-".sqlesc($cun)." WHERE id=".sqlesc($userid);
sql_query($sql);

die("存款".$cun."蝌蚪，".$ddl."后可以取款。");
?>
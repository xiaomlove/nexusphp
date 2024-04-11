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
if(!isset($_GET['gua'])){
    //不抽，输出蝌蚪
    die($bonus);
}else{
    if($bonus<1000){
        die('蝌蚪不足！');
    }else{
        sql_query("UPDATE users SET seedbonus=seedbonus-1000 where id=".sqlesc($userid));
        $rand=random_int(0, 1000);
        if($rand<3){
            //一等奖1TB上传1099511627776
            sql_query("UPDATE users SET uploaded=uploaded+1099511627776 WHERE id=".sqlesc($userid));
            die('0');
        }elseif($rand>=3 && $rand<5){
            //二等奖100000蝌蚪
            sql_query("UPDATE users SET seedbonus=seedbonus+100000 where id=".sqlesc($userid));
            die('1');
        }elseif($rand>=5 && $rand<300){
            //三等奖10G上传10737418240
            sql_query("UPDATE users SET uploaded=uploaded+10737418240 WHERE id=".sqlesc($userid));
            die('2');
        }elseif($rand>=300 && $rand<550){
            //四等奖1邀请
            sql_query("UPDATE users SET invites=invites+1 where id=".sqlesc($userid));
            die('3');
        }elseif($rand>=550 && $rand<750){
            //五等奖1G上传1073741824
            sql_query("UPDATE users SET uploaded=uploaded+1073741824 WHERE id=".sqlesc($userid));
            die('4');
        }elseif($rand>=750 && $rand<=1000){
            //六等奖100蝌蚪
            sql_query("UPDATE users SET seedbonus=seedbonus+100 where id=".sqlesc($userid));
            die('5');
        }
    }
}
?>
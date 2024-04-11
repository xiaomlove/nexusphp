<?php

require "/www/wwwroot/qingwa.pro/include/bittorrent.php";
dbconn(true);


function hasMedal($id,$medalid){
    $sql="SELECT uid,medal_id FROM `user_medals` WHERE uid=".sqlesc($id)." and medal_id=".sqlesc($medalid);
    $res=sql_query($sql);
    return mysql_num_rows($res)>0;
}


function grantMedal($id,$medalid){
    $time=date("Y-m-d H:i:s");
    $sql="INSERT INTO `user_medals` (uid, medal_id,status,created_at,updated_at) VALUES (".sqlesc($id).",".sqlesc($medalid).",0,".sqlesc($time).",".sqlesc($time).")";
    sql_query($sql);
}


$sql="SELECT owner,COUNT(owner) FROM `torrents` WHERE seeders > 0 AND approval_status = 1 GROUP BY owner";
$res=sql_query($sql);
if (mysql_num_rows($res)>0){
    while($arr=mysql_fetch_array($res)){
        $uid=$arr['owner'];
        $torrents_amount=$arr['COUNT(owner)'];
        print("\n当前用户：[".$uid."]发种数量：[".$torrents_amount."]\n");
        if($torrents_amount>=1){
            if(!hasMedal($uid,4)){
                grantMedal($uid,4);
                print("已授予发种1徽章\n");
            }            
        }
        if($torrents_amount>=10){
            if(!hasMedal($uid,5)){
                grantMedal($uid,5);
                print("已授予发种10徽章\n");
            }          
        }
        if($torrents_amount>=100){
            if(!hasMedal($uid,6)){
                grantMedal($uid,6);
                print("已授予发种100徽章\n");
            }          
        }
    }
}
?>
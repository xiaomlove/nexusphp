<?php
require "../../include/bittorrent.php";
dbconn(true);
global $CURUSER,$BASEURL;
if (!$CURUSER) {
    die("[]");
}
$userid=$CURUSER["id"];
$res=sql_query("SELECT class FROM users where id=".sqlesc($userid));
if(mysql_num_rows($res)== 0){
    die("[]");
}
$class=mysql_fetch_assoc($res);
$class=$class['class'];
if($class<11){
    die('[]');
}
if(isset($_GET["role_id"]) && isset($_GET["bt"])){
    $role_id = (int)$_GET["role_id"];
    $bt=date('Y-m-d H:i:s', (int)$_GET["bt"]);
    $et=date('Y-m-d H:i:s', strtotime("+1 month", strtotime($bt)));; 
}else{
    die('[]');
}


//查询对应role用户
$sql="SELECT uid FROM user_roles WHERE role_id=".sqlesc($role_id);
$res=sql_query($sql);
if(mysql_num_rows($res)== 0){
    die("[]");
}
$users=[];
while($arr=mysql_fetch_assoc($res)){
    $users[]=$arr['uid'];
}


//发种增量统计
$torrents=[];
for($i=0;$i<count($users);$i++){
    $sql="SELECT * FROM torrents WHERE owner=".sqlesc($users[$i])." AND added>".sqlesc($bt);
    $res=sql_query($sql);
    $torrents[$i]["uid"]=$users[$i];
    $torrents[$i]["username"]=get_username($users[$i],false,false,false,false,false,false,"",false);
    
    $j=0;
    if(mysql_num_rows($res)== 0){
        $torrents[$i]["torrents"]=[];
    }
    while($arr=mysql_fetch_assoc($res)){
        $torrents[$i]["torrents"][$j]['id']=$arr['id'];
        $torrents[$i]["torrents"][$j]['name']=$arr['name'];
        $torrents[$i]["torrents"][$j]['approval_status']=$arr['approval_status'];
        $sql="SELECT * FROM torrent_tags WHERE torrent_id=" . sqlesc($arr['id']);
        $res2=sql_query($sql);
        while($arr2=mysql_fetch_assoc($res2)){
            $torrents[$i]["torrents"][$j]['tags'][]=$arr2['tag_id'];
        }
        $torrents[$i]["torrents"][$j++]['category']=$arr['category'];
    }
}
//审种增量
$approval=[];
for($i=0;$i<count($users);$i++){
    $sql="SELECT * FROM `torrent_operation_logs` WHERE (action_type='approval_deny' or action_type='approval_allow') AND uid=".sqlesc($users[$i]) . " GROUP BY `torrent_id`";
    $res=sql_query($sql);
    $approval[$i]["uid"]=$users[$i];
    $approval[$i]["username"]=get_username($users[$i],false,false,false,false,false,false,"",false);
    $j=0;
    if(mysql_num_rows($res)== 0){
        $approval[$i]["torrents"]=[];
    }
    while($arr=mysql_fetch_assoc($res)){
        $approval[$i]["torrents"][$j++]=$arr['torrent_id'];
    }
}



die(json_encode(array("torrents"=>$torrents,"approval"=>$approval)))
?>
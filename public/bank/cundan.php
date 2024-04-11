<?php
require "../../include/bittorrent.php";
require_once("lilv.php");
dbconn(true);
loggedinorreturn(true);
$userid = $CURUSER["id"];
$sql="SELECT * FROM bank WHERE uid=". sqlesc($userid) ." ORDER BY id";
$res=sql_query($sql);
$array=array();
if (mysql_num_rows($res)>0){
    while($arr=mysql_fetch_array($res)){
        $id=$arr['id'];
        $bonus=$arr['bonus'];
        $type=$arr['type'];
        $begin_time=$arr['begin_time'];
        $ddl=$arr['ddl'];
        $lilv2=$lilv[$type];
        $days=array(30,90,180,360,0)[$type];
        $bonus2=$bonus+$bonus*$lilv2*$days;
        $arrpush=new stdClass();
        $arrpush->id=$id;
        $arrpush->bonus=$bonus;
        $arrpush->begin_time=$begin_time;
        $arrpush->ddl=$ddl;
        $arrpush->bonus2=$bonus2;
        $array[]=$arrpush;
    }
    die(json_encode($array));
}else{
    die('[]');
}



?>
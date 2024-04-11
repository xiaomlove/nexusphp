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
$sql="SELECT id,name FROM roles";
$res=sql_query($sql);
if(mysql_num_rows($res)== 0){
    die("[]");
}
$roles=[];
$i=0;
while($arr=mysql_fetch_assoc($res)){
    $roles[$i]["id"]=$arr['id'];
    $roles[$i++]["name"]=$arr['name'];
}

die(json_encode($roles))
?>
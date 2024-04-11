<?php
require "../../include/bittorrent.php";
dbconn(true);
loggedinorreturn(true);
$userid = $CURUSER["id"];
$sql1="SELECT uid,SUM(bonus) FROM bank GROUP BY uid ORDER BY SUM(bonus) DESC LIMIT 100";
$sql2="SELECT SUM(bonus) FROM bank WHERE uid=".sqlesc($userid);
$res1=sql_query($sql1);
if (mysql_num_rows($res1)>0){
    $top100=array();
    while($arr=mysql_fetch_array($res1)){
        $top100[]=[
            'uid'=>$arr['uid'],
            'bonus'=>$arr['SUM(bonus)']
        ];
    }
}
$res2=sql_query($sql2);
if (mysql_num_rows($res2)>0){
    $mybonus=0;
    while($arr=mysql_fetch_array($res2)){
        $mybonus=$arr['SUM(bonus)'];
    }
}
?>
<h2>存款TOP100</h2>
<h4>我的存款：<?php echo $mybonus;?></h4>
<table width="100%">
    <tr style='text-align:center;font-weight:800;user-select:none;'>
        <td>排名</td>
        <td>用户名</td>
        <td>存款</td>
    <tr>
    <?php
        for($i=0; $i<count($top100); $i++){
            echo "<tr style='text-align:center;'>";
            echo "<td>";
            echo "".($i+1);
            echo "</td>";
            echo "<td>";
            echo str_replace("./","../",get_username($top100[$i]["uid"],false,true,true,true,false,false,"",true));
            echo "</td>";
            echo "<td>";
            echo "".$top100[$i]["bonus"];
            echo "</td>";
            echo "</tr>";
        }
    ?>
</table>
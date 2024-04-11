<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
if (isset($_GET['del']))
{
	if (is_valid_id($_GET['del']))
	{
		if(user_can('sbmanage'))
		{
			sql_query("DELETE FROM shoutbox WHERE id=".mysql_real_escape_string($_GET['del']));
		}
	}
}
$where=$_GET["type"] ?? '';
$refresh = ($CURUSER['sbrefresh'] ? $CURUSER['sbrefresh'] : 120)
?>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="Refresh" content="<?php echo $refresh?>; url=<?php echo get_protocol_prefix() . $BASEURL?>/shoutbox.php?type=<?php echo htmlspecialchars($where)?>">
<link rel="stylesheet" href="<?php echo get_font_css_uri()?>" type="text/css">
<link rel="stylesheet" href="<?php echo get_css_uri()."theme.css"?>" type="text/css">
<link rel="stylesheet" href="styles/curtain_imageresizer.css" type="text/css">
<link rel="stylesheet" href="styles/nexus.css" type="text/css">
<script src="js/curtain_imageresizer.js" type="text/javascript"></script><style type="text/css">body {overflow-y:scroll; overflow-x: hidden}</style>
<?php
print(get_style_addicode());
$startcountdown = "startcountdown(".$CURUSER['sbrefresh'].")";
?>
<script type="text/javascript">
//<![CDATA[
var t;
function startcountdown(time)
{
parent.document.getElementById('countdown').innerHTML=time;
time=time-1;
t=setTimeout("startcountdown("+time+")",1000);
}
function countdown(time)
{
	if (time <= 0){
	parent.document.getElementById("hbtext").disabled=false;
	parent.document.getElementById("hbsubmit").disabled=false;
	parent.document.getElementById("hbsubmit").value=parent.document.getElementById("sbword").innerHTML;
	}
	else {
	parent.document.getElementById("hbsubmit").value=time;
	time=time-1;
	setTimeout("countdown("+time+")", 1000);
	}
}
function hbquota(){
parent.document.getElementById("hbtext").disabled=true;
parent.document.getElementById("hbsubmit").disabled=true;
var time=10;
countdown(time);
//]]>
}
</script>
</head>
<body class='inframe' <?php if (isset($_GET["type"]) && $_GET["type"] != "helpbox"){?> onload="<?php echo $startcountdown?>" <?php } else {?> onload="hbquota()" <?php } ?>>
<?php
if(isset($_GET["sent"]) && $_GET["sent"]=="yes"){
if(!isset($_GET["shbox_text"]) || !$_GET['shbox_text'])
{
	$userid=intval($CURUSER["id"] ?? 0);
}
else
{
	if($_GET["type"]=="helpbox")
	{
		if ($showhelpbox_main != 'yes'){
			write_log("Someone is hacking shoutbox. - IP : ".getip(),'mod');
			die($lang_shoutbox['text_helpbox_disabled']);
		}
		$userid=0;
		$type='hb';
	}
	elseif ($_GET["type"] == 'shoutbox')
	{
		$userid=intval($CURUSER["id"] ?? 0);
		if (!$userid){
			write_log("Someone is hacking shoutbox. - IP : ".getip(),'mod');
			die($lang_shoutbox['text_no_permission_to_shoutbox']);
		}
		if (!empty($_GET["toguest"])){
			$type ='hb';
		}
		else {
			$type = 'sb';
		};
	}
	$date=sqlesc(time());
	$text=trim($_GET["shbox_text"]);

	sql_query("INSERT INTO shoutbox (userid, date, text, type) VALUES (" . sqlesc($userid) . ", $date, " . sqlesc($text) . ", ".sqlesc($type).")") or sqlerr(__FILE__, __LINE__);
	print "<script type=\"text/javascript\">parent.document.forms['shbox'].shbox_text.value='';</script>";
	///////////////////////////////
	$wazong = sql_query("SELECT * FROM wazong where uid=".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
	if(mysql_num_rows($wazong) == 0){
		sql_query("INSERT INTO wazong (uid,upload,download,bonus) VALUES(".sqlesc($userid).",0,0,0)");
		$wazong = sql_query("SELECT * FROM wazong where uid=".sqlesc($userid));
	}
	$day=date('d',time());
	while($arr = mysql_fetch_assoc($wazong)){
		$upload = $day!=date('d',$arr['upload'])?true:false;
		$download = $day!=date('d',$arr['download'])?true:false;
		$bonus = $day!=date('d',$arr['bonus'])?true:false;
	}
	$wazong = sql_query("SELECT class,donor FROM users WHERE id=".sqlesc($userid));
	while($arr = mysql_fetch_assoc($wazong)){
		$vip = $arr['class']<10?true:false;
		$donor=$arr['donor']=='no'?true:false;
	}
	$wazong = sql_query("SELECT meta_key,deadline FROM user_metas WHERE uid=".sqlesc($userid));
	if(mysql_num_rows($wazong) == 0){
		$caihongID=true;
	}else{
		$caihongID=true;
		while($arr = mysql_fetch_assoc($wazong)){
			if($arr['meta_key']=="PERSONALIZED_USERNAME"){
				if($arr['deadline']==NULL){
					$caihongID=false;
				}else{
					sql_query("DELETE FROM user_metas WHERE uid=".sqlesc($userid));
				}				
			}
		}
	}
	$date0=time();
	if(strpos($text,"蛙总")!==false){
		if(strpos($text,"求上传") && $upload){
			sql_query("UPDATE users SET uploaded=uploaded+10737418240 where id=".sqlesc($userid));
			sql_query("UPDATE wazong SET upload=$date0 where uid=".$userid);
			//$text='蛙总响应了你的请求，赐给你10GB上传';
			$text='发了！';
		}elseif(strpos($text,"求下载") && $download){
			sql_query("UPDATE users SET downloaded=downloaded+10737418240 where id=".sqlesc($userid));
			sql_query("UPDATE wazong SET download=$date0 where uid=".$userid);
			//$text='蛙总响应了你的请求，赐给你10GB下载';
			$text='发了！';
		}/*elseif(strpos($text,"求蝌蚪") && $bonus){
			sql_query("UPDATE users SET seedbonus=seedbonus+10000 where id=".sqlesc($userid));
			sql_query("UPDATE wazong SET bonus=$date0 where uid=".$userid);
			//$text='蛙总响应了你的请求，赐给你10000蝌蚪';
			$text='[em201]';
		}elseif(strpos($text,"求VIP") && $vip){
			sql_query("UPDATE users SET class=10 where id=".sqlesc($userid));
			$text='[em201]';
			//$text='蛙总响应了你的请求，赐给你永久VIP';
		}elseif(strpos($text,"求黄星") && $donor){
			$userInfo = \App\Models\User::query()->findOrFail($userid);
			sql_query("UPDATE users SET donor = 'yes' where id=".sqlesc($userid));
			clear_user_cache($userid, $userInfo->passkey);
			$text='[em201]';
			//$text='蛙总响应了你的请求，赐给你永久黄星';
		}elseif(strpos($text,"求彩虹ID") && $caihongID){
			$userInfo = \App\Models\User::query()->findOrFail($userid);
			$sql="INSERT INTO user_metas (uid, meta_key, status) VALUES (".sqlesc($userid).", 'PERSONALIZED_USERNAME', 0)";
			sql_query($sql);
			clear_user_cache($userid, $userInfo->passkey);
			//$text='蛙总响应了你的请求，赐给你永久彩虹ID';
			$text='[em201]';
		}*/else{
			$text='不要调戏蛙总！（怒）';
		}
		$date=sqlesc(time()+1);
		sql_query("INSERT INTO shoutbox (userid, date, text, type) VALUES (1, $date, " . sqlesc($text) . ", ".sqlesc($type).")") or sqlerr(__FILE__, __LINE__);
		print "<script type=\"text/javascript\">parent.document.forms['shbox'].shbox_text.value='';</script>";
	}
	///////////////////////////////
}
}

$limit = ($CURUSER['sbnum'] ? $CURUSER['sbnum'] : 70);
if ($where == "helpbox")
{
$sql = "SELECT * FROM shoutbox WHERE type='hb' ORDER BY date DESC LIMIT ".$limit;
}
elseif ($CURUSER['hidehb'] == 'yes' || $showhelpbox_main != 'yes'){
$sql = "SELECT * FROM shoutbox WHERE type='sb' ORDER BY date DESC LIMIT ".$limit;
}
elseif ($CURUSER){
$sql = "SELECT * FROM shoutbox ORDER BY date DESC LIMIT ".$limit;
}
else {
die("<h1>".$lang_shoutbox['std_access_denied']."</h1>"."<p>".$lang_shoutbox['std_access_denied_note']."</p></body></html>");
}
$res = sql_query($sql) or sqlerr(__FILE__, __LINE__);
if (mysql_num_rows($res) == 0)
print("\n");
else
{
	print("<table border='0' cellspacing='0' cellpadding='2' width='100%' align='left'>\n");

	while ($arr = mysql_fetch_assoc($res))
	{
        $del = '';
		if (user_can('sbmanage')) {
			$del .= "[<a href=\"shoutbox.php?del=".$arr['id']."\">".$lang_shoutbox['text_del']."</a>]";
		}
		$avatar='pic/default_avatar.png';
		if ($arr["userid"]) {
			$username = get_username($arr["userid"],false,true,true,true,false,false,"",true);
			$avatar2=sql_query("SELECT avatar FROM users WHERE id=".sqlesc($arr["userid"]));
			if (mysql_num_rows($avatar2) == 0){
				$avatar='pic/default_avatar.png';
			}else{
				while ($avatar3 = mysql_fetch_assoc($avatar2)){
					$avatar=$avatar3['avatar']==""?$avatar:$avatar3['avatar'];
				}
			}
			if (isset($arr["type"]) && isset($_GET['type']) && $_GET["type"] != 'helpbox' && $arr["type"] == 'hb')
				$username .= $lang_shoutbox['text_to_guest'];
			}
		else $username = $lang_shoutbox['text_guest'];

		if (isset($CURUSER) && $CURUSER['timetype'] != 'timealive')
			$time = strftime("%m.%d %H:%M",$arr["date"]);
		else $time = get_elapsed_time($arr["date"]).$lang_shoutbox['text_ago'];
        
        if($CURUSER['id']==$arr["userid"]){
            $printhtml="<ul style='text-align:right;padding:4px 0;display:block;border-spacing: 0;'><li style='margin:0;vertical-align: bottom;color:#444;margin-left:-12px;max-width:calc(100% - 100px);border-radius:12px;background-color:#FFF;display:inline-block;padding:10px;line-height:20px;font-size:14px;'>";
			$printhtml="<ul style='text-align:right;padding:4px 0;display:block;border-spacing: 0;'><li style='margin:0;vertical-align: bottom;color:#444;margin-left:-12px;max-width:calc(100% - 100px);border-radius:12px;background-color:#FFF;display:inline-block;padding:10px;line-height:20px;font-size:14px;'>";
		    $printhtml.=format_comment($arr["text"],true,false,true,true,600,$arr["userid"]==1?true:false,false);
		    $printhtml.="<p style='font-size:10px;margin-bottom:0;'>".$username."&nbsp;&nbsp;发布于：".$time.$del."</p></li><li style='margin:0;vertical-align: bottom;position:relative;top:-8px;left:-4px;border-radius:0;width:0;height:0;border-left: 10px solid #FFF;border-right: 10px solid transparent;border-top: 10px solid transparent;border-bottom: 10px solid transparent;display:inline-block;'></li></ul>";
        }else{
		    $printhtml="<ul style='padding:4px 0;display:block;border-spacing: 0;'><li style='margin:0;vertical-align: bottom;position:relative;top:-8px;left:-6px;border-radius:0;width:0;height:0;border-left: 10px solid transparent;border-right: 10px solid #a6e860;border-top: 10px solid transparent;border-bottom: 10px solid transparent;display:inline-block;'></li><li style='margin:0;vertical-align: bottom;color:#444;margin-left:-12px;max-width:calc(100% - 100px);border-radius:12px;background-color:#a6e860;display:inline-block;padding:10px;line-height:20px;font-size:14px;'>";
		    $printhtml.=format_comment($arr["text"],true,false,true,true,600,$arr["userid"]==1?true:false,false);
		    $printhtml.="<p style='font-size:10px;margin-bottom:0;'>".$username."&nbsp;&nbsp;发布于：".$time.$del."</p></li></ul>";
        }
		/*print("<tr><td class=\"shoutrow\"><span class='date'>[".$time."]</span> ".
$del ." ". $username." " . format_comment($arr["text"],true,false,true,true,600,$arr["userid"]==1?true:false,false)."
</td></tr>\n");*/
		print($printhtml);
	}
	print("</table>");
}
?>
</body>
</html>


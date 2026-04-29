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
$refresh = ($CURUSER['sbrefresh'] ?? 120)
?>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="Refresh" content="<?php echo $refresh?>; url=<?php echo get_protocol_prefix() . $BASEURL?>/shoutbox.php?type=<?php echo htmlspecialchars($where)?>">
<link rel="stylesheet" href="<?php echo get_font_css_uri()?>" type="text/css">
<link rel="stylesheet" href="<?php echo get_css_uri()."theme.css"?>" type="text/css">
<link rel="stylesheet" href="styles/curtain_imageresizer.css" type="text/css">
<link rel="stylesheet" href="styles/nexus.css" type="text/css">
<script src="js/curtain_imageresizer.js" type="text/javascript"></script><style type="text/css">
body {overflow-y:scroll; overflow-x: hidden}
td.shoutrow .shout-avatar {
	width: 22px;
	height: 22px;
	border-radius: 50%;
	object-fit: cover;
	vertical-align: middle;
	margin-right: 4px;
	background: rgba(127,127,127,.15);
}
td.shoutrow .shout-mention {
	background: rgba(64,128,255,.12);
	border-radius: 3px;
	padding: 0 3px;
	text-decoration: none;
	font-weight: bold;
}
</style>
<?php
print(get_style_addicode());
$startcountdown = "startcountdown(".$refresh.")";
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
            do_log("Someone is hacking shoutbox. helpbox_disabled - IP : ".getip());
			die($lang_shoutbox['text_helpbox_disabled']);
		}
		$userid=0;
		$type='hb';
	}
	elseif ($_GET["type"] == 'shoutbox')
	{
		$userid=intval($CURUSER["id"] ?? 0);
		if (!$userid){
            do_log("Someone is hacking shoutbox. no_permission_to_shoutbox - IP : ".getip());
			die($lang_shoutbox['text_no_permission_to_shoutbox']);
		}
		if (!empty($_GET["toguest"]))
			$type ='hb';
		else $type = 'sb';
	}
	$date=sqlesc(time());
	$text=trim($_GET["shbox_text"]);
    if (isset($userid) && $userid > 0) {
        $lock = new \Nexus\Database\NexusLock("shoutbox:$userid", 60);
    } else {
        $lock = new \Nexus\Database\NexusLock("shoutbox:" . getip(), 60);
    }
    if (!$lock->acquire()) {
        die($lang_shoutbox['speaking_too_often']);
    }
	sql_query("INSERT INTO shoutbox (userid, date, text, type) VALUES (" . sqlesc($userid) . ", $date, " . sqlesc($text) . ", ".sqlesc($type).")") or sqlerr(__FILE__, __LINE__);
	print "<script type=\"text/javascript\">parent.document.forms['shbox'].shbox_text.value='';</script>";
}
}

$limit = ($CURUSER['sbnum'] ?? 70);
if ($where == "helpbox" && $showhelpbox_main == 'yes') {
    //request helpbox, not require login
    $sql = "SELECT * FROM shoutbox WHERE type='hb' ORDER BY date DESC LIMIT ".$limit;
} elseif ($where == "shoutbox" && isset($CURUSER) && ($CURUSER['hidehb'] == 'yes' || $showhelpbox_main != 'yes')) {
    //request shoutbox, exclude helpbox content, require login
    $sql = "SELECT * FROM shoutbox WHERE type='sb' ORDER BY date DESC LIMIT ".$limit;
} elseif (isset($CURUSER)) {
    $sql = "SELECT * FROM shoutbox ORDER BY date DESC LIMIT ".$limit;
} else {
    die("<h1>".$lang_shoutbox['std_access_denied']."</h1>"."<p>".$lang_shoutbox['std_access_denied_note']."</p></body></html>");
}
/**
 * Replace plain @username tokens with links to userdetails.
 * Runs over already-rendered HTML (output of format_comment). Negative-lookbehind
 * gives identifier-style word boundaries, and the match is dropped if the username
 * doesn't resolve to a real user, so false positives (emails, URL fragments) are
 * left untouched.
 */
function shoutbox_render_mentions($html)
{
	static $cache = [];
	if ($html === '' || strpos($html, '@') === false) {
		return $html;
	}
	return preg_replace_callback(
		'/(?<![A-Za-z0-9_\-])@([A-Za-z0-9_\-]{2,40})(?![A-Za-z0-9_\-])/u',
		function ($m) use (&$cache) {
			$nick = $m[1];
			$key = strtolower($nick);
			if (!array_key_exists($key, $cache)) {
				$res = sql_query("SELECT id, username FROM users WHERE LOWER(username) = LOWER(" . sqlesc($nick) . ") LIMIT 1");
				$row = $res ? mysql_fetch_assoc($res) : false;
				$cache[$key] = $row ? ['id' => (int)$row['id'], 'name' => $row['username']] : false;
			}
			if (!$cache[$key]) {
				return $m[0];
			}
			return '<a class="shout-mention" href="userdetails.php?id=' . $cache[$key]['id'] . '">@' . htmlspecialchars($cache[$key]['name']) . '</a>';
		},
		$html
	);
}

$res = sql_query($sql) or sqlerr(__FILE__, __LINE__);
if (mysql_num_rows($res) == 0)
print("\n");
else
{
	$showAvatars = isset($CURUSER['avatars']) && $CURUSER['avatars'] === 'yes';
	print("<table border='0' cellspacing='0' cellpadding='2' width='100%' align='left'>\n");

	while ($arr = mysql_fetch_assoc($res))
	{
        $del = '';
		if (user_can('sbmanage')) {
			$del .= "[<a href=\"shoutbox.php?del=".$arr['id']."\">".$lang_shoutbox['text_del']."</a>]";
		}
		$avatarUrl = 'pic/default_avatar.png';
		if ($arr["userid"]) {
			$username = get_username($arr["userid"],false,true,true,true,false,false,"",true);
			if (isset($arr["type"]) && isset($_GET['type']) && $_GET["type"] != 'helpbox' && $arr["type"] == 'hb')
				$username .= $lang_shoutbox['text_to_guest'];
			if ($showAvatars) {
				$userRow = get_user_row((int)$arr["userid"]);
				$rawAvatar = trim((string)($userRow["avatar"] ?? ''));
				if ($rawAvatar !== '') {
					$avatarUrl = $rawAvatar;
				}
			}
		}
		else $username = $lang_shoutbox['text_guest'];
		$avatarHtml = '<img class="shout-avatar" src="' . htmlspecialchars($avatarUrl) . '" alt="" onerror="this.onerror=null;this.src=\'pic/default_avatar.png\';" />';
		if (isset($CURUSER) && $CURUSER['timetype'] != 'timealive')
			$time = (new DateTime())->setTimestamp($arr["date"])->format('m.d H:i');
		else $time = get_elapsed_time($arr["date"]).$lang_shoutbox['text_ago'];
		$message = format_comment($arr["text"],true,false,true,true,600,false,false);
		$message = shoutbox_render_mentions($message);
		print("<tr><td class=\"shoutrow\"><span class='date'>[".$time."]</span> ".
$del ." ". $avatarHtml . " " . $username." " . $message."
</td></tr>\n");
	}
	print("</table>");
}
?>
</body>
</html>

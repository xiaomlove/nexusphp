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
$refresh = ($CURUSER['sbrefresh'] ?? 120);

// Resolve channel for live updates. Defaults to 'sb' (shoutbox); 'hb' for helpbox.
$shoutChannel = ($where === 'helpbox') ? 'shoutbox.hb' : 'shoutbox.sb';

// Pull frontend Reverb settings so echo.js can connect. Empty values
// are fine — the bootstrap silently no-ops without a configured key.
$reverbConfig = [
    'key' => (string) (env('REVERB_APP_KEY') ?: ''),
    'host' => (string) (env('REVERB_HOST') ?: $_SERVER['HTTP_HOST'] ?? ''),
    'port' => (int) (env('REVERB_PORT') ?: 8080),
    'scheme' => (string) (env('REVERB_SCHEME') ?: 'http'),
];

// Locate the Vite-built echo.js bundle. Falls back to '' if Vite hasn't run.
$reverbBundleUrl = '';
$manifestPath = __DIR__ . '/build/manifest.json';
if (is_file($manifestPath)) {
    $manifest = json_decode(file_get_contents($manifestPath), true);
    if (isset($manifest['resources/js/echo.js']['file'])) {
        $reverbBundleUrl = '/build/' . $manifest['resources/js/echo.js']['file'];
    }
}
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
td.shoutrow .shout-avatar-link {
	display: inline-block;
	line-height: 0;
	vertical-align: middle;
}
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
td.shoutrow .shout-nick-reply {
	cursor: pointer;
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
function shoutReply(nick) {
	try {
		var input = null;
		if (parent && parent.document) {
			if (parent.document.forms && parent.document.forms['shbox'] && parent.document.forms['shbox'].shbox_text) {
				input = parent.document.forms['shbox'].shbox_text;
			}
			if (!input) {
				input = parent.document.getElementById('hbtext');
			}
		}
		if (!input) { return false; }
		var prefix = '@' + nick + ', ';
		var val = input.value || '';
		if (val.indexOf(prefix) !== 0) {
			input.value = prefix + val;
		}
		input.focus();
		try { input.setSelectionRange(input.value.length, input.value.length); } catch (e) {}
	} catch (e) {}
	return false;
}
</script>
</head>
<body class='inframe' data-shout-channel="<?php echo htmlspecialchars($shoutChannel)?>" <?php if (isset($_GET["type"]) && $_GET["type"] != "helpbox"){?> onload="<?php echo $startcountdown?>" <?php } else {?> onload="hbquota()" <?php } ?>>
<?php if ($reverbBundleUrl !== '' && $reverbConfig['key'] !== ''): ?>
<script>window.__REVERB__ = <?php echo json_encode($reverbConfig); ?>;</script>
<script type="module" src="<?php echo htmlspecialchars($reverbBundleUrl); ?>"></script>
<?php endif; ?>
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
	// Broadcast the new shout over Reverb so live listeners refresh
	// without waiting for the meta-refresh poll. Wrapped to ensure a
	// broadcasting failure never breaks the legacy insert flow.
	try {
		$shoutId = (int) mysql_insert_id();
		event(new \App\Events\ShoutSent(
			$shoutId,
			(int) $userid,
			(int) (is_string($date) ? trim($date, "'") : $date),
			(string) $text,
			(string) $type,
		));
	} catch (\Throwable $e) {
		do_log("ShoutSent broadcast failed: " . $e->getMessage(), 'error');
	}
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
		$nickReplyName = '';
		if ($arr["userid"]) {
			$username = get_username($arr["userid"],false,true,true,true,false,false,"",true);
			if (isset($arr["type"]) && isset($_GET['type']) && $_GET["type"] != 'helpbox' && $arr["type"] == 'hb')
				$username .= $lang_shoutbox['text_to_guest'];
			$userRow = get_user_row((int)$arr["userid"]);
			$nickReplyName = trim((string)($userRow["username"] ?? ''));
			if ($showAvatars) {
				$rawAvatar = trim((string)($userRow["avatar"] ?? ''));
				if ($rawAvatar !== '') {
					$avatarUrl = $rawAvatar;
				}
			}
			// Repurpose the nickname link: instead of going to userdetails, clicking the nick
			// inserts "@nick, " into the input box. Avatar takes over the profile-link role below.
			if ($nickReplyName !== '' && (int)($CURUSER['id'] ?? 0) > 0) {
				$onclickAttr = 'return shoutReply(' . htmlspecialchars(json_encode($nickReplyName, JSON_UNESCAPED_UNICODE), ENT_QUOTES) . ')';
				$username = preg_replace(
					'#href="userdetails\.php\?id=\d+"#',
					'href="javascript:void(0)" onclick="' . $onclickAttr . '"',
					$username,
					1
				);
				// Tag the rewritten link so we can give it a pointer cursor without affecting other anchors.
				$username = preg_replace(
					'#<a\s([^>]*onclick="return shoutReply\()#',
					'<a class="shout-nick-reply" $1',
					$username,
					1
				);
			}
		}
		else $username = $lang_shoutbox['text_guest'];
		$avatarImg = '<img class="shout-avatar" src="' . htmlspecialchars($avatarUrl) . '" alt="" onerror="this.onerror=null;this.src=\'pic/default_avatar.png\';" />';
		if ((int)$arr["userid"] > 0) {
			$avatarHtml = '<a class="shout-avatar-link" href="userdetails.php?id=' . (int)$arr["userid"] . '" target="_blank">' . $avatarImg . '</a>';
		} else {
			$avatarHtml = $avatarImg;
		}
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

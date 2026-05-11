<?php

use App\Events\ShoutSent;
use Nexus\Database\NexusDB;
use Nexus\Database\NexusLock;

require_once '../include/bittorrent.php';
dbconn();
require_once get_langfile_path();
if (isset($_GET['del'])) {
    if (is_valid_id($_GET['del'])) {
        if (user_can('sbmanage')) {
            NexusDB::table('shoutbox')->where('id', (int) $_GET['del'])->delete();
        }
    }
}
$where = $_GET['type'] ?? '';
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
$manifestPath = __DIR__.'/build/manifest.json';
if (is_file($manifestPath)) {
    $manifest = json_decode(file_get_contents($manifestPath), true);
    if (isset($manifest['resources/js/echo.js']['file'])) {
        $reverbBundleUrl = '/build/'.$manifest['resources/js/echo.js']['file'];
    }
}
?>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta http-equiv="Refresh" content="<?php echo $refresh?>; url=<?php echo get_protocol_prefix().$BASEURL?>/shoutbox.php?type=<?php echo htmlspecialchars($where)?>">
<link rel="stylesheet" href="<?php echo get_font_css_uri()?>" type="text/css">
<link rel="stylesheet" href="<?php echo get_css_uri().'theme.css'?>" type="text/css">
<link rel="stylesheet" href="styles/curtain_imageresizer.css" type="text/css">
<link rel="stylesheet" href="styles/nexus.css" type="text/css">
<script src="js/curtain_imageresizer.js" type="text/javascript"></script><style type="text/css">
body {overflow-y:scroll; overflow-x: hidden}
td.shoutrow .shout-avatar-link {
	display: inline-block;
	line-height: 0;
	vertical-align: middle;
}
td.shoutrow .shout-avatar,
td.shoutrow .shout-avatar-spacer {
	width: 22px;
	height: 22px;
	display: inline-block;
	vertical-align: middle;
	margin-right: 4px;
}
td.shoutrow .shout-avatar {
	border-radius: 50%;
	object-fit: cover;
	background: rgba(127,127,127,.15);
}
td.shoutrow.shout-row-grouped {
	opacity: .92;
}
td.shoutrow.shout-row-grouped .shout-avatar-spacer {
	background: transparent;
}
td.shoutrow .shout-mention {
	background: rgba(64,128,255,.12);
	border-radius: 3px;
	padding: 0 3px;
	text-decoration: none;
	font-weight: bold;
}
td.shoutrow .shout-mention.shout-mention-me {
	background: rgba(255,196,0,.30);
	color: inherit;
	box-shadow: 0 0 0 1px rgba(255,196,0,.55) inset;
}
td.shoutrow.shoutrow-mentions-me {
	background: rgba(255,196,0,.08);
	border-left: 3px solid rgba(255,196,0,.65);
	padding-left: 5px;
}
td.shoutrow .shout-nick-reply {
	cursor: pointer;
}
td.shoutrow .shout-nick-reply:hover {
	text-decoration: underline;
}
td.shoutrow .shout-torrent {
	background: rgba(0,168,107,.14);
	border-radius: 3px;
	padding: 0 3px;
	text-decoration: none;
	font-weight: bold;
}
td.shoutrow .shout-msg.shout-msg-clamped {
	display: inline-block;
	max-width: 100%;
	max-height: 3.6em;
	overflow: hidden;
	vertical-align: top;
}
td.shoutrow .shout-msg-toggle {
	font-size: 11px;
	margin-left: 4px;
	opacity: .65;
	cursor: pointer;
	white-space: nowrap;
}
td.shoutrow .shout-msg-toggle:hover {
	opacity: 1;
}
td.shoutrow .shout-class-badge {
	display: inline-block;
	font-size: 9px;
	line-height: 12px;
	font-weight: bold;
	letter-spacing: 0.5px;
	padding: 1px 4px;
	border-radius: 3px;
	margin-right: 3px;
	text-transform: uppercase;
	vertical-align: middle;
	color: #fff !important;
}
</style>
<?php
echo get_style_addicode();
$startcountdown = 'startcountdown('.$refresh.')';
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
<body class='inframe' data-shout-channel="<?php echo htmlspecialchars($shoutChannel)?>" <?php if (isset($_GET['type']) && $_GET['type'] != 'helpbox') {?> onload="<?php echo $startcountdown?>" <?php } else {?> onload="hbquota()" <?php } ?>>
<?php if ($reverbBundleUrl !== '' && $reverbConfig['key'] !== '') { ?>
<script>window.__REVERB__ = <?php echo json_encode($reverbConfig); ?>;</script>
<script type="module" src="<?php echo htmlspecialchars($reverbBundleUrl); ?>"></script>
<?php } ?>
<?php
if (isset($_GET['sent']) && $_GET['sent'] == 'yes') {
    if (! isset($_GET['shbox_text']) || ! $_GET['shbox_text']) {
        $userid = intval($CURUSER['id'] ?? 0);
    } else {
        if ($_GET['type'] == 'helpbox') {
            if ($showhelpbox_main != 'yes') {
                do_log('Someone is hacking shoutbox. helpbox_disabled - IP : '.getip());
                exit($lang_shoutbox['text_helpbox_disabled']);
            }
            $userid = 0;
            $type = 'hb';
        } elseif ($_GET['type'] == 'shoutbox') {
            $userid = intval($CURUSER['id'] ?? 0);
            if (! $userid) {
                do_log('Someone is hacking shoutbox. no_permission_to_shoutbox - IP : '.getip());
                exit($lang_shoutbox['text_no_permission_to_shoutbox']);
            }
            if (! empty($_GET['toguest'])) {
                $type = 'hb';
            } else {
                $type = 'sb';
            }
        }
        $dateInt = time();
        $text = trim($_GET['shbox_text']);
        if (isset($userid) && $userid > 0) {
            $lock = new NexusLock("shoutbox:$userid", 60);
        } else {
            $lock = new NexusLock('shoutbox:'.getip(), 60);
        }
        if (! $lock->acquire()) {
            exit($lang_shoutbox['speaking_too_often']);
        }
        $shoutId = (int) NexusDB::insert('shoutbox', [
            'userid' => (int) $userid,
            'date' => $dateInt,
            'text' => (string) $text,
            'type' => (string) $type,
        ]);
        // Broadcast the new shout over Reverb so live listeners receive
        // the row instantly without waiting for the meta-refresh poll.
        // We render the same <tr> the listing below would render and
        // ship it as part of the payload — subscribers just prepend it.
        // Wrapped so a broadcasting failure never breaks the insert.
        try {
            $broadcastRow = [
                'id' => $shoutId,
                'userid' => (int) $userid,
                'date' => $dateInt,
                'text' => (string) $text,
                'type' => (string) $type,
            ];
            $broadcastShowAvatars = isset($CURUSER['avatars']) && $CURUSER['avatars'] === 'yes';
            $broadcastWhere = ($type === 'hb') ? 'helpbox' : 'shoutbox';
            $broadcastHtml = shoutbox_render_row($broadcastRow, $broadcastWhere, $lang_shoutbox, $CURUSER, $broadcastShowAvatars);
            event(new ShoutSent(
                $shoutId,
                (int) $userid,
                $dateInt,
                (string) $text,
                (string) $type,
                $broadcastHtml,
            ));
        } catch (Throwable $e) {
            do_log('ShoutSent broadcast failed: '.$e->getMessage(), 'error');
        }
        echo "<script type=\"text/javascript\">parent.document.forms['shbox'].shbox_text.value='';</script>";
    }
}

$limit = (int) ($CURUSER['sbnum'] ?? 70);
if ($where == 'helpbox' && $showhelpbox_main == 'yes') {
    // request helpbox, not require login
    $sql = "SELECT * FROM shoutbox WHERE type='hb' ORDER BY date DESC LIMIT ".$limit;
} elseif ($where == 'shoutbox' && isset($CURUSER) && ($CURUSER['hidehb'] == 'yes' || $showhelpbox_main != 'yes')) {
    // request shoutbox, exclude helpbox content, require login
    $sql = "SELECT * FROM shoutbox WHERE type='sb' ORDER BY date DESC LIMIT ".$limit;
} elseif (isset($CURUSER)) {
    $sql = 'SELECT * FROM shoutbox ORDER BY date DESC LIMIT '.$limit;
} else {
    exit('<h1>'.$lang_shoutbox['std_access_denied'].'</h1>'.'<p>'.$lang_shoutbox['std_access_denied_note'].'</p></body></html>');
}

/**
 * Build a small role badge for staff/VIP-tier classes. Returns empty string for
 * regular users so the shoutbox doesn't get cluttered with badges on every row.
 */
function shoutbox_class_badge($class)
{
    static $map = null;
    if ($map === null) {
        $map = [
            UC_VIP => ['VIP', '#9c27b0'],
            UC_RETIREE => ['RET', '#607d8b'],
            UC_UPLOADER => ['UPL', '#1976d2'],
            UC_MODERATOR => ['MOD', '#388e3c'],
            UC_ADMINISTRATOR => ['ADM', '#d32f2f'],
            UC_SYSOP => ['SYS', '#b71c1c'],
            UC_STAFFLEADER => ['CHIEF', '#e65100'],
        ];
    }
    $class = (int) $class;
    if (! isset($map[$class])) {
        return '';
    }
    $label = $map[$class][0];
    $color = $map[$class][1];
    $tooltip = '';
    if (function_exists('get_user_class_name')) {
        $tooltip = (string) get_user_class_name($class, false, false, true);
    }

    return '<span class="shout-class-badge" style="background:'.$color.'" title="'.htmlspecialchars($tooltip, ENT_QUOTES).'">'.$label.'</span>';
}

/**
 * Replace plain #1234 tokens with links to torrent details.
 * Runs over already-rendered HTML (output of format_comment). Tokens that don't
 * resolve to an existing torrent row are left as plain text, so we don't break
 * arbitrary `#fragment` URLs or the like.
 */
function shoutbox_render_torrents($html)
{
    static $cache = [];
    if ($html === '' || strpos($html, '#') === false) {
        return $html;
    }

    // Negative lookbehind excludes word-chars and common URL/HTML separators so we
    // don't match the `#` inside `https://x#1234`, `<a href="x#1234">`, etc.
    return preg_replace_callback(
        '/(?<![\w&"\/=])#(\d{1,9})(?!\w)/',
        function ($m) use (&$cache) {
            $id = (int) $m[1];
            if ($id <= 0) {
                return $m[0];
            }
            if (! array_key_exists($id, $cache)) {
                $row = NexusDB::table('torrents')->where('id', $id)->select(['id'])->first();
                $cache[$id] = (bool) $row;
            }
            if (! $cache[$id]) {
                return $m[0];
            }

            return '<a class="shout-torrent" href="details.php?id='.$id.'" target="_blank">#'.$id.'</a>';
        },
        $html
    );
}

/**
 * Replace plain @username tokens with links to userdetails.
 * Runs over already-rendered HTML (output of format_comment). Negative-lookbehind
 * gives identifier-style word boundaries, and the match is dropped if the username
 * doesn't resolve to a real user, so false positives (emails, URL fragments) are
 * left untouched.
 */
function shoutbox_render_mentions($html, &$mentionsMe = false)
{
    static $cache = [];
    global $CURUSER;
    $myId = (int) ($CURUSER['id'] ?? 0);
    if ($html === '' || strpos($html, '@') === false) {
        return $html;
    }

    // Username charset is intentionally permissive to cover legacy nicknames that
    // contain brackets/parens (e.g. "[LP-Bits]", "(Mod)Name"). The lookup-only-on-success
    // behaviour below means false positives stay as plain text.
    return preg_replace_callback(
        '/(?<![\w\-\[\]\(\)])@([\w\-\[\]\(\)]{2,40})(?![\w\-\[\]\(\)])/u',
        function ($m) use (&$cache, $myId, &$mentionsMe) {
            $nick = $m[1];
            if ($nick === '' || strlen($nick) < 2) {
                return $m[0];
            }
            $key = strtolower($nick);
            if (! array_key_exists($key, $cache)) {
                $row = NexusDB::table('users')
                    ->whereRaw('LOWER(username) = LOWER(?)', [$nick])
                    ->select(['id', 'username'])
                    ->first();
                $cache[$key] = $row ? ['id' => (int) $row->id, 'name' => $row->username] : false;
            }
            if (! $cache[$key]) {
                return $m[0];
            }
            $isMe = $myId > 0 && $cache[$key]['id'] === $myId;
            if ($isMe) {
                $mentionsMe = true;
            }
            $cls = $isMe ? 'shout-mention shout-mention-me' : 'shout-mention';
            // Click on @nick in a rendered message inserts "@nick, " into the shoutbox input,
            // matching the click-on-nick behaviour. Falls back to userdetails for guests
            // (no shoutbox input to type into) via href="userdetails.php?id=N".
            $nick = $cache[$key]['name'];
            if ($myId > 0) {
                $onclick = 'return shoutReply('.htmlspecialchars(json_encode($nick, JSON_UNESCAPED_UNICODE), ENT_QUOTES).')';
                $replyTitle = '';
                if (isset($GLOBALS['lang_shoutbox']['tooltip_nick_reply'])) {
                    $replyTitle = ' title="'.htmlspecialchars($GLOBALS['lang_shoutbox']['tooltip_nick_reply'], ENT_QUOTES).'"';
                }

                return '<a class="'.$cls.'" href="userdetails.php?id='.$cache[$key]['id'].'" onclick="'.$onclick.'"'.$replyTitle.'>@'.htmlspecialchars($nick).'</a>';
            }

            return '<a class="'.$cls.'" href="userdetails.php?id='.$cache[$key]['id'].'">@'.htmlspecialchars($nick).'</a>';
        },
        $html
    );
}

/**
 * Render one <tr>…</tr> for a shoutbox row. Extracted into a helper so
 * the broadcast payload (sent over Reverb to every open iframe) and the
 * initial server-rendered listing share one code path — any future
 * formatting tweak only has to land here.
 */
function shoutbox_render_row(array $arr, $where, array $lang_shoutbox, $CURUSER, $showAvatars, $isGrouped = false)
{
    $tooltipAvatar = $lang_shoutbox['tooltip_avatar'] ?? 'Open profile';
    $tooltipReply = $lang_shoutbox['tooltip_nick_reply'] ?? 'Reply via @';
    $labelMore = $lang_shoutbox['shout_show_more'] ?? 'more';
    $labelLess = $lang_shoutbox['shout_show_less'] ?? 'less';
    $del = '';
    if (user_can('sbmanage')) {
        $del .= '[<a href="shoutbox.php?del='.$arr['id'].'">'.$lang_shoutbox['text_del'].'</a>]';
    }
    $avatarUrl = 'pic/default_avatar.png';
    $nickReplyName = '';
    $classBadge = '';
    if ($arr['userid']) {
        $username = get_username($arr['userid'], false, true, true, true, false, false, '', true);
        if (isset($arr['type']) && $where == 'shoutbox' && $arr['type'] == 'hb') {
            $username .= $lang_shoutbox['text_to_guest'];
        }
        $userRow = get_user_row((int) $arr['userid']);
        $nickReplyName = trim((string) ($userRow['username'] ?? ''));
        $classBadge = shoutbox_class_badge((int) ($userRow['class'] ?? 0));
        if ($showAvatars) {
            $rawAvatar = trim((string) ($userRow['avatar'] ?? ''));
            if ($rawAvatar !== '') {
                $avatarUrl = $rawAvatar;
            }
        }
        // Repurpose the nickname link: instead of going to userdetails, clicking the nick
        // inserts "@nick, " into the input box. Avatar takes over the profile-link role below.
        // get_username() emits an absolute URL (scheme+host), so the regex must be tolerant
        // of any prefix between `href="` and `userdetails.php`.
        if ($nickReplyName !== '' && (int) ($CURUSER['id'] ?? 0) > 0) {
            $onclickAttr = 'return shoutReply('.htmlspecialchars(json_encode($nickReplyName, JSON_UNESCAPED_UNICODE), ENT_QUOTES).')';
            $username = preg_replace(
                '#href="[^"]*userdetails\.php\?id=\d+"#',
                'href="javascript:void(0)" onclick="'.$onclickAttr.'" title="'.htmlspecialchars($tooltipReply, ENT_QUOTES).'"',
                $username,
                1
            );
            $username = preg_replace(
                '#<a\s([^>]*onclick="return shoutReply\()#',
                '<a class="shout-nick-reply" $1',
                $username,
                1
            );
        }
    } else {
        $username = $lang_shoutbox['text_guest'];
    }
    $avatarImg = '<img class="shout-avatar" src="'.htmlspecialchars($avatarUrl).'" alt="" loading="lazy" decoding="async" onerror="this.onerror=null;this.src=\'pic/default_avatar.png\';" />';
    if ((int) $arr['userid'] > 0) {
        $avatarHtml = '<a class="shout-avatar-link" href="userdetails.php?id='.(int) $arr['userid'].'" target="_blank" title="'.htmlspecialchars($tooltipAvatar, ENT_QUOTES).'">'.$avatarImg.'</a>';
    } else {
        $avatarHtml = $avatarImg;
    }
    if (isset($CURUSER) && ($CURUSER['timetype'] ?? '') != 'timealive') {
        $time = (new DateTime)->setTimestamp((int) $arr['date'])->format('m.d H:i');
    } else {
        $time = get_elapsed_time($arr['date']).$lang_shoutbox['text_ago'];
    }
    $message = format_comment($arr['text'], true, false, true, true, 600, false, false);
    $mentionsMe = false;
    $message = shoutbox_render_mentions($message, $mentionsMe);
    $message = shoutbox_render_torrents($message);
    // Heuristic for the "show more" toggle. The plain-text length lets us decide
    // server-side without measuring layout, at the cost of some imprecision
    // (a single very long word vs many short lines render to different heights).
    $plainLen = mb_strlen(strip_tags($message));
    $isLong = $plainLen > 280;
    $msgClass = $isLong ? 'shout-msg shout-msg-clamped' : 'shout-msg';
    $messageHtml = '<span class="'.$msgClass.'">'.$message.'</span>';
    if ($isLong) {
        $messageHtml .= '<a class="shout-msg-toggle" href="javascript:void(0)" data-on="'.htmlspecialchars($labelLess, ENT_QUOTES).'" data-off="'.htmlspecialchars($labelMore, ENT_QUOTES).'">'.htmlspecialchars($labelMore).'</a>';
    }
    $rowClasses = ['shoutrow'];
    if ($mentionsMe) {
        $rowClasses[] = 'shoutrow-mentions-me';
    }
    if ($isGrouped) {
        $rowClasses[] = 'shout-row-grouped';
        // Replace avatar+username (+badge) with a single 22px spacer so message text
        // stays vertically aligned with the avatar column above.
        $avatarHtml = '<span class="shout-avatar-spacer" aria-hidden="true"></span>';
        $username = '';
        $classBadge = '';
    }
    $rowClass = implode(' ', $rowClasses);

    return '<tr data-shout-id="'.(int) $arr['id'].'"><td class="'.$rowClass.'"><span class=\'date\'>['.$time.']</span> '.
        $del.' '.$avatarHtml.' '.$classBadge.$username.' '.$messageHtml."\n</td></tr>\n";
}

$shoutRows = NexusDB::select($sql);
if (count($shoutRows) === 0) {
    echo "\n";
} else {
    $showAvatars = isset($CURUSER['avatars']) && $CURUSER['avatars'] === 'yes';
    // Group consecutive shouts from the same user posted within this many seconds.
    // Avatar/nick are hidden on the second+ rows of the group; rows render as a
    // continuation with a 22px spacer so message text stays aligned.
    $groupWindowSec = 120;
    $prevUserId = 0;
    $prevDate = 0;
    echo '<table id="shoutbox-table" data-shout-limit="'.(int) $limit."\" border='0' cellspacing='0' cellpadding='2' width='100%' align='left'>\n";

    foreach ($shoutRows as $arr) {
        $currUserId = (int) $arr['userid'];
        $currDate = (int) $arr['date'];
        // Iteration is DESC (newest first), so the previous row is newer in time.
        // Treat the current row as a continuation of an above group when it shares
        // userid with the row above and is within the group window.
        $isContinuation = (
            $currUserId > 0
            && $currUserId === $prevUserId
            && $prevDate > 0
            && abs($prevDate - $currDate) <= $groupWindowSec
        );
        echo shoutbox_render_row($arr, $where, $lang_shoutbox, $CURUSER, $showAvatars, $isContinuation);
        $prevUserId = $currUserId;
        $prevDate = $currDate;
    }
    echo '</table>';
    // Delegated handler so the [more]/[less] toggle works for both the
    // initial server-rendered rows and any future Reverb-prepended rows.
    echo '<script type="text/javascript">(function(){'.
        'var host=document.getElementById("shoutbox-table");'.
        'if(!host||host.__shoutToggleBound)return;'.
        'host.__shoutToggleBound=true;'.
        'host.addEventListener("click",function(e){'.
            'var btn=e.target;'.
            'while(btn&&btn!==host&&!(btn.classList&&btn.classList.contains("shout-msg-toggle"))){btn=btn.parentNode;}'.
            'if(!btn||btn===host)return;'.
            'var msg=btn.previousSibling;while(msg&&msg.nodeType===3){msg=msg.previousSibling;}'.
            'if(!msg)return;'.
            'var clamped=msg.classList.toggle("shout-msg-clamped");'.
            'btn.textContent=clamped?btn.getAttribute("data-off"):btn.getAttribute("data-on");'.
            'if(e.preventDefault)e.preventDefault();'.
        '},false);'.
    '})();</script>';
}
?>
</body>
</html>

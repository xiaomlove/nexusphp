<?php
# IMPORTANT: Do not edit below unless you know what you are doing!
if(!defined('IN_TRACKER'))
die('Hacking attempt!');
include_once($rootpath . 'include/globalfunctions.php');
include_once($rootpath . 'include/config.php');
include_once($rootpath . 'classes/class_advertisement.php');
require_once($rootpath . get_langfile_path("functions.php"));

function get_langfolder_cookie()
{
	global $deflang;
	if (!isset($_COOKIE["c_lang_folder"])) {
		return $deflang;
	} else {
		$langfolder_array = get_langfolder_list();
		foreach($langfolder_array as $lf)
		{
			if($lf == $_COOKIE["c_lang_folder"])
			return $_COOKIE["c_lang_folder"];
		}
		return $deflang;
	}
}

function get_user_lang($user_id)
{
	$lang = mysql_fetch_assoc(sql_query("SELECT site_lang_folder FROM language LEFT JOIN users ON language.id = users.lang WHERE language.site_lang=1 AND users.id= ". sqlesc($user_id) ." LIMIT 1")) or sqlerr(__FILE__, __LINE__);
	return $lang['site_lang_folder'];
}

function get_langfile_path($script_name ="", $target = false, $lang_folder = "")
{
	global $CURLANGDIR;
	$CURLANGDIR = get_langfolder_cookie();
	if($lang_folder == "")
	{
		$lang_folder = $CURLANGDIR;
	}
	return "lang/" . ($target == false ? $lang_folder : "_target") ."/lang_". ( $script_name == "" ? substr(strrchr($_SERVER['SCRIPT_NAME'],'/'),1) : $script_name);
}

function get_row_count($table, $suffix = "")
{
	$r = sql_query("SELECT COUNT(*) FROM $table $suffix") or sqlerr(__FILE__, __LINE__);
	$a = mysql_fetch_row($r) or die(mysql_error());
	return $a[0];
}

function get_row_sum($table, $field, $suffix = "")
{
	$r = sql_query("SELECT SUM($field) FROM $table $suffix") or sqlerr(__FILE__, __LINE__);
	$a = mysql_fetch_row($r) or die(mysql_error());
	return $a[0];
}

function get_single_value($table, $field, $suffix = ""){
	$r = sql_query("SELECT $field FROM $table $suffix LIMIT 1") or sqlerr(__FILE__, __LINE__);
	$a = mysql_fetch_row($r) or die(mysql_error());
	if ($a) {
		return $a[0];
	} else {
		return false;
	}
}

function stdmsg($heading, $text, $htmlstrip = false)
{
	if ($htmlstrip) {
		$heading = htmlspecialchars(trim($heading));
		$text = htmlspecialchars(trim($text));
	}
	print("<table align=\"center\" class=\"main\" width=\"500\" border=\"0\" cellpadding=\"0\" cellspacing=\"0\"><tr><td class=\"embedded\">\n");
	if ($heading)
	print("<h2>".$heading."</h2>\n");
	print("<table width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"10\"><tr><td class=\"text\">");
	print($text . "</td></tr></table></td></tr></table>\n");
}

function stderr($heading, $text, $htmlstrip = true, $head = true, $foot = true, $die = true)
{
	if ($head) stdhead();
	stdmsg($heading, $text, $htmlstrip);
	if ($foot) stdfoot();
	if ($die) die;
}

function sqlerr($file = '', $line = '')
{
	print("<table border=\"0\" bgcolor=\"blue\" align=\"left\" cellspacing=\"0\" cellpadding=\"10\" style=\"background: blue;\">" .
	"<tr><td class=\"embedded\"><font color=\"white\"><h1>SQL Error</h1>\n" .
	"<b>" . mysql_error() . ($file != '' && $line != '' ? "<p>in $file, line $line</p>" : "") . "</b></font></td></tr></table>");
	die;
}

function format_quotes($s)
{
	global $lang_functions;
	preg_match_all('/\\[quote.*?\\]/i', $s, $result, PREG_PATTERN_ORDER);
	$openquotecount = count($openquote = $result[0]);
	preg_match_all('/\\[\/quote\\]/i', $s, $result, PREG_PATTERN_ORDER);
	$closequotecount = count($closequote = $result[0]);

	if ($openquotecount != $closequotecount) return $s; // quote mismatch. Return raw string...

	// Get position of opening quotes
	$openval = array();
	$pos = -1;

	foreach($openquote as $val)
	$openval[] = $pos = strpos($s,$val,$pos+1);

	// Get position of closing quotes
	$closeval = array();
	$pos = -1;

	foreach($closequote as $val)
	$closeval[] = $pos = strpos($s,$val,$pos+1);


	for ($i=0; $i < count($openval); $i++)
	if ($openval[$i] > $closeval[$i]) return $s; // Cannot close before opening. Return raw string...


	$s = preg_replace("/\\[quote\\]/i","<fieldset><legend> ".$lang_functions['text_quote']." </legend><br />",$s);
	$s = preg_replace("/\\[quote=(.+?)\\]/i", "<fieldset><legend> ".$lang_functions['text_quote'].": \\1 </legend><br />", $s);
	$s = preg_replace("/\\[\\/quote\\]/i","</fieldset><br />",$s);
	return $s;
}

function print_attachment($dlkey, $enableimage = true, $imageresizer = true)
{
	global $Cache, $httpdirectory_attachment;
	global $lang_functions;
	if (strlen($dlkey) == 32){
	if (!$row = $Cache->get_value('attachment_'.$dlkey.'_content')){
		$res = sql_query("SELECT * FROM attachments WHERE dlkey=".sqlesc($dlkey)." LIMIT 1") or sqlerr(__FILE__,__LINE__);
		$row = mysql_fetch_array($res);
		$Cache->cache_value('attachment_'.$dlkey.'_content', $row, 86400);
	}
	}
	if (!$row)
	{
		return "<div style=\"text-decoration: line-through; font-size: 7pt\">".$lang_functions['text_attachment_key'].$dlkey.$lang_functions['text_not_found']."</div>";
	}
	else{
	$id = $row['id'];
	if ($row['isimage'] == 1)
	{
		if ($enableimage){
			if ($row['thumb'] == 1){
				$url = $httpdirectory_attachment."/".$row['location'].".thumb.jpg";
			}
			else{
				$url = $httpdirectory_attachment."/".$row['location'];
			}
			if($imageresizer == true)
				$onclick = " onclick=\"Previewurl('".$httpdirectory_attachment."/".$row['location']."')\"";
			else $onclick = "";
			$return = "<img id=\"attach".$id."\" alt=\"".htmlspecialchars($row['filename'])."\" src=\"".$url."\"". $onclick .  " onmouseover=\"domTT_activate(this, event, 'content', '".htmlspecialchars("<strong>".$lang_functions['text_size']."</strong>: ".mksize($row['filesize'])."<br />".gettime($row['added']))."', 'styleClass', 'attach', 'x', findPosition(this)[0], 'y', findPosition(this)[1]-58);\" />";
		}
		else $return = "";
	}
	else
	{
		switch($row['filetype'])
		{
			case 'application/x-bittorrent': {
				$icon = "<img alt=\"torrent\" src=\"pic/attachicons/torrent.gif\" />";
				break;
			}
			case 'application/zip':{
				$icon = "<img alt=\"zip\" src=\"pic/attachicons/archive.gif\" />";
				break;
			}
			case 'application/rar':{
				$icon = "<img alt=\"rar\" src=\"pic/attachicons/archive.gif\" />";
				break;
			}
			case 'application/x-7z-compressed':{
				$icon = "<img alt=\"7z\" src=\"pic/attachicons/archive.gif\" />";
				break;
			}
			case 'application/x-gzip':{
				$icon = "<img alt=\"gzip\" src=\"pic/attachicons/archive.gif\" />";
				break;
			}
			case 'audio/mpeg':{
			}
			case 'audio/ogg':{
				$icon = "<img alt=\"audio\" src=\"pic/attachicons/audio.gif\" />";
				break;
			}
			case 'video/x-flv':{
				$icon = "<img alt=\"flv\" src=\"pic/attachicons/flv.gif\" />";
				break;
			}
			default: {
				$icon = "<img alt=\"other\" src=\"pic/attachicons/common.gif\" />";
			}
		}
		$return = "<div class=\"attach\">".$icon."&nbsp;&nbsp;<a href=\"".htmlspecialchars("getattachment.php?id=".$id."&dlkey=".$dlkey)."\" target=\"_blank\" id=\"attach".$id."\" onmouseover=\"domTT_activate(this, event, 'content', '".htmlspecialchars("<strong>".$lang_functions['text_downloads']."</strong>: ".number_format($row['downloads'])."<br />".gettime($row['added']))."', 'styleClass', 'attach', 'x', findPosition(this)[0], 'y', findPosition(this)[1]-58);\">".htmlspecialchars($row['filename'])."</a>&nbsp;&nbsp;<font class=\"size\">(".mksize($row['filesize']).")</font></div>";
	}
	return $return;
	}
}

function addTempCode($value) {
	global $tempCode, $tempCodeCount;
	$tempCode[$tempCodeCount] = $value;
	$return = "<tempCode_$tempCodeCount>";
	$tempCodeCount++;
	return $return;
}

function formatAdUrl($adid, $url, $content, $newWindow=true)
{
	return formatUrl("adredir.php?id=".$adid."&amp;url=".rawurlencode($url), $newWindow, $content);
}
function formatUrl($url, $newWindow = false, $text = '', $linkClass = '') {
	if (!$text) {
		$text = $url;
	}
	return addTempCode("<a".($linkClass ? " class=\"$linkClass\"" : '')." href=\"$url\"" . ($newWindow==true? " target=\"_blank\"" : "").">$text</a>");
}
function formatCode($text) {
	global $lang_functions;
	return addTempCode("<br /><div class=\"codetop\">".$lang_functions['text_code']."</div><div class=\"codemain\">$text</div><br />");
}

function formatImg($src, $enableImageResizer, $image_max_width, $image_max_height) {
	return addTempCode("<img alt=\"image\" src=\"$src\"" .($enableImageResizer ?  " onload=\"Scale(this,$image_max_width,$image_max_height);\" onclick=\"Preview(this);\"" : "") .  " />");
}

function formatFlash($src, $width, $height) {
	if (!$width) {
		$width = 500;
	}
	if (!$height) {
		$height = 300;
	}
	return addTempCode("<object width=\"$width\" height=\"$height\"><param name=\"movie\" value=\"$src\" /><embed src=\"$src\" width=\"$width\" height=\"$height\" type=\"application/x-shockwave-flash\"></embed></object>");
}
function formatFlv($src, $width, $height) {
	if (!$width) {
		$width = 320;
	}
	if (!$height) {
		$height = 240;
	}
	return addTempCode("<object width=\"$width\" height=\"$height\"><param name=\"movie\" value=\"flvplayer.swf?file=$src\" /><param name=\"allowFullScreen\" value=\"true\" /><embed src=\"flvplayer.swf?file=$src\" type=\"application/x-shockwave-flash\" allowfullscreen=\"true\" width=\"$width\" height=\"$height\"></embed></object>");
}
function format_urls($text, $newWindow = false) {
	return preg_replace("/((https?|ftp|gopher|news|telnet|mms|rtsp):\/\/[^()\[\]<>\s]+)/ei",
	"formatUrl('\\1', ".($newWindow==true ? 1 : 0).", '', 'faqlink')", $text);
}
function format_comment($text, $strip_html = true, $xssclean = false, $newtab = false, $imageresizer = true, $image_max_width = 700, $enableimage = true, $enableflash = true , $imagenum = -1, $image_max_height = 0, $adid = 0)
{
	global $lang_functions;
	global $CURUSER, $SITENAME, $BASEURL, $enableattach_attachment;
	global $tempCode, $tempCodeCount;
	$tempCode = array();
	$tempCodeCount = 0;
	$imageresizer = $imageresizer ? 1 : 0;
	$s = $text;

	if ($strip_html) {
		$s = htmlspecialchars($s);
	}
	// Linebreaks
	$s = nl2br($s);

	if (strpos($s,"[code]") !== false && strpos($s,"[/code]") !== false) {
		$s = preg_replace("/\[code\](.+?)\[\/code\]/eis","formatCode('\\1')", $s);
	}

	$originalBbTagArray = array('[siteurl]', '[site]','[*]', '[b]', '[/b]', '[i]', '[/i]', '[u]', '[/u]', '[pre]', '[/pre]', '[/color]', '[/font]', '[/size]', "  ");
	$replaceXhtmlTagArray = array(get_protocol_prefix().$BASEURL, $SITENAME, '<img class="listicon listitem" src="pic/trans.gif" alt="list" />', '<b>', '</b>', '<i>', '</i>', '<u>', '</u>', '<pre>', '</pre>', '</span>', '</font>', '</font>', ' &nbsp;');
	$s = str_replace($originalBbTagArray, $replaceXhtmlTagArray, $s);

	$originalBbTagArray = array("/\[font=([^\[\(&\\;]+?)\]/is", "/\[color=([#0-9a-z]{1,15})\]/is", "/\[color=([a-z]+)\]/is", "/\[size=([1-7])\]/is");
	$replaceXhtmlTagArray = array("<font face=\"\\1\">", "<span style=\"color: \\1;\">", "<span style=\"color: \\1;\">", "<font size=\"\\1\">");
	$s = preg_replace($originalBbTagArray, $replaceXhtmlTagArray, $s);

	if ($enableattach_attachment == 'yes' && $imagenum != 1){
		$limit = 20;
		$s = preg_replace("/\[attach\]([0-9a-zA-z][0-9a-zA-z]*)\[\/attach\]/ies", "print_attachment('\\1', ".($enableimage ? 1 : 0).", ".($imageresizer ? 1 : 0).")", $s, $limit);
	}

	if ($enableimage) {
		$s = preg_replace("/\[img\]([^\<\r\n\"']+?)\[\/img\]/ei", "formatImg('\\1',".$imageresizer.",".$image_max_width.",".$image_max_height.")", $s, $imagenum, $imgReplaceCount);
		$s = preg_replace("/\[img=([^\<\r\n\"']+?)\]/ei", "formatImg('\\1',".$imageresizer.",".$image_max_width.",".$image_max_height.")", $s, ($imagenum != -1 ? max($imagenum-$imgReplaceCount, 0) : -1));
	} else {
		$s = preg_replace("/\[img\]([^\<\r\n\"']+?)\[\/img\]/i", '', $s, -1);
		$s = preg_replace("/\[img=([^\<\r\n\"']+?)\]/i", '', $s, -1);
	}

	// [flash,500,400]http://www/image.swf[/flash]
	if (strpos($s,"[flash") !== false) { //flash is not often used. Better check if it exist before hand
		if ($enableflash) {
			$s = preg_replace("/\[flash(\,([1-9][0-9]*)\,([1-9][0-9]*))?\]((http|ftp):\/\/[^\s'\"<>]+(\.(swf)))\[\/flash\]/ei", "formatFlash('\\4', '\\2', '\\3')", $s);
		} else {
			$s = preg_replace("/\[flash(\,([1-9][0-9]*)\,([1-9][0-9]*))?\]((http|ftp):\/\/[^\s'\"<>]+(\.(swf)))\[\/flash\]/i", '', $s);
		}
	}
	//[flv,320,240]http://www/a.flv[/flv]
	if (strpos($s,"[flv") !== false) { //flv is not often used. Better check if it exist before hand
		if ($enableflash) {
			$s = preg_replace("/\[flv(\,([1-9][0-9]*)\,([1-9][0-9]*))?\]((http|ftp):\/\/[^\s'\"<>]+(\.(flv)))\[\/flv\]/ei", "formatFlv('\\4', '\\2', '\\3')", $s);
		} else {
			$s = preg_replace("/\[flv(\,([1-9][0-9]*)\,([1-9][0-9]*))?\]((http|ftp):\/\/[^\s'\"<>]+(\.(flv)))\[\/flv\]/i", '', $s);
		}
	}

	// [url=http://www.example.com]Text[/url]
	if ($adid) {
		$s = preg_replace("/\[url=([^\[\s]+?)\](.+?)\[\/url\]/ei", "formatAdUrl(".$adid." ,'\\1', '\\2', ".($newtab==true ? 1 : 0).", 'faqlink')", $s);
	} else {
		$s = preg_replace("/\[url=([^\[\s]+?)\](.+?)\[\/url\]/ei", "formatUrl('\\1', ".($newtab==true ? 1 : 0).", '\\2', 'faqlink')", $s);
	}

	// [url]http://www.example.com[/url]
	$s = preg_replace("/\[url\]([^\[\s]+?)\[\/url\]/ei",
	"formatUrl('\\1', ".($newtab==true ? 1 : 0).", '', 'faqlink')", $s);

	$s = format_urls($s, $newtab);
	// Quotes
	if (strpos($s,"[quote") !== false && strpos($s,"[/quote]") !== false) { //format_quote is kind of slow. Better check if [quote] exists beforehand
		$s = format_quotes($s);
	}

	$s = preg_replace("/\[em([1-9][0-9]*)\]/ie", "(\\1 < 192 ? '<img src=\"pic/smilies/\\1.gif\" alt=\"[em\\1]\" />' : '[em\\1]')", $s);
	reset($tempCode);
	$j = 0;
	while(count($tempCode) || $j > 5) {
		foreach($tempCode as $key=>$code) {
			$s = str_replace("<tempCode_$key>", $code, $s, $count);
			if ($count) {
				unset($tempCode[$key]);
				$i = $i+$count;
			}
		}
		$j++;
	}
	return $s;
}

function highlight($search,$subject,$hlstart='<b><font class="striking">',$hlend="</font></b>") 
{

	$srchlen=strlen($search);    // lenght of searched string
	if ($srchlen==0) return $subject;
	$find = $subject;
	while ($find = stristr($find,$search)) {    // find $search text in $subject -case insensitiv
		$srchtxt = substr($find,0,$srchlen);    // get new search text
		$find=substr($find,$srchlen);
		$subject = str_replace($srchtxt,"$hlstart$srchtxt$hlend",$subject);    // highlight founded case insensitive search text
	}
	return $subject;
}

function get_user_class()
{
	global $CURUSER;
	return $CURUSER["class"];
}

function get_user_class_name($class, $compact = false, $b_colored = false, $I18N = false)
{
	static $en_lang_functions;
	static $current_user_lang_functions;
	if (!$en_lang_functions) {
		require(get_langfile_path("functions.php",false,"en"));
		$en_lang_functions = $lang_functions;
	}

	if(!$I18N) {
		$this_lang_functions = $en_lang_functions;
	} else {
		if (!$current_user_lang_functions) {
			require(get_langfile_path("functions.php"));
			$current_user_lang_functions = $lang_functions;
		}
		$this_lang_functions = $current_user_lang_functions;
	}
	
	$class_name = "";
	switch ($class)
	{
		case UC_PEASANT: {$class_name = $this_lang_functions['text_peasant']; break;}
		case UC_USER: {$class_name = $this_lang_functions['text_user']; break;}
		case UC_POWER_USER: {$class_name = $this_lang_functions['text_power_user']; break;}
		case UC_ELITE_USER: {$class_name = $this_lang_functions['text_elite_user']; break;}
		case UC_CRAZY_USER: {$class_name = $this_lang_functions['text_crazy_user']; break;}
		case UC_INSANE_USER: {$class_name = $this_lang_functions['text_insane_user']; break;}
		case UC_VETERAN_USER: {$class_name = $this_lang_functions['text_veteran_user']; break;}
		case UC_EXTREME_USER: {$class_name = $this_lang_functions['text_extreme_user']; break;}
		case UC_ULTIMATE_USER: {$class_name = $this_lang_functions['text_ultimate_user']; break;}
		case UC_NEXUS_MASTER: {$class_name = $this_lang_functions['text_nexus_master']; break;}
		case UC_VIP: {$class_name = $this_lang_functions['text_vip']; break;}
		case UC_UPLOADER: {$class_name = $this_lang_functions['text_uploader']; break;}
		case UC_RETIREE: {$class_name = $this_lang_functions['text_retiree']; break;}
		case UC_FORUM_MODERATOR: {$class_name = $this_lang_functions['text_forum_moderator']; break;}
		case UC_MODERATOR: {$class_name = $this_lang_functions['text_moderators']; break;}
		case UC_ADMINISTRATOR: {$class_name = $this_lang_functions['text_administrators']; break;}
		case UC_SYSOP: {$class_name = $this_lang_functions['text_sysops']; break;}
		case UC_STAFFLEADER: {$class_name = $this_lang_functions['text_staff_leader']; break;}
	}
	
	switch ($class)
	{
		case UC_PEASANT: {$class_name_color = $en_lang_functions['text_peasant']; break;}
		case UC_USER: {$class_name_color = $en_lang_functions['text_user']; break;}
		case UC_POWER_USER: {$class_name_color = $en_lang_functions['text_power_user']; break;}
		case UC_ELITE_USER: {$class_name_color = $en_lang_functions['text_elite_user']; break;}
		case UC_CRAZY_USER: {$class_name_color = $en_lang_functions['text_crazy_user']; break;}
		case UC_INSANE_USER: {$class_name_color = $en_lang_functions['text_insane_user']; break;}
		case UC_VETERAN_USER: {$class_name_color = $en_lang_functions['text_veteran_user']; break;}
		case UC_EXTREME_USER: {$class_name_color = $en_lang_functions['text_extreme_user']; break;}
		case UC_ULTIMATE_USER: {$class_name_color = $en_lang_functions['text_ultimate_user']; break;}
		case UC_NEXUS_MASTER: {$class_name_color = $en_lang_functions['text_nexus_master']; break;}
		case UC_VIP: {$class_name_color = $en_lang_functions['text_vip']; break;}
		case UC_UPLOADER: {$class_name_color = $en_lang_functions['text_uploader']; break;}
		case UC_RETIREE: {$class_name_color = $en_lang_functions['text_retiree']; break;}
		case UC_FORUM_MODERATOR: {$class_name_color = $en_lang_functions['text_forum_moderator']; break;}
		case UC_MODERATOR: {$class_name_color = $en_lang_functions['text_moderators']; break;}
		case UC_ADMINISTRATOR: {$class_name_color = $en_lang_functions['text_administrators']; break;}
		case UC_SYSOP: {$class_name_color = $en_lang_functions['text_sysops']; break;}
		case UC_STAFFLEADER: {$class_name_color = $en_lang_functions['text_staff_leader']; break;}
	}
	
	$class_name = ( $compact == true ? str_replace(" ", "",$class_name) : $class_name);
	if ($class_name) return ($b_colored == true ? "<b class='" . str_replace(" ", "",$class_name_color) . "_Name'>" . $class_name . "</b>" : $class_name);
}

function is_valid_user_class($class)
{
	return is_numeric($class) && floor($class) == $class && $class >= UC_PEASANT && $class <= UC_STAFFLEADER;
}

function int_check($value,$stdhead = false, $stdfood = true, $die = true, $log = true) {
	global $lang_functions;
	global $CURUSER;
	if (is_array($value))
	{
		foreach ($value as $val) int_check ($val);
	}
	else
	{
		if (!is_valid_id($value)) {
			$msg = "Invalid ID Attempt: Username: ".$CURUSER["username"]." - UserID: ".$CURUSER["id"]." - UserIP : ".getip();
			if ($log)
				write_log($msg,'mod');

			if ($stdhead)
				stderr($lang_functions['std_error'],$lang_functions['std_invalid_id']);
			else
			{
				print ("<h2>".$lang_functions['std_error']."</h2><table width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"10\"><tr><td class=\"text\">");
				print ($lang_functions['std_invalid_id']."</td></tr></table>");
			}
			if ($stdfood)
				stdfoot();
			if ($die)
				die;
		}
		else
			return true;
	}
}

function is_valid_id($id)
{
	return is_numeric($id) && ($id > 0) && (floor($id) == $id);
}


//-------- Begins a main frame
function begin_main_frame($caption = "", $center = false, $width = 100)
{
	$tdextra = "";
	if ($caption)
	print("<h2>".$caption."</h2>");

	if ($center)
	$tdextra .= " align=\"center\"";

	$width = 940 * $width /100;

	print("<table class=\"main\" width=\"".$width."\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">" .
	"<tr><td class=\"embedded\" $tdextra>");
}

function end_main_frame()
{
	print("</td></tr></table>\n");
}

function begin_frame($caption = "", $center = false, $padding = 10, $width="100%", $caption_center="left")
{
	$tdextra = "";

	if ($center)
	$tdextra .= " align=\"center\"";

	print(($caption ? "<h2 align=\"".$caption_center."\">".$caption."</h2>" : "") . "<table width=\"".$width."\" border=\"1\" cellspacing=\"0\" cellpadding=\"".$padding."\">" . "<tr><td class=\"text\" $tdextra>\n");

}

function end_frame()
{
	print("</td></tr></table>\n");
}

function begin_table($fullwidth = false, $padding = 5)
{
	$width = "";

	if ($fullwidth)
	$width .= " width=50%";
	print("<table class=\"main".$width."\" border=\"1\" cellspacing=\"0\" cellpadding=\"".$padding."\">");
}

function end_table()
{
	print("</table>\n");
}

//-------- Inserts a smilies frame
//         (move to globals)

function insert_smilies_frame()
{
	global $lang_functions;
	begin_frame($lang_functions['text_smilies'], true);
	begin_table(false, 5);
	print("<tr><td class=\"colhead\">".$lang_functions['col_type_something']."</td><td class=\"colhead\">".$lang_functions['col_to_make_a']."</td></tr>\n");
	for ($i=1; $i<192; $i++) {
		print("<tr><td>[em$i]</td><td><img src=\"pic/smilies/".$i.".gif\" alt=\"[em$i]\" /></td></tr>\n");
	}
	end_table();
	end_frame();
}

function get_ratio_color($ratio)
{
	if ($ratio < 0.1) return "#ff0000";
	if ($ratio < 0.2) return "#ee0000";
	if ($ratio < 0.3) return "#dd0000";
	if ($ratio < 0.4) return "#cc0000";
	if ($ratio < 0.5) return "#bb0000";
	if ($ratio < 0.6) return "#aa0000";
	if ($ratio < 0.7) return "#990000";
	if ($ratio < 0.8) return "#880000";
	if ($ratio < 0.9) return "#770000";
	if ($ratio < 1) return "#660000";
	return "";
}

function get_slr_color($ratio)
{
	if ($ratio < 0.025) return "#ff0000";
	if ($ratio < 0.05) return "#ee0000";
	if ($ratio < 0.075) return "#dd0000";
	if ($ratio < 0.1) return "#cc0000";
	if ($ratio < 0.125) return "#bb0000";
	if ($ratio < 0.15) return "#aa0000";
	if ($ratio < 0.175) return "#990000";
	if ($ratio < 0.2) return "#880000";
	if ($ratio < 0.225) return "#770000";
	if ($ratio < 0.25) return "#660000";
	if ($ratio < 0.275) return "#550000";
	if ($ratio < 0.3) return "#440000";
	if ($ratio < 0.325) return "#330000";
	if ($ratio < 0.35) return "#220000";
	if ($ratio < 0.375) return "#110000";
	return "";
}

function write_log($text, $security = "normal")
{
	$text = sqlesc($text);
	$added = sqlesc(date("Y-m-d H:i:s"));
	$security = sqlesc($security);
	sql_query("INSERT INTO sitelog (added, txt, security_level) VALUES($added, $text, $security)") or sqlerr(__FILE__, __LINE__);
}



function get_elapsed_time($ts,$shortunit = false)
{
	global $lang_functions;
	$mins = floor(abs(TIMENOW - $ts) / 60);
	$hours = floor($mins / 60);
	$mins -= $hours * 60;
	$days = floor($hours / 24);
	$hours -= $days * 24;
	$months = floor($days / 30);
	$days2 = $days - $months * 30;
	$years = floor($days / 365);
	$months -= $years * 12;
	$t = "";
	if ($years > 0)
	return $years.($shortunit ? $lang_functions['text_short_year'] : $lang_functions['text_year'] . add_s($year)) ."&nbsp;".$months.($shortunit ? $lang_functions['text_short_month'] : $lang_functions['text_month'] . add_s($months));
	if ($months > 0)
	return $months.($shortunit ?  $lang_functions['text_short_month'] : $lang_functions['text_month'] . add_s($months)) ."&nbsp;".$days2.($shortunit ? $lang_functions['text_short_day'] : $lang_functions['text_day'] . add_s($days2));
	if ($days > 0)
	return $days.($shortunit ? $lang_functions['text_short_day'] : $lang_functions['text_day'] . add_s($days))."&nbsp;".$hours.($shortunit ? $lang_functions['text_short_hour'] : $lang_functions['text_hour'] . add_s($hours));
	if ($hours > 0)
	return $hours.($shortunit ? $lang_functions['text_short_hour'] : $lang_functions['text_hour'] . add_s($hours))."&nbsp;".$mins.($shortunit ? $lang_functions['text_short_min'] : $lang_functions['text_min'] . add_s($mins));
	if ($mins > 0)
	return $mins.($shortunit ? $lang_functions['text_short_min'] : $lang_functions['text_min'] . add_s($mins));
	return "&lt; 1".($shortunit ? $lang_functions['text_short_min'] : $lang_functions['text_min']);
}

function textbbcode($form,$text,$content="",$hastitle=false, $col_num = 130)
{
	global $lang_functions;
	global $subject, $BASEURL, $CURUSER, $enableattach_attachment;
?>

<script type="text/javascript">
//<![CDATA[
var b_open = 0;
var i_open = 0;
var u_open = 0;
var color_open = 0;
var list_open = 0;
var quote_open = 0;
var html_open = 0;

var myAgent = navigator.userAgent.toLowerCase();
var myVersion = parseInt(navigator.appVersion);

var is_ie = ((myAgent.indexOf("msie") != -1) && (myAgent.indexOf("opera") == -1));
var is_nav = ((myAgent.indexOf('mozilla')!=-1) && (myAgent.indexOf('spoofer')==-1)
&& (myAgent.indexOf('compatible') == -1) && (myAgent.indexOf('opera')==-1)
&& (myAgent.indexOf('webtv') ==-1) && (myAgent.indexOf('hotjava')==-1));

var is_win = ((myAgent.indexOf("win")!=-1) || (myAgent.indexOf("16bit")!=-1));
var is_mac = (myAgent.indexOf("mac")!=-1);
var bbtags = new Array();
function cstat() {
	var c = stacksize(bbtags);
	if ( (c < 1) || (c == null) ) {c = 0;}
	if ( ! bbtags[0] ) {c = 0;}
	document.<?php echo $form?>.tagcount.value = "Close last, Open "+c;
}
function stacksize(thearray) {
	for (i = 0; i < thearray.length; i++ ) {
		if ( (thearray[i] == "") || (thearray[i] == null) || (thearray == 'undefined') ) {return i;}
	}
	return thearray.length;
}
function pushstack(thearray, newval) {
	arraysize = stacksize(thearray);
	thearray[arraysize] = newval;
}
function popstackd(thearray) {
	arraysize = stacksize(thearray);
	theval = thearray[arraysize - 1];
	return theval;
}
function popstack(thearray) {
	arraysize = stacksize(thearray);
	theval = thearray[arraysize - 1];
	delete thearray[arraysize - 1];
	return theval;
}
function closeall() {
	if (bbtags[0]) {
		while (bbtags[0]) {
			tagRemove = popstack(bbtags)
			if ( (tagRemove != 'color') ) {
				doInsert("[/"+tagRemove+"]", "", false);
				eval("document.<?php echo $form?>." + tagRemove + ".value = ' " + tagRemove + " '");
				eval(tagRemove + "_open = 0");
			} else {
				doInsert("[/"+tagRemove+"]", "", false);
			}
			cstat();
			return;
		}
	}
	document.<?php echo $form?>.tagcount.value = "Close last, Open 0";
	bbtags = new Array();
	document.<?php echo $form?>.<?php echo $text?>.focus();
}
function add_code(NewCode) {
	document.<?php echo $form?>.<?php echo $text?>.value += NewCode;
	document.<?php echo $form?>.<?php echo $text?>.focus();
}
function alterfont(theval, thetag) {
	if (theval == 0) return;
	if(doInsert("[" + thetag + "=" + theval + "]", "[/" + thetag + "]", true)) pushstack(bbtags, thetag);
	document.<?php echo $form?>.color.selectedIndex = 0;
	cstat();
}

function tag_url(PromptURL, PromptTitle, PromptError) {
	var FoundErrors = '';
	var enterURL = prompt(PromptURL, "http://");
	var enterTITLE = prompt(PromptTitle, "");
	if (!enterURL || enterURL=="") {FoundErrors += " " + PromptURL + ",";}
	if (!enterTITLE) {FoundErrors += " " + PromptTitle;}
	if (FoundErrors) {alert(PromptError+FoundErrors);return;}
	doInsert("[url="+enterURL+"]"+enterTITLE+"[/url]", "", false);
}

function tag_list(PromptEnterItem, PromptError) {
	var FoundErrors = '';
	var enterTITLE = prompt(PromptEnterItem, "");
	if (!enterTITLE) {FoundErrors += " " + PromptEnterItem;}
	if (FoundErrors) {alert(PromptError+FoundErrors);return;}
	doInsert("[*]"+enterTITLE+"", "", false);
}

function tag_image(PromptImageURL, PromptError) {
	var FoundErrors = '';
	var enterURL = prompt(PromptImageURL, "http://");
	if (!enterURL || enterURL=="http://") {
		alert(PromptError+PromptImageURL);
		return;
	}
	doInsert("[img]"+enterURL+"[/img]", "", false);
}

function tag_extimage(content) {
	doInsert(content, "", false);
}

function tag_email(PromptEmail, PromptError) {
	var emailAddress = prompt(PromptEmail, "");
	if (!emailAddress) {
		alert(PromptError+PromptEmail);
		return;
	}
	doInsert("[email]"+emailAddress+"[/email]", "", false);
}

function doInsert(ibTag, ibClsTag, isSingle)
{
	var isClose = false;
	var obj_ta = document.<?php echo $form?>.<?php echo $text?>;
	if ( (myVersion >= 4) && is_ie && is_win)
	{
		if(obj_ta.isTextEdit)
		{
			obj_ta.focus();
			var sel = document.selection;
			var rng = sel.createRange();
			rng.colapse;
			if((sel.type == "Text" || sel.type == "None") && rng != null)
			{
				if(ibClsTag != "" && rng.text.length > 0)
				ibTag += rng.text + ibClsTag;
				else if(isSingle) isClose = true;
				rng.text = ibTag;
			}
		}
		else
		{
			if(isSingle) isClose = true;
			obj_ta.value += ibTag;
		}
	}
	else if (obj_ta.selectionStart || obj_ta.selectionStart == '0')
	{
		var startPos = obj_ta.selectionStart;
		var endPos = obj_ta.selectionEnd;
		obj_ta.value = obj_ta.value.substring(0, startPos) + ibTag + obj_ta.value.substring(endPos, obj_ta.value.length);
		obj_ta.selectionEnd = startPos + ibTag.length;
		if(isSingle) isClose = true;
	}
	else
	{
		if(isSingle) isClose = true;
		obj_ta.value += ibTag;
	}
	obj_ta.focus();
	// obj_ta.value = obj_ta.value.replace(/ /, " ");
	return isClose;
}

function winop()
{
	windop = window.open("moresmilies.php?form=<?php echo $form?>&text=<?php echo $text?>","mywin","height=500,width=500,resizable=no,scrollbars=yes");
}

function simpletag(thetag)
{
	var tagOpen = eval(thetag + "_open");
	if (tagOpen == 0) {
		if(doInsert("[" + thetag + "]", "[/" + thetag + "]", true))
		{
			eval(thetag + "_open = 1");
			eval("document.<?php echo $form?>." + thetag + ".value += '*'");
			pushstack(bbtags, thetag);
			cstat();
		}
	}
	else {
		lastindex = 0;
		for (i = 0; i < bbtags.length; i++ ) {
			if ( bbtags[i] == thetag ) {
				lastindex = i;
			}
		}

		while (bbtags[lastindex]) {
			tagRemove = popstack(bbtags);
			doInsert("[/" + tagRemove + "]", "", false)
			if ((tagRemove != 'COLOR') ){
				eval("document.<?php echo $form?>." + tagRemove + ".value = '" + tagRemove.toUpperCase() + "'");
				eval(tagRemove + "_open = 0");
			}
		}
		cstat();
	}
}
//]]>
</script>
<table width="100%" cellspacing="0" cellpadding="5" border="0">
<tr><td align="left" colspan="2">
<table cellspacing="1" cellpadding="2" border="0">
<tr>
<td class="embedded"><input style="font-weight: bold;font-size:11px; margin-right:3px" type="button" name="b" value="B" onclick="javascript: simpletag('b')" /></td>
<td class="embedded"><input class="codebuttons" style="font-style: italic;font-size:11px;margin-right:3px" type="button" name="i" value="I" onclick="javascript: simpletag('i')" /></td>
<td class="embedded"><input class="codebuttons" style="text-decoration: underline;font-size:11px;margin-right:3px" type="button" name="u" value="U" onclick="javascript: simpletag('u')" /></td>
<?php
print("<td class=\"embedded\"><input class=\"codebuttons\" style=\"font-size:11px;margin-right:3px\" type=\"button\" name='url' value='URL' onclick=\"javascript:tag_url('" . $lang_functions['js_prompt_enter_url'] . "','" . $lang_functions['js_prompt_enter_title'] . "','" . $lang_functions['js_prompt_error'] . "')\" /></td>");
print("<td class=\"embedded\"><input class=\"codebuttons\" style=\"font-size:11px;margin-right:3px\" type=\"button\" name=\"IMG\" value=\"IMG\" onclick=\"javascript: tag_image('" . $lang_functions['js_prompt_enter_image_url'] . "','" . $lang_functions['js_prompt_error'] . "')\" /></td>");
print("<td class=\"embedded\"><input type=\"button\" style=\"font-size:11px;margin-right:3px\" name=\"list\" value=\"List\" onclick=\"tag_list('" . addslashes($lang_functions['js_prompt_enter_item']) . "','" . $lang_functions['js_prompt_error'] . "')\" /></td>");
?>
<td class="embedded"><input class="codebuttons" style="font-size:11px;margin-right:3px" type="button" name="quote" value="QUOTE" onclick="javascript: simpletag('quote')" /></td>
<td class="embedded"><input style="font-size:11px;margin-right:3px" type="button" onclick='javascript:closeall();' name='tagcount' value="Close all tags" /></td>
<td class="embedded"><select class="med codebuttons" style="margin-right:3px" name='color' onchange="alterfont(this.options[this.selectedIndex].value, 'color')">
<option value='0'>--- <?php echo $lang_functions['select_color'] ?> ---</option>
<option style="background-color: black" value="Black">Black</option>
<option style="background-color: sienna" value="Sienna">Sienna</option>
<option style="background-color: darkolivegreen" value="DarkOliveGreen">Dark Olive Green</option>
<option style="background-color: darkgreen" value="DarkGreen">Dark Green</option>
<option style="background-color: darkslateblue" value="DarkSlateBlue">Dark Slate Blue</option>
<option style="background-color: navy" value="Navy">Navy</option>
<option style="background-color: indigo" value="Indigo">Indigo</option>
<option style="background-color: darkslategray" value="DarkSlateGray">Dark Slate Gray</option>
<option style="background-color: darkred" value="DarkRed">Dark Red</option>
<option style="background-color: darkorange" value="DarkOrange">Dark Orange</option>
<option style="background-color: olive" value="Olive">Olive</option>
<option style="background-color: green" value="Green">Green</option>
<option style="background-color: teal" value="Teal">Teal</option>
<option style="background-color: blue" value="Blue">Blue</option>
<option style="background-color: slategray" value="SlateGray">Slate Gray</option>
<option style="background-color: dimgray" value="DimGray">Dim Gray</option>
<option style="background-color: red" value="Red">Red</option>
<option style="background-color: sandybrown" value="SandyBrown">Sandy Brown</option>
<option style="background-color: yellowgreen" value="YellowGreen">Yellow Green</option>
<option style="background-color: seagreen" value="SeaGreen">Sea Green</option>
<option style="background-color: mediumturquoise" value="MediumTurquoise">Medium Turquoise</option>
<option style="background-color: royalblue" value="RoyalBlue">Royal Blue</option>
<option style="background-color: purple" value="Purple">Purple</option>
<option style="background-color: gray" value="Gray">Gray</option>
<option style="background-color: magenta" value="Magenta">Magenta</option>
<option style="background-color: orange" value="Orange">Orange</option>
<option style="background-color: yellow" value="Yellow">Yellow</option>
<option style="background-color: lime" value="Lime">Lime</option>
<option style="background-color: cyan" value="Cyan">Cyan</option>
<option style="background-color: deepskyblue" value="DeepSkyBlue">Deep Sky Blue</option>
<option style="background-color: darkorchid" value="DarkOrchid">Dark Orchid</option>
<option style="background-color: silver" value="Silver">Silver</option>
<option style="background-color: pink" value="Pink">Pink</option>
<option style="background-color: wheat" value="Wheat">Wheat</option>
<option style="background-color: lemonchiffon" value="LemonChiffon">Lemon Chiffon</option>
<option style="background-color: palegreen" value="PaleGreen">Pale Green</option>
<option style="background-color: paleturquoise" value="PaleTurquoise">Pale Turquoise</option>
<option style="background-color: lightblue" value="LightBlue">Light Blue</option>
<option style="background-color: plum" value="Plum">Plum</option>
<option style="background-color: white" value="White">White</option>
</select></td>
<td class="embedded">
<select class="med codebuttons" name='font' onchange="alterfont(this.options[this.selectedIndex].value, 'font')">
<option value="0">--- <?php echo $lang_functions['select_font'] ?> ---</option>
<option value="Arial">Arial</option>
<option value="Arial Black">Arial Black</option>
<option value="Arial Narrow">Arial Narrow</option>
<option value="Book Antiqua">Book Antiqua</option>
<option value="Century Gothic">Century Gothic</option>
<option value="Comic Sans MS">Comic Sans MS</option>
<option value="Courier New">Courier New</option>
<option value="Fixedsys">Fixedsys</option>
<option value="Garamond">Garamond</option>
<option value="Georgia">Georgia</option>
<option value="Impact">Impact</option>
<option value="Lucida Console">Lucida Console</option>
<option value="Lucida Sans Unicode">Lucida Sans Unicode</option>
<option value="Microsoft Sans Serif">Microsoft Sans Serif</option>
<option value="Palatino Linotype">Palatino Linotype</option>
<option value="System">System</option>
<option value="Tahoma">Tahoma</option>
<option value="Times New Roman">Times New Roman</option>
<option value="Trebuchet MS">Trebuchet MS</option>
<option value="Verdana">Verdana</option>
</select>
</td>
<td class="embedded">
<select class="med codebuttons" name='size' onchange="alterfont(this.options[this.selectedIndex].value, 'size')">
<option value="0">--- <?php echo $lang_functions['select_size'] ?> ---</option>
<option value="1">1</option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
<option value="5">5</option>
<option value="6">6</option>
<option value="7">7</option>
</select></td></tr>
</table>
</td>
</tr>
<?php
if ($enableattach_attachment == 'yes'){
?>
<tr>
<td colspan="2" valign="middle">
<iframe src="<?php echo get_protocol_prefix() . $BASEURL?>/attachment.php" width="100%" height="24" frameborder="0" scrolling="no" marginheight="0" marginwidth="0"></iframe>
</td>
</tr>
<?php
}
print("<tr>");
print("<td align=\"left\"><textarea class=\"bbcode\" cols=\"100\" style=\"width: 650px;\" name=\"".$text."\" id=\"".$text."\" rows=\"20\" onkeydown=\"ctrlenter(event,'compose','qr')\">".$content."</textarea>");
?>
</td>
<td align="center" width="99%">
<table cellspacing="1" cellpadding="3">
<tr>
<?php
$i = 0;
$quickSmilies = array(1, 2, 3, 5, 6, 7, 8, 9, 10, 11, 13, 16, 17, 19, 20, 21, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 39, 40, 41);
foreach ($quickSmilies as $smily) {
	if ($i%4 == 0 && $i > 0) {
		print('</tr><tr>');
	}
	print("<td class=\"embedded\" style=\"padding: 3px;\">".getSmileIt($form, $text, $smily)."</td>");
	$i++;
}
?>
</tr></table>
<br />
<a href="javascript:winop();"><?php echo $lang_functions['text_more_smilies'] ?></a>
</td></tr></table>
<?php
}

function begin_compose($title = "",$type="new", $body="", $hassubject=true, $subject="", $maxsubjectlength=100){
	global $lang_functions;
	if ($title)
		print("<h1 align=\"center\">".$title."</h1>");
	switch ($type){
		case 'new': 
		{
			$framename = $lang_functions['text_new'];
			break;
		}
		case 'reply': 
		{
			$framename = $lang_functions['text_reply'];
			break;
		}
		case 'quote':
		{
			$framename = $lang_functions['text_quote'];
			break;
		}
		case 'edit':
		{
			$framename = $lang_functions['text_edit'];
			break;
		}
		default:
		{
			$framename = $lang_functions['text_new'];
			break;
		}
	}
	begin_frame($framename, true);
	print("<table class=\"main\" width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n");
	if ($hassubject)
		print("<tr><td class=\"rowhead\">".$lang_functions['row_subject']."</td>" .
"<td class=\"rowfollow\" align=\"left\"><input type=\"text\" style=\"width: 650px;\" name=\"subject\" maxlength=\"".$maxsubjectlength."\" value=\"".$subject."\" /></td></tr>\n");
	print("<tr><td class=\"rowhead\" valign=\"top\">".$lang_functions['row_body']."</td><td class=\"rowfollow\" align=\"left\"><span style=\"display: none;\" id=\"previewouter\"></span><div id=\"editorouter\">");
	textbbcode("compose","body", $body, false);
	print("</div></td></tr>");
}

function end_compose(){
	global $lang_functions;
	print("<tr><td colspan=\"2\" align=\"center\"><table><tr><td class=\"embedded\"><input id=\"qr\" type=\"submit\" class=\"btn\" value=\"".$lang_functions['submit_submit']."\" /></td><td class=\"embedded\">");
	print("<input type=\"button\" class=\"btn2\" name=\"previewbutton\" id=\"previewbutton\" value=\"".$lang_functions['submit_preview']."\" onclick=\"javascript:preview(this.parentNode);\" />");
	print("<input type=\"button\" class=\"btn2\" style=\"display: none;\" name=\"unpreviewbutton\" id=\"unpreviewbutton\" value=\"".$lang_functions['submit_edit']."\" onclick=\"javascript:unpreview(this.parentNode);\" />");
	print("</td></tr></table>");
	print("</td></tr>");
	print("</table>\n");
	end_frame();
	print("<p align=\"center\"><a href=\"tags.php\" target=\"_blank\">".$lang_functions['text_tags']."</a> | <a href=\"smilies.php\" target=\"_blank\">".$lang_functions['text_smilies']."</a></p>\n");
}

function insert_suggest($keyword, $userid, $pre_escaped = true)
{
	if(mb_strlen($keyword,"UTF-8") >= 2)
	{
		$userid = 0 + $userid;
		if($userid)
		sql_query("INSERT INTO suggest(keywords, userid, adddate) VALUES (" . ($pre_escaped == true ? "'" . $keyword . "'" : sqlesc($keyword)) . "," . sqlesc($userid) . ", NOW())") or sqlerr(__FILE__,__LINE__);
	}
}

function get_external_tr($imdb_url = "")
{
	global $lang_functions;
	global $showextinfo;
	$imdbNumber = parse_imdb_id($imdb_url);
	($showextinfo['imdb'] == 'yes' ? tr($lang_functions['row_imdb_url'],  "<input type=\"text\" style=\"width: 650px;\" name=\"url\" value=\"".($imdbNumber ? "http://www.imdb.com/title/tt".parse_imdb_id($imdb_url) : "")."\" /><br /><font class=\"medium\">".$lang_functions['text_imdb_url_note']."</font>", 1) : "");
}

function get_torrent_extinfo_identifier($torrentid)
{
	$torrentid = 0 + $torrentid;

	$result = array('imdb_id');
	unset($result);

	if($torrentid)
	{
		$res = sql_query("SELECT url FROM torrents WHERE id=" . $torrentid) or sqlerr(__FILE__,__LINE__);
		if(mysql_num_rows($res) == 1)
		{
			$arr = mysql_fetch_array($res) or sqlerr(__FILE__,__LINE__);

			$imdb_id = parse_imdb_id($arr["url"]);
			$result['imdb_id'] = $imdb_id;
		}
	}
	return $result;
}

function parse_imdb_id($url)
{
	if ($url != "" && preg_match("/[0-9]{7}/i", $url, $matches)) {
		return $matches[0];
	} elseif ($url && is_numeric($url) && strlen($url) < 7) {
		return str_pad($url, 7, '0', STR_PAD_LEFT);
	} else {
		return false;
	}
}

function build_imdb_url($imdb_id)
{
	return $imdb_id == "" ? "" : "http://www.imdb.com/title/tt" . $imdb_id . "/";
}

// it's a stub implemetation here, we need more acurate regression analysis to complete our algorithm
function get_torrent_2_user_value($user_snatched_arr)
{
	// check if it's current user's torrent
	$torrent_2_user_value = 1.0;

	$torrent_res = sql_query("SELECT * FROM torrents WHERE id = " . $user_snatched_arr['torrentid']) or sqlerr(__FILE__, __LINE__);
	if(mysql_num_rows($torrent_res) == 1)	// torrent still exists
	{
		$torrent_arr = mysql_fetch_array($torrent_res) or sqlerr(__FILE__, __LINE__);
		if($torrent_arr['owner'] == $user_snatched_arr['userid'])	// owner's torrent
		{
			$torrent_2_user_value *= 0.7;	// owner's torrent
			$torrent_2_user_value += ($user_snatched_arr['uploaded'] / $torrent_arr['size'] ) -1 > 0 ? 0.2 - exp(-(($user_snatched_arr['uploaded'] / $torrent_arr['size'] ) -1)) : ($user_snatched_arr['uploaded'] / $torrent_arr['size'] ) -1;
			$torrent_2_user_value += min(0.1 , ($user_snatched_arr['seedtime'] / 37*60*60 ) * 0.1);
		}
		else
		{
			if($user_snatched_arr['finished'] == 'yes')
			{
				$torrent_2_user_value *= 0.5;
				$torrent_2_user_value += ($user_snatched_arr['uploaded'] / $torrent_arr['size'] ) -1 > 0 ? 0.4 - exp(-(($user_snatched_arr['uploaded'] / $torrent_arr['size'] ) -1)) : ($user_snatched_arr['uploaded'] / $torrent_arr['size'] ) -1;
				$torrent_2_user_value += min(0.1, ($user_snatched_arr['seedtime'] / 22*60*60 ) * 0.1);
			}
			else
			{
				$torrent_2_user_value *= 0.2;
				$torrent_2_user_value += min(0.05, ($user_snatched_arr['leechtime'] / 24*60*60 ) * 0.1);	// usually leechtime could not explain much
			}
		}
	}
	else	// torrent already deleted, half blind guess, be conservative
	{
		
		if($user_snatched_arr['finished'] == 'no' && $user_snatched_arr['uploaded'] > 0 && $user_snatched_arr['downloaded'] == 0)	// possibly owner
		{
			$torrent_2_user_value *= 0.55;	//conservative
			$torrent_2_user_value += min(0.05, ($user_snatched_arr['leechtime'] / 31*60*60 ) * 0.1);
			$torrent_2_user_value += min(0.1, ($user_snatched_arr['seedtime'] / 31*60*60 ) * 0.1);
		}
		else if($user_snatched_arr['downloaded'] > 0)	// possibly leecher
		{
			$torrent_2_user_value *= 0.38;	//conservative
			$torrent_2_user_value *= min(0.22, 0.1 * $user_snatched_arr['uploaded'] / $user_snatched_arr['downloaded']);	// 0.3 for conservative
			$torrent_2_user_value += min(0.05, ($user_snatched_arr['leechtime'] / 22*60*60 ) * 0.1);
			$torrent_2_user_value += min(0.12, ($user_snatched_arr['seedtime'] / 22*60*60 ) * 0.1);
		}
		else
			$torrent_2_user_value *= 0.0;
	}
	return $torrent_2_user_value;
}

function cur_user_check () {
	global $lang_functions;
	global $CURUSER;
	if ($CURUSER)
	{
		sql_query("UPDATE users SET lang=" . get_langid_from_langcookie() . " WHERE id = ". $CURUSER['id']);
		stderr ($lang_functions['std_permission_denied'], $lang_functions['std_already_logged_in']);
	}
}

function KPS($type = "+", $point = "1.0", $id = "") {
	global $bonus_tweak;
	if ($point != 0){
		$point = sqlesc($point);
		if ($bonus_tweak == "enable" || $bonus_tweak == "disablesave"){
			sql_query("UPDATE users SET seedbonus = seedbonus$type$point WHERE id = ".sqlesc($id)) or sqlerr(__FILE__, __LINE__);
		}
	}
	else return;
}

function get_agent($peer_id, $agent)
{
	return substr($agent, 0, (strpos($agent, ";") == false ? strlen($agent) : strpos($agent, ";")));
}

function EmailBanned($newEmail)
{
	$newEmail = trim(strtolower($newEmail));
	$sql = sql_query("SELECT * FROM bannedemails") or sqlerr(__FILE__, __LINE__);
	$list = mysql_fetch_array($sql);
	$addresses = explode(' ', preg_replace("/[[:space:]]+/", " ", trim($list[value])) );

	if(count($addresses) > 0)
	{
		foreach ( $addresses as $email )
		{
			$email = trim(strtolower(preg_replace('/\./', '\\.', $email)));
			if(strstr($email, "@"))
			{
				if(preg_match('/^@/', $email))
				{// Any user @host?
					// Expand the match expression to catch hosts and
					// sub-domains
					$email = preg_replace('/^@/', '[@\\.]', $email);
					if(preg_match("/".$email."$/", $newEmail))
					return true;
				}
			}
			elseif(preg_match('/@$/', $email))
			{    // User at any host?
				if(preg_match("/^".$email."/", $newEmail))
				return true;
			}
			else
			{                // User@host
				if(strtolower($email) == $newEmail)
				return true;
			}
		}
	}

	return false;
}

function EmailAllowed($newEmail)
{
global $restrictemaildomain;
if ($restrictemaildomain == 'yes'){
	$newEmail = trim(strtolower($newEmail));
	$sql = sql_query("SELECT * FROM allowedemails") or sqlerr(__FILE__, __LINE__);
	$list = mysql_fetch_array($sql);
	$addresses = explode(' ', preg_replace("/[[:space:]]+/", " ", trim($list[value])) );

	if(count($addresses) > 0)
	{
		foreach ( $addresses as $email )
		{
			$email = trim(strtolower(preg_replace('/\./', '\\.', $email)));
			if(strstr($email, "@"))
			{
				if(preg_match('/^@/', $email))
				{// Any user @host?
					// Expand the match expression to catch hosts and
					// sub-domains
					$email = preg_replace('/^@/', '[@\\.]', $email);
					if(preg_match('/'.$email.'$/', $newEmail))
					return true;
				}
			}
			elseif(preg_match('/@$/', $email))
			{    // User at any host?
				if(preg_match("/^".$email."/", $newEmail))
				return true;
			}
			else
			{                // User@host
				if(strtolower($email) == $newEmail)
				return true;
			}
		}
	}
	return false;
}
else return true;
}

function allowedemails()
{
	$sql = sql_query("SELECT * FROM allowedemails") or sqlerr(__FILE__, __LINE__);
	$list = mysql_fetch_array($sql);
	return $list['value'];
}

function redirect($url)
{
	if(!headers_sent()){
	header("Location : $url");
	}
	else
	echo "<script type=\"text/javascript\">window.location.href = '$url';</script>";
	exit;
}

function set_cachetimestamp($id, $field = "cache_stamp")
{
	sql_query("UPDATE torrents SET $field = " . time() . " WHERE id = " . sqlesc($id)) or sqlerr(__FILE__, __LINE__);
}
function reset_cachetimestamp($id, $field = "cache_stamp")
{
	sql_query("UPDATE torrents SET $field = 0 WHERE id = " . sqlesc($id)) or sqlerr(__FILE__, __LINE__);
}

function cache_check ($file = 'cachefile',$endpage = true, $cachetime = 600) {
	global $lang_functions;
	global $rootpath,$cache,$CURLANGDIR;
	$cachefile = $rootpath.$cache ."/" . $CURLANGDIR .'/'.$file.'.html';
	// Serve from the cache if it is younger than $cachetime
	if (file_exists($cachefile) && (time() - $cachetime < filemtime($cachefile)))
	{
		include($cachefile);
		if ($endpage)
		{
			print("<p align=\"center\"><font class=\"small\">".$lang_functions['text_page_last_updated'].date('Y-m-d H:i:s', filemtime($cachefile))."</font></p>");
			end_main_frame();
			stdfoot();
			exit;
		}
		return false;
	}
  	ob_start();
	return true;
}

function cache_save  ($file = 'cachefile') {
	global $rootpath,$cache;
	global $CURLANGDIR;
	$cachefile = $rootpath.$cache ."/" . $CURLANGDIR . '/'.$file.'.html';
	$fp = fopen($cachefile, 'w');
	// save the contents of output buffer to the file
	fwrite($fp, ob_get_contents());
	// close the file
	fclose($fp);
	// Send the output to the browser
	ob_end_flush();
}

function get_email_encode($lang)
{
	if($lang == 'chs' || $lang == 'cht')
	return "gbk";
	else
	return "utf-8";
}

function change_email_encode($lang, $content)
{
	return iconv("utf-8", get_email_encode($lang) . "//IGNORE", $content);
}

function safe_email($email) {
	$email = str_replace("<","",$email);
	$email = str_replace(">","",$email);
	$email = str_replace("\'","",$email);
	$email = str_replace('\"',"",$email);
	$email = str_replace("\\\\","",$email);

	return $email;
}

function check_email ($email) {
	if(preg_match('/^[A-Za-z0-9][A-Za-z0-9_.+\-]*@[A-Za-z0-9][A-Za-z0-9_+\-]*(\.[A-Za-z0-9][A-Za-z0-9_+\-]*)+$/', $email))
	return true;
	else
	return false;
}

function sent_mail($to,$fromname,$fromemail,$subject,$body,$type = "confirmation",$showmsg=true,$multiple=false,$multiplemail='',$hdr_encoding = 'UTF-8', $specialcase = '') {
	global $lang_functions;
	global $rootpath,$SITENAME,$SITEEMAIL,$smtptype,$smtp,$smtp_host,$smtp_port,$smtp_from,$smtpaddress,$smtpport,$accountname,$accountpassword;
	# Is the OS Windows or Mac or Linux?
	if (strtoupper(substr(PHP_OS,0,3)=='WIN')) {
		$eol="\r\n";
		$windows = true;
	}
	elseif (strtoupper(substr(PHP_OS,0,3)=='MAC'))
		$eol="\r";
	else
		$eol="\n";
	if ($smtptype == 'none')
		return false;
	if ($smtptype == 'default') {
		@mail($to, "=?".$hdr_encoding."?B?".base64_encode($subject)."?=", $body, "From: ".$SITEEMAIL.$eol."Content-type: text/html; charset=".$hdr_encoding.$eol, "-f$SITEEMAIL") or stderr($lang_functions['std_error'], $lang_functions['text_unable_to_send_mail']);
	}
	elseif ($smtptype == 'advanced') {
		$mid = md5(getip() . $fromname);
		$name = $_SERVER["SERVER_NAME"];
		$headers .= "From: $fromname <$fromemail>".$eol;
		$headers .= "Reply-To: $fromname <$fromemail>".$eol;
		$headers .= "Return-Path: $fromname <$fromemail>".$eol;
		$headers .= "Message-ID: <$mid thesystem@$name>".$eol;
		$headers .= "X-Mailer: PHP v".phpversion().$eol;
		$headers .= "MIME-Version: 1.0".$eol;
		$headers .= "Content-type: text/html; charset=".$hdr_encoding.$eol;
		$headers .= "X-Sender: PHP".$eol;
		if ($multiple)
		{
			$bcc_multiplemail = "";
			foreach ($multiplemail as $toemail)
			$bcc_multiplemail = $bcc_multiplemail . ( $bcc_multiplemail != "" ? "," : "") . $toemail;

			$headers .= "Bcc: $multiplemail.$eol";
		}
		if ($smtp == "yes") {
			ini_set('SMTP', $smtp_host);
			ini_set('smtp_port', $smtp_port);
			if ($windows)
			ini_set('sendmail_from', $smtp_from);
		}

		@mail($to,"=?".$hdr_encoding."?B?".base64_encode($subject)."?=",$body,$headers) or stderr($lang_functions['std_error'], $lang_functions['text_unable_to_send_mail']);

		ini_restore(SMTP);
		ini_restore(smtp_port);
		if ($windows)
		ini_restore(sendmail_from);
	}
	elseif ($smtptype == 'external') {
		require_once ($rootpath . 'include/smtp/smtp.lib.php');
		$mail = new smtp($hdr_encoding,'eYou');
		$mail->debug(false);
		$mail->open($smtpaddress, $smtpport);
		$mail->auth($accountname, $accountpassword);
		//	$mail->bcc($multiplemail);
		$mail->from($SITEEMAIL);
		if ($multiple)
		{
			$mail->multi_to_head($to);
			foreach ($multiplemail as $toemail)
			$mail->multi_to($toemail);
		}
		else
		$mail->to($to);
		$mail->mime_content_transfer_encoding();
		$mail->mime_charset('text/html', $hdr_encoding);
		$mail->subject($subject);
		$mail->body($body);
		$mail->send() or stderr($lang_functions['std_error'], $lang_functions['text_unable_to_send_mail']);
		$mail->close();
	}
	if ($showmsg) {
		if ($type == "confirmation")
		stderr($lang_functions['std_success'], $lang_functions['std_confirmation_email_sent']."<b>". htmlspecialchars($to) ."</b>.\n" .
		$lang_functions['std_please_wait'],false);
		elseif ($type == "details")
		stderr($lang_functions['std_success'], $lang_functions['std_account_details_sent']."<b>". htmlspecialchars($to) ."</b>.\n" .
		$lang_functions['std_please_wait'],false);
	}else
	return true;
}

function failedloginscheck ($type = 'Login') {
	global $lang_functions;
	global $maxloginattempts;
	$total = 0;
	$ip = sqlesc(getip());
	$Query = sql_query("SELECT SUM(attempts) FROM loginattempts WHERE ip=$ip") or sqlerr(__FILE__, __LINE__);
	list($total) = mysql_fetch_array($Query);
	if ($total >= $maxloginattempts) {
		sql_query("UPDATE loginattempts SET banned = 'yes' WHERE ip=$ip") or sqlerr(__FILE__, __LINE__);
		stderr($type.$lang_functions['std_locked'].$type.$lang_functions['std_attempts_reached'], $lang_functions['std_your_ip_banned']);
	}
}
function failedlogins ($type = 'login', $recover = false, $head = true)
{
	global $lang_functions;
	$ip = sqlesc(getip());
	$added = sqlesc(date("Y-m-d H:i:s"));
	$a = (@mysql_fetch_row(@sql_query("select count(*) from loginattempts where ip=$ip"))) or sqlerr(__FILE__, __LINE__);
	if ($a[0] == 0)
	sql_query("INSERT INTO loginattempts (ip, added, attempts) VALUES ($ip, $added, 1)") or sqlerr(__FILE__, __LINE__);
	else
	sql_query("UPDATE loginattempts SET attempts = attempts + 1 where ip=$ip") or sqlerr(__FILE__, __LINE__);
	if ($recover)
	sql_query("UPDATE loginattempts SET type = 'recover' WHERE ip = $ip") or sqlerr(__FILE__, __LINE__);
	if ($type == 'silent')
	return;
	elseif ($type == 'login')
	{
		stderr($lang_functions['std_login_failed'],$lang_functions['std_login_failed_note'],false);
	}
	else
	stderr($lang_functions['std_failed'],$type,false, $head);

}

function login_failedlogins($type = 'login', $recover = false, $head = true)
{
	global $lang_functions;
	$ip = sqlesc(getip());
	$added = sqlesc(date("Y-m-d H:i:s"));
	$a = (@mysql_fetch_row(@sql_query("select count(*) from loginattempts where ip=$ip"))) or sqlerr(__FILE__, __LINE__);
	if ($a[0] == 0)
	sql_query("INSERT INTO loginattempts (ip, added, attempts) VALUES ($ip, $added, 1)") or sqlerr(__FILE__, __LINE__);
	else
	sql_query("UPDATE loginattempts SET attempts = attempts + 1 where ip=$ip") or sqlerr(__FILE__, __LINE__);
	if ($recover)
	sql_query("UPDATE loginattempts SET type = 'recover' WHERE ip = $ip") or sqlerr(__FILE__, __LINE__);
	if ($type == 'silent')
	return;
	elseif ($type == 'login')
	{
		stderr($lang_functions['std_login_failed'],$lang_functions['std_login_failed_note'],false);
	}
	else
	stderr($lang_functions['std_recover_failed'],$type,false, $head);
}

function remaining ($type = 'login') {
	global $maxloginattempts;
	$total = 0;
	$ip = sqlesc(getip());
	$Query = sql_query("SELECT SUM(attempts) FROM loginattempts WHERE ip=$ip") or sqlerr(__FILE__, __LINE__);
	list($total) = mysql_fetch_array($Query);
	$remaining = $maxloginattempts - $total;
	if ($remaining <= 2 )
	$remaining = "<font color=\"red\" size=\"2\">[".$remaining."]</font>";
	else
	$remaining = "<font color=\"green\" size=\"2\">[".$remaining."]</font>";

	return $remaining;
}

function registration_check($type = "invitesystem", $maxuserscheck = true, $ipcheck = true) {
	global $lang_functions;
	global $invitesystem, $registration, $maxusers, $SITENAME, $maxip;
	if ($type == "invitesystem") {
		if ($invitesystem == "no") {
			stderr($lang_functions['std_oops'], $lang_functions['std_invite_system_disabled'], 0);
		}
	}

	if ($type == "normal") {
		if ($registration == "no") {
			stderr($lang_functions['std_sorry'], $lang_functions['std_open_registration_disabled'], 0);
		}
	}

	if ($maxuserscheck) {
		$res = sql_query("SELECT COUNT(*) FROM users") or sqlerr(__FILE__, __LINE__);
		$arr = mysql_fetch_row($res);
		if ($arr[0] >= $maxusers)
		stderr($lang_functions['std_sorry'], $lang_functions['std_account_limit_reached'], 0);
	}

	if ($ipcheck) {
		$ip = getip () ;
		$a = (@mysql_fetch_row(@sql_query("select count(*) from users where ip='" . mysql_real_escape_string($ip) . "'"))) or sqlerr(__FILE__, __LINE__);
		if ($a[0] > $maxip)
		stderr($lang_functions['std_sorry'], $lang_functions['std_the_ip']."<b>" . htmlspecialchars($ip) ."</b>". $lang_functions['std_used_many_times'],false);
	}
	return true;
}

function random_str($length="6")
{
	$set = array("A","B","C","D","E","F","G","H","P","R","M","N","1","2","3","4","5","6","7","8","9");
	$str;
	for($i=1;$i<=$length;$i++)
	{
		$ch = rand(0, count($set)-1);
		$str .= $set[$ch];
	}
	return $str;
}
function image_code () {
	$randomstr = random_str();
	$imagehash = md5($randomstr);
	$dateline = time();
	$sql = 'INSERT INTO `regimages` (`imagehash`, `imagestring`, `dateline`) VALUES (\''.$imagehash.'\', \''.$randomstr.'\', \''.$dateline.'\');';
	sql_query($sql) or die(mysql_error());
	return $imagehash;
}

function check_code ($imagehash, $imagestring, $where = 'signup.php',$maxattemptlog=false,$head=true) {
	global $lang_functions;
	$query = sprintf("SELECT * FROM regimages WHERE imagehash='%s' AND imagestring='%s'",
	mysql_real_escape_string($imagehash),
	mysql_real_escape_string($imagestring));
	$sql = sql_query($query);
	$imgcheck = mysql_fetch_array($sql);
	if(!$imgcheck['dateline']) {
		$delete = sprintf("DELETE FROM regimages WHERE imagehash='%s'",
		mysql_real_escape_string($imagehash));
		sql_query($delete);
		if (!$maxattemptlog)
		bark($lang_functions['std_invalid_image_code']."<a href=\"".htmlspecialchars($where)."\">".$lang_functions['std_here_to_request_new']);
		else
		failedlogins($lang_functions['std_invalid_image_code']."<a href=\"".htmlspecialchars($where)."\">".$lang_functions['std_here_to_request_new'],true,$head);
	}else{
		$delete = sprintf("DELETE FROM regimages WHERE imagehash='%s'",
		mysql_real_escape_string($imagehash));
		sql_query($delete);
		return true;
	}
}
function show_image_code () {
	global $lang_functions;
	global $iv;
	if ($iv == "yes") {
		unset($imagehash);
		$imagehash = image_code () ;
		print ("<tr><td class=\"rowhead\">".$lang_functions['row_security_image']."</td>");
		print ("<td align=\"left\"><img src=\"".htmlspecialchars("image.php?action=regimage&imagehash=".$imagehash)."\" border=\"0\" alt=\"CAPTCHA\" /></td></tr>");
		print ("<tr><td class=\"rowhead\">".$lang_functions['row_security_code']."</td><td align=\"left\">");
		print("<input type=\"text\" autocomplete=\"off\" style=\"width: 180px; border: 1px solid gray\" name=\"imagestring\" value=\"\" />");
		print("<input type=\"hidden\" name=\"imagehash\" value=\"$imagehash\" /></td></tr>");
	}
}

function get_ip_location($ip)
{
	global $lang_functions;
	global $Cache;
	if (!$ret = $Cache->get_value('location_list')){
		$ret = array();
		$res = sql_query("SELECT * FROM locations") or sqlerr(__FILE__, __LINE__);
		while ($row = mysql_fetch_array($res))
			$ret[] = $row;
		$Cache->cache_value('location_list', $ret, 152800);
	}
	$location = array($lang_functions['text_unknown'],"");

	foreach($ret AS $arr)
	{
		if(in_ip_range(false, $ip, $arr["start_ip"], $arr["end_ip"]))
		{
			$location = array($arr["name"], $lang_functions['text_user_ip'].":&nbsp;" . $ip . ($arr["location_main"] != "" ? "&nbsp;".$lang_functions['text_location_main'].":&nbsp;" . $arr["location_main"] : ""). ($arr["location_sub"] != "" ? "&nbsp;".$lang_functions['text_location_sub'].":&nbsp;" . $arr["location_sub"] : "") . "&nbsp;".$lang_functions['text_ip_range'].":&nbsp;" . $arr["start_ip"] . "&nbsp;~&nbsp;". $arr["end_ip"]);
			break;
		}
	}
	return $location;
}

function in_ip_range($long, $targetip, $ip_one, $ip_two=false)
{
	// if only one ip, check if is this ip
	if($ip_two===false){
		if(($long ? (long2ip($ip_one) == $targetip) : ( $ip_one == $targetip))){
			$ip=true;
		}
		else{
			$ip=false;
		}
	}
	else{
		if($long ? ($ip_one<=ip2long($targetip) && $ip_two>=ip2long($targetip)) : (ip2long($ip_one)<=ip2long($targetip) && ip2long($ip_two)>=ip2long($targetip))){
			$ip=true;
		}
		else{
			$ip=false;
		}
	}
	return $ip;
}


function validip_format($ip)
{
	$ipPattern =
	'/\b(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.' .
	'(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.' .
	'(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.' .
	'(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\b/';

	return preg_match($ipPattern, $ip);
}

function maxslots () {
	global $lang_functions;
	global $CURUSER, $maxdlsystem;
	$gigs = $CURUSER["uploaded"] / (1024*1024*1024);
	$ratio = (($CURUSER["downloaded"] > 0) ? ($CURUSER["uploaded"] / $CURUSER["downloaded"]) : 1);
	if ($ratio < 0.5 || $gigs < 5) $max = 1;
	elseif ($ratio < 0.65 || $gigs < 6.5) $max = 2;
	elseif ($ratio < 0.8 || $gigs < 8) $max = 3;
	elseif ($ratio < 0.95 || $gigs < 9.5) $max = 4;
	else $max = 0;
	if ($maxdlsystem == "yes") {
		if (get_user_class() < UC_VIP) {
			if ($max > 0)
			print ("<font class='color_slots'>".$lang_functions['text_slots']."</font><a href=\"faq.php#id215\">$max</a>");
			else
			print ("<font class='color_slots'>".$lang_functions['text_slots']."</font>".$lang_functions['text_unlimited']);
		}else
		print ("<font class='color_slots'>".$lang_functions['text_slots']."</font>".$lang_functions['text_unlimited']);
	}else
	print ("<font class='color_slots'>".$lang_functions['text_slots']."</font>".$lang_functions['text_unlimited']);
}

function WriteConfig ($configname = NULL, $config = NULL) {
	global $lang_functions, $CONFIGURATIONS;

	if (file_exists('config/allconfig.php')) {
		require('config/allconfig.php');
	}
	if ($configname) {
		$$configname=$config;
	}
	$path = './config/allconfig.php';
	if (!file_exists($path) || !is_writable ($path)) {
		stdmsg($lang_functions['std_error'], $lang_functions['std_cannot_read_file']."[<b>".htmlspecialchars($path)."</b>]".$lang_functions['std_access_permission_note']);
	}
	$data = "<?php\n";
	foreach ($CONFIGURATIONS as $CONFIGURATION) {
		$data .= "\$$CONFIGURATION=".getExportedValue($$CONFIGURATION).";\n";
	}
	$fp = @fopen ($path, 'w');
	if (!$fp) {
		stdmsg($lang_functions['std_error'], $lang_functions['std_cannot_open_file']."[<b>".htmlspecialchars($path)."</b>]".$lang_functions['std_to_save_info'].$lang_functions['std_access_permission_note']);
	}
	$Res = @fwrite($fp, $data);
	if (empty($Res)) {
		stdmsg($lang_functions['std_error'], $lang_functions['text_cannot_save_info_in']."[<b>".htmlspecialchars($path)."</b>]".$lang_functions['std_access_permission_note']);
	}
	fclose($fp);
	return true;
}

function getExportedValue($input,$t = null) {
	switch (gettype($input)) {
		case 'string':
			return "'".str_replace(array("\\","'"),array("\\\\","\'"),$input)."'";
		case 'array':
			$output = "array(\r";
			foreach ($input as $key => $value) {
				$output .= $t."\t".getExportedValue($key,$t."\t").' => '.getExportedValue($value,$t."\t");
				$output .= ",\n";
			}
			$output .= $t.')';
			return $output;
		case 'boolean':
			return $input ? 'true' : 'false';
		case 'NULL':
			return 'NULL';
		case 'integer':
		case 'double':
		case 'float':
			return "'".(string)$input."'";
	 }
	 return 'NULL';
}

function dbconn($autoclean = false)
{
	global $lang_functions;
	global $mysql_host, $mysql_user, $mysql_pass, $mysql_db;
	global $useCronTriggerCleanUp;

	if (!mysql_connect($mysql_host, $mysql_user, $mysql_pass))
	{
		switch (mysql_errno())
		{
			case 1040:
			case 2002:
				die("<html><head><meta http-equiv=refresh content=\"10 $_SERVER[REQUEST_URI]\"><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"></head><body><table border=0 width=100% height=100%><tr><td><h3 align=center>".$lang_functions['std_server_load_very_high']."</h3></td></tr></table></body></html>");
			default:
				die("[" . mysql_errno() . "] dbconn: mysql_connect: " . mysql_error());
		}
	}
	mysql_query("SET NAMES UTF8");
	mysql_query("SET collation_connection = 'utf8_general_ci'");
	mysql_query("SET sql_mode=''");
	mysql_select_db($mysql_db) or die('dbconn: mysql_select_db: ' + mysql_error());

	userlogin();

	if (!$useCronTriggerCleanUp && $autoclean) {
		register_shutdown_function("autoclean");
	}
}
function get_user_row($id)
{
	global $Cache, $CURUSER;
	static $curuserRowUpdated = false;
	static $neededColumns = array('id', 'noad', 'class', 'enabled', 'privacy', 'avatar', 'signature', 'uploaded', 'downloaded', 'last_access', 'username', 'donor', 'leechwarn', 'warned', 'title');
	if ($id == $CURUSER['id']) {
		$row = array();
		foreach($neededColumns as $column) {
			$row[$column] = $CURUSER[$column];
		}
		if (!$curuserRowUpdated) {
			$Cache->cache_value('user_'.$CURUSER['id'].'_content', $row, 900);
			$curuserRowUpdated = true;
		}
	} elseif (!$row = $Cache->get_value('user_'.$id.'_content')){
		$res = sql_query("SELECT ".implode(',', $neededColumns)." FROM users WHERE id = ".sqlesc($id)) or sqlerr(__FILE__,__LINE__);
		$row = mysql_fetch_array($res);
		$Cache->cache_value('user_'.$id.'_content', $row, 900);
	}

	if (!$row)
		return false;
	else return $row;
}

function userlogin() {
	global $lang_functions;
	global $Cache;
	global $SITE_ONLINE, $oldip;
	global $enablesqldebug_tweak, $sqldebug_tweak;
	unset($GLOBALS["CURUSER"]);

	$ip = getip();
	$nip = ip2long($ip);
	if ($nip) //$nip would be false for IPv6 address
	{
		$res = sql_query("SELECT * FROM bans WHERE $nip >= first AND $nip <= last") or sqlerr(__FILE__, __LINE__);
		if (mysql_num_rows($res) > 0)
		{
			header("HTTP/1.0 403 Forbidden");
			print("<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"></head><body>".$lang_functions['text_unauthorized_ip']."</body></html>\n");
			die;
		}
	}

	if (empty($_COOKIE["c_secure_pass"]) || empty($_COOKIE["c_secure_uid"]) || empty($_COOKIE["c_secure_login"]))
		return;
	if ($_COOKIE["c_secure_login"] == base64("yeah"))
	{
		//if (empty($_SESSION["s_secure_uid"]) || empty($_SESSION["s_secure_pass"]))
		//return;
	}
	$b_id = base64($_COOKIE["c_secure_uid"],false);
	$id = 0 + $b_id;
	if (!$id || !is_valid_id($id) || strlen($_COOKIE["c_secure_pass"]) != 32)
	return;

	if ($_COOKIE["c_secure_login"] == base64("yeah"))
	{
		//if (strlen($_SESSION["s_secure_pass"]) != 32)
		//return;
	}

	$res = sql_query("SELECT * FROM users WHERE users.id = ".sqlesc($id)." AND users.enabled='yes' AND users.status = 'confirmed' LIMIT 1");
	$row = mysql_fetch_array($res);
	if (!$row)
	return;

	$sec = hash_pad($row["secret"]);

	//die(base64_decode($_COOKIE["c_secure_login"]));

	if ($_COOKIE["c_secure_login"] == base64("yeah"))
	{

		if ($_COOKIE["c_secure_pass"] != md5($row["passhash"].$_SERVER["REMOTE_ADDR"]))
		return;
	}
	else
	{
		if ($_COOKIE["c_secure_pass"] !== md5($row["passhash"]))
		return;
	}

	if ($_COOKIE["c_secure_login"] == base64("yeah"))
	{
		//if ($_SESSION["s_secure_pass"] !== md5($row["passhash"].$_SERVER["REMOTE_ADDR"]))
		//return;
	}
	if (!$row["passkey"]){
		$passkey = md5($row['username'].date("Y-m-d H:i:s").$row['passhash']);
		sql_query("UPDATE users SET passkey = ".sqlesc($passkey)." WHERE id=" . sqlesc($row["id"]));// or die(mysql_error());
	}

	$oldip = $row['ip'];
	$row['ip'] = $ip;
	$GLOBALS["CURUSER"] = $row;
	if ($_GET['clearcache'] && get_user_class() >= UC_MODERATOR) {
	    $Cache->setClearCache(1);
	}
	if ($enablesqldebug_tweak == 'yes' && get_user_class() >= $sqldebug_tweak) {
		error_reporting(E_ALL & ~E_NOTICE);
	}
}

function autoclean() {
	global $autoclean_interval_one, $rootpath;
	$now = TIMENOW;

	$res = sql_query("SELECT value_u FROM avps WHERE arg = 'lastcleantime'");
	$row = mysql_fetch_array($res);
	if (!$row) {
		sql_query("INSERT INTO avps (arg, value_u) VALUES ('lastcleantime',$now)") or sqlerr(__FILE__, __LINE__);
		return false;
	}
	$ts = $row[0];
	if ($ts + $autoclean_interval_one > $now) {
		return false;
	}
	sql_query("UPDATE avps SET value_u=$now WHERE arg='lastcleantime' AND value_u = $ts") or sqlerr(__FILE__, __LINE__);
	if (!mysql_affected_rows()) {
		return false;
	}
	require_once($rootpath . 'include/cleanup.php');
	return docleanup();
}

function unesc($x) {
	return $x;
}


function getsize_int($amount, $unit = "G")
{
	if ($unit == "B")
	return floor($amount);
	elseif ($unit == "K")
	return floor($amount * 1024);
	elseif ($unit == "M")
	return floor($amount * 1048576);
	elseif ($unit == "G")
	return floor($amount * 1073741824);
	elseif($unit == "T")
	return floor($amount * 1099511627776);
	elseif($unit == "P")
	return floor($amount * 1125899906842624);
}

function mksize_compact($bytes)
{
	if ($bytes < 1000 * 1024)
	return number_format($bytes / 1024, 2) . "<br />KB";
	elseif ($bytes < 1000 * 1048576)
	return number_format($bytes / 1048576, 2) . "<br />MB";
	elseif ($bytes < 1000 * 1073741824)
	return number_format($bytes / 1073741824, 2) . "<br />GB";
	elseif ($bytes < 1000 * 1099511627776)
	return number_format($bytes / 1099511627776, 3) . "<br />TB";
	else
	return number_format($bytes / 1125899906842624, 3) . "<br />PB";
}

function mksize_loose($bytes)
{
	if ($bytes < 1000 * 1024)
	return number_format($bytes / 1024, 2) . "&nbsp;KB";
	elseif ($bytes < 1000 * 1048576)
	return number_format($bytes / 1048576, 2) . "&nbsp;MB";
	elseif ($bytes < 1000 * 1073741824)
	return number_format($bytes / 1073741824, 2) . "&nbsp;GB";
	elseif ($bytes < 1000 * 1099511627776)
	return number_format($bytes / 1099511627776, 3) . "&nbsp;TB";
	else
	return number_format($bytes / 1125899906842624, 3) . "&nbsp;PB";
}

function mksize($bytes)
{
	if ($bytes < 1000 * 1024)
	return number_format($bytes / 1024, 2) . " KB";
	elseif ($bytes < 1000 * 1048576)
	return number_format($bytes / 1048576, 2) . " MB";
	elseif ($bytes < 1000 * 1073741824)
	return number_format($bytes / 1073741824, 2) . " GB";
	elseif ($bytes < 1000 * 1099511627776)
	return number_format($bytes / 1099511627776, 3) . " TB";
	else
	return number_format($bytes / 1125899906842624, 3) . " PB";
}


function mksizeint($bytes)
{
	$bytes = max(0, $bytes);
	if ($bytes < 1000)
	return floor($bytes) . " B";
	elseif ($bytes < 1000 * 1024)
	return floor($bytes / 1024) . " kB";
	elseif ($bytes < 1000 * 1048576)
	return floor($bytes / 1048576) . " MB";
	elseif ($bytes < 1000 * 1073741824)
	return floor($bytes / 1073741824) . " GB";
	elseif ($bytes < 1000 * 1099511627776)
	return floor($bytes / 1099511627776) . " TB";
	else
	return floor($bytes / 1125899906842624) . " PB";
}

function deadtime() {
	global $anninterthree;
	return time() - floor($anninterthree * 1.3);
}

function mkprettytime($s) {
	global $lang_functions;
	if ($s < 0)
	$s = 0;
	$t = array();
	foreach (array("60:sec","60:min","24:hour","0:day") as $x) {
		$y = explode(":", $x);
		if ($y[0] > 1) {
			$v = $s % $y[0];
			$s = floor($s / $y[0]);
		}
		else
		$v = $s;
		$t[$y[1]] = $v;
	}

	if ($t["day"])
	return $t["day"] . $lang_functions['text_day'] . sprintf("%02d:%02d:%02d", $t["hour"], $t["min"], $t["sec"]);
	if ($t["hour"])
	return sprintf("%d:%02d:%02d", $t["hour"], $t["min"], $t["sec"]);
	//    if ($t["min"])
	return sprintf("%d:%02d", $t["min"], $t["sec"]);
	//    return $t["sec"] . " secs";
}

function mkglobal($vars) {
	if (!is_array($vars))
	$vars = explode(":", $vars);
	foreach ($vars as $v) {
		if (isset($_GET[$v]))
		$GLOBALS[$v] = unesc($_GET[$v]);
		elseif (isset($_POST[$v]))
		$GLOBALS[$v] = unesc($_POST[$v]);
		else
		return 0;
	}
	return 1;
}

function tr($x,$y,$noesc=0,$relation='') {
	if ($noesc)
	$a = $y;
	else {
		$a = htmlspecialchars($y);
		$a = str_replace("\n", "<br />\n", $a);
	}
	print("<tr".( $relation ? " relation = \"$relation\"" : "")."><td class=\"rowhead nowrap\" valign=\"top\" align=\"right\">$x</td><td class=\"rowfollow\" valign=\"top\" align=\"left\">".$a."</td></tr>\n");
}

function tr_small($x,$y,$noesc=0,$relation='') {
	if ($noesc)
	$a = $y;
	else {
		$a = htmlspecialchars($y);
		//$a = str_replace("\n", "<br />\n", $a);
	}
	print("<tr".( $relation ? " relation = \"$relation\"" : "")."><td width=\"1%\" class=\"rowhead nowrap\" valign=\"top\" align=\"right\">".$x."</td><td width=\"99%\" class=\"rowfollow\" valign=\"top\" align=\"left\">".$a."</td></tr>\n");
}

function twotd($x,$y,$nosec=0){
	if ($noesc)
	$a = $y;
	else {
		$a = htmlspecialchars($y);
		$a = str_replace("\n", "<br />\n", $a);
	}
	print("<td class=\"rowhead\">".$x."</td><td class=\"rowfollow\">".$y."</td>");
}

function validfilename($name) {
	return preg_match('/^[^\0-\x1f:\\\\\/?*\xff#<>|]+$/si', $name);
}

function validemail($email) {
	return preg_match('/^[\w.-]+@([\w.-]+\.)+[a-z]{2,6}$/is', $email);
}

function validlang($langid) {
	global $deflang;
	$langid = 0 + $langid;
	$res = sql_query("SELECT * FROM language WHERE site_lang = 1 AND id = " . sqlesc($langid)) or sqlerr(__FILE__, __LINE__);
	if(mysql_num_rows($res) == 1)
	{
		$arr = mysql_fetch_array($res)  or sqlerr(__FILE__, __LINE__);
		return $arr['site_lang_folder'];
	}
	else return $deflang;
}

function get_if_restricted_is_open()
{
	global $sptime; 
	// it's sunday
	if($sptime == 'yes' && (date("w",time()) == '0' || (date("w",time()) == 6) && (date("G",time()) >=12 && date("G",time()) <=23)))
	{
		return true;
	}
	else
	return false;
}

function menu ($selected = "home") {
	global $lang_functions;
	global $BASEURL,$CURUSER;
	global $enableoffer, $enablespecial, $enableextforum, $extforumurl, $where_tweak;
	global $USERUPDATESET;
	$script_name = $_SERVER["SCRIPT_FILENAME"];
	if (preg_match("/index/i", $script_name)) {
		$selected = "home";
	}elseif (preg_match("/forums/i", $script_name)) {
		$selected = "forums";
	}elseif (preg_match("/torrents/i", $script_name)) {
		$selected = "torrents";
	}elseif (preg_match("/music/i", $script_name)) {
		$selected = "music";
	}elseif (preg_match("/offers/i", $script_name) OR preg_match("/offcomment/i", $script_name)) {
		$selected = "offers";
	}elseif (preg_match("/upload/i", $script_name)) {
		$selected = "upload";
	}elseif (preg_match("/subtitles/i", $script_name)) {
		$selected = "subtitles";
	}elseif (preg_match("/usercp/i", $script_name)) {
		$selected = "usercp";
	}elseif (preg_match("/topten/i", $script_name)) {
		$selected = "topten";
	}elseif (preg_match("/log/i", $script_name)) {
		$selected = "log";
	}elseif (preg_match("/rules/i", $script_name)) {
		$selected = "rules";
	}elseif (preg_match("/faq/i", $script_name)) {
		$selected = "faq";
	}elseif (preg_match("/staff/i", $script_name)) {
		$selected = "staff";
	}else
	$selected = "";
	print ("<div id=\"nav\"><ul id=\"mainmenu\" class=\"menu\">");
	print ("<li" . ($selected == "home" ? " class=\"selected\"" : "") . "><a href=\"index.php\">" . $lang_functions['text_home'] . "</a></li>");
	if ($enableextforum != 'yes')
	print ("<li" . ($selected == "forums" ? " class=\"selected\"" : "") . "><a href=\"forums.php\">".$lang_functions['text_forums']."</a></li>");
	else
	print ("<li" . ($selected == "forums" ? " class=\"selected\"" : "") . "><a href=\"" . $extforumurl."\" target=\"_blank\">".$lang_functions['text_forums']."</a></li>");
	print ("<li" . ($selected == "torrents" ? " class=\"selected\"" : "") . "><a href=\"torrents.php\">".$lang_functions['text_torrents']."</a></li>");
	if ($enablespecial == 'yes')
	print ("<li" . ($selected == "music" ? " class=\"selected\"" : "") . "><a href=\"music.php\">".$lang_functions['text_music']."</a></li>");
	if ($enableoffer == 'yes')
	print ("<li" . ($selected == "offers" ? " class=\"selected\"" : "") . "><a href=\"offers.php\">".$lang_functions['text_offers']."</a></li>");
	if ($enablerequest == 'yes')
	print ("<li" . ($selected == "requests" ? " class=\"selected\"" : "") . "><a href=\"viewrequests.php\">".$lang_functions['text_request']."</a></li>");
	print ("<li" . ($selected == "upload" ? " class=\"selected\"" : "") . "><a href=\"upload.php\">".$lang_functions['text_upload']."</a></li>");
	print ("<li" . ($selected == "subtitles" ? " class=\"selected\"" : "") . "><a href=\"subtitles.php\">".$lang_functions['text_subtitles']."</a></li>");
	print ("<li" . ($selected == "usercp" ? " class=\"selected\"" : "") . "><a href=\"usercp.php\">".$lang_functions['text_user_cp']."</a></li>");
	print ("<li" . ($selected == "topten" ? " class=\"selected\"" : "") . "><a href=\"topten.php\">".$lang_functions['text_top_ten']."</a></li>");
	print ("<li" . ($selected == "log" ? " class=\"selected\"" : "") . "><a href=\"log.php\">".$lang_functions['text_log']."</a></li>");
	print ("<li" . ($selected == "rules" ? " class=\"selected\"" : "") . "><a href=\"rules.php\">".$lang_functions['text_rules']."</a></li>");
	print ("<li" . ($selected == "faq" ? " class=\"selected\"" : "") . "><a href=\"faq.php\">".$lang_functions['text_faq']."</a></li>");
	print ("<li" . ($selected == "staff" ? " class=\"selected\"" : "") . "><a href=\"staff.php\">".$lang_functions['text_staff']."</a></li>");
	print ("</ul></div>");

	if ($CURUSER){
		if ($where_tweak == 'yes')
			$USERUPDATESET[] = "page = ".sqlesc($selected);
	}
}
function get_css_row() {
	global $CURUSER, $defcss, $Cache;
	static $rows;
	$cssid = $CURUSER ? $CURUSER["stylesheet"] : $defcss;
	if (!$rows && !$rows = $Cache->get_value('stylesheet_content')){
		$rows = array();
		$res = sql_query("SELECT * FROM stylesheets ORDER BY id ASC");
		while($row = mysql_fetch_array($res)) {
			$rows[$row['id']] = $row;
		}
		$Cache->cache_value('stylesheet_content', $rows, 95400);
	}
	return $rows[$cssid];
}
function get_css_uri($file = "")
{
	$cssRow = get_css_row();
	$ss_uri = $cssRow['uri'];
	if (!$ss_uri)
		$ss_uri = get_single_value("stylesheets","uri","WHERE id=".sqlesc($defcss));
	if ($file == "")
		return $ss_uri;
	else return $ss_uri.$file;
}

function get_font_css_uri(){
	global $CURUSER;
	if ($CURUSER['fontsize'] == 'large')
		$file = 'largefont.css';
	elseif ($CURUSER['fontsize'] == 'small')
		$file = 'smallfont.css';
	else $file = 'mediumfont.css';
	return "styles/".$file;
}

function get_style_addicode()
{
	$cssRow = get_css_row();
	return $cssRow['addicode'];
}

function get_cat_folder($cat = 101)
{
	static $catPath = array();
	if (!$catPath[$cat]) {
		global $CURUSER, $CURLANGDIR;
		$catrow = get_category_row($cat);
		$catmode = $catrow['catmodename'];
		$caticonrow = get_category_icon_row($CURUSER['caticon']);
		$catPath[$cat] = "category/".$catmode."/".$caticonrow['folder'] . ($caticonrow['multilang'] == 'yes' ? $CURLANGDIR."/" : "");
	}
	return $catPath[$cat];
}

function get_style_highlight()
{
	global $CURUSER;
	if ($CURUSER)
	{
		$ss_a = @mysql_fetch_array(@sql_query("select hltr from stylesheets where id=" . $CURUSER["stylesheet"]));
		if ($ss_a) $hltr = $ss_a["hltr"];
	}
	if (!$hltr)
	{
		$r = sql_query("SELECT hltr FROM stylesheets WHERE id=5") or die(mysql_error());
		$a = mysql_fetch_array($r) or die(mysql_error());
		$hltr = $a["hltr"];
	}
	return $hltr;
}

function stdhead($title = "", $msgalert = true, $script = "", $place = "")
{
	global $lang_functions;
	global $CURUSER, $CURLANGDIR, $USERUPDATESET, $iplog1, $oldip, $SITE_ONLINE, $FUNDS, $SITENAME, $SLOGAN, $logo_main, $BASEURL, $offlinemsg, $showversion,$enabledonation, $staffmem_class, $titlekeywords_tweak, $metakeywords_tweak, $metadescription_tweak, $cssdate_tweak, $deletenotransfertwo_account, $neverdelete_account, $iniupload_main;
	global $tstart;
	global $Cache;
	global $Advertisement;

	$Cache->setLanguage($CURLANGDIR);
	
	$Advertisement = new ADVERTISEMENT($CURUSER['id']);
	$cssupdatedate = $cssdate_tweak;
	// Variable for Start Time
	$tstart = getmicrotime(); // Start time

	//Insert old ip into iplog
	if ($CURUSER){
		if ($iplog1 == "yes") {
			if (($oldip != $CURUSER["ip"]) && $CURUSER["ip"])
			sql_query("INSERT INTO iplog (ip, userid, access) VALUES (" . sqlesc($CURUSER['ip']) . ", " . $CURUSER['id'] . ", '" . $CURUSER['last_access'] . "')");
		}
		$USERUPDATESET[] = "last_access = ".sqlesc(date("Y-m-d H:i:s"));
		$USERUPDATESET[] = "ip = ".sqlesc($CURUSER['ip']);
	}
	header("Content-Type: text/html; charset=utf-8; Cache-control:private");
	//header("Pragma: No-cache");
	if ($title == "")
	$title = $SITENAME;
	else
	$title = $SITENAME." :: " . htmlspecialchars($title);
	if ($titlekeywords_tweak)
		$title .= " ".htmlspecialchars($titlekeywords_tweak);
	$title .= $showversion;
	if ($SITE_ONLINE == "no") {
		if (get_user_class() < UC_ADMINISTRATOR) {
			die($lang_functions['std_site_down_for_maintenance']);
		}
		else
		{
			$offlinemsg = true;
		}
	}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<?php
if ($metakeywords_tweak){
?>
<meta name="keywords" content="<?php echo htmlspecialchars($metakeywords_tweak)?>" />
<?php
}
if ($metadescription_tweak){
?>
<meta name="description" content="<?php echo htmlspecialchars($metadescription_tweak)?>" />
<?php
}
?>
<meta name="generator" content="<?php echo PROJECTNAME?>" />
<?php
print(get_style_addicode());
$css_uri = get_css_uri();
$cssupdatedate=($cssupdatedate ? "?".htmlspecialchars($cssupdatedate) : "");
?>
<title><?php echo $title?></title>
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
<link rel="search" type="application/opensearchdescription+xml" title="<?php echo $SITENAME?> Torrents" href="opensearch.php" />
<link rel="stylesheet" href="<?php echo get_font_css_uri().$cssupdatedate?>" type="text/css" />
<link rel="stylesheet" href="styles/sprites.css<?php echo $cssupdatedate?>" type="text/css" />
<link rel="stylesheet" href="<?php echo get_forum_pic_folder()."/forumsprites.css".$cssupdatedate?>" type="text/css" />
<link rel="stylesheet" href="<?php echo $css_uri."theme.css".$cssupdatedate?>" type="text/css" />
<link rel="stylesheet" href="<?php echo $css_uri."DomTT.css".$cssupdatedate?>" type="text/css" />
<link rel="stylesheet" href="styles/curtain_imageresizer.css<?php echo $cssupdatedate?>" type="text/css" />
<?php
if ($CURUSER){
	$caticonrow = get_category_icon_row($CURUSER['caticon']);
	if($caticonrow['cssfile']){
?>
<link rel="stylesheet" href="<?php echo htmlspecialchars($caticonrow['cssfile']).$cssupdatedate?>" type="text/css" />
<?php
	}
}
?>
<link rel="alternate" type="application/rss+xml" title="Latest Torrents" href="torrentrss.php" />
<script type="text/javascript" src="curtain_imageresizer.js<?php echo $cssupdatedate?>"></script>
<script type="text/javascript" src="ajaxbasic.js<?php echo $cssupdatedate?>"></script>
<script type="text/javascript" src="common.js<?php echo $cssupdatedate?>"></script>
<script type="text/javascript" src="domLib.js<?php echo $cssupdatedate?>"></script>
<script type="text/javascript" src="domTT.js<?php echo $cssupdatedate?>"></script>
<script type="text/javascript" src="domTT_drag.js<?php echo $cssupdatedate?>"></script>
<script type="text/javascript" src="fadomatic.js<?php echo $cssupdatedate?>"></script>
</head>
<body>
<table class="head" cellspacing="0" cellpadding="0" align="center">
	<tr>
		<td class="clear">
<?php
if ($logo_main == "")
{
?>
			<div class="logo"><?php echo htmlspecialchars($SITENAME)?></div>
			<div class="slogan"><?php echo htmlspecialchars($SLOGAN)?></div>
<?php
}
else
{
?>
			<div class="logo_img"><img src="<?php echo $logo_main?>" alt="<?php echo htmlspecialchars($SITENAME)?>" title="<?php echo htmlspecialchars($SITENAME)?> - <?php echo htmlspecialchars($SLOGAN)?>" /></div>
<?php
}
?>
		</td>
		<td class="clear nowrap" align="right" valign="middle">
<?php if ($Advertisement->enable_ad()){
		$headerad=$Advertisement->get_ad('header');
		if ($headerad){
			echo "<span id=\"ad_header\">".$headerad[0]."</span>";
		}
}
if ($enabledonation == 'yes'){?>
			<a href="donate.php"><img src="<?php echo get_forum_pic_folder()?>/donate.gif" alt="Make a donation" style="margin-left: 5px; margin-top: 50px;" /></a>
<?php
}
?>
		</td>
	</tr>
</table>

<table class="mainouter" width="982" cellspacing="0" cellpadding="5" align="center">
	<tr><td id="nav_block" class="text" align="center">
<?php if (!$CURUSER) { ?>
			<a href="login.php"><font class="big"><b><?php echo $lang_functions['text_login'] ?></b></font></a> / <a href="signup.php"><font class="big"><b><?php echo $lang_functions['text_signup'] ?></b></font></a>
<?php } 
else {
	begin_main_frame();
	menu ();
	end_main_frame();

	$datum = getdate();
	$datum["hours"] = sprintf("%02.0f", $datum["hours"]);
	$datum["minutes"] = sprintf("%02.0f", $datum["minutes"]);
	$ratio = get_ratio($CURUSER['id']);

	//// check every 15 minutes //////////////////
	$messages = $Cache->get_value('user_'.$CURUSER["id"].'_inbox_count');
	if ($messages == ""){
		$messages = get_row_count("messages", "WHERE receiver=" . sqlesc($CURUSER["id"]) . " AND location<>0");
		$Cache->cache_value('user_'.$CURUSER["id"].'_inbox_count', $messages, 900);
	}
	$outmessages = $Cache->get_value('user_'.$CURUSER["id"].'_outbox_count');
	if ($outmessages == ""){
		$outmessages = get_row_count("messages","WHERE sender=" . sqlesc($CURUSER["id"]) . " AND saved='yes'");
		$Cache->cache_value('user_'.$CURUSER["id"].'_outbox_count', $outmessages, 900);
	}
	if (!$connect = $Cache->get_value('user_'.$CURUSER["id"].'_connect')){
		$res3 = sql_query("SELECT connectable FROM peers WHERE userid=" . sqlesc($CURUSER["id"]) . " LIMIT 1");
		if($row = mysql_fetch_row($res3))
			$connect = $row[0];
		else $connect = 'unknown';
		$Cache->cache_value('user_'.$CURUSER["id"].'_connect', $connect, 900);
	}

	if($connect == "yes")
		$connectable = "<b><font color=\"green\">".$lang_functions['text_yes']."</font></b>";
	elseif ($connect == 'no')
		$connectable = "<a href=\"faq.php#id21\"><b><font color=\"red\">".$lang_functions['text_no']."</font></b></a>";
	else
		$connectable = $lang_functions['text_unknown'];

	//// check every 60 seconds //////////////////
	$activeseed = $Cache->get_value('user_'.$CURUSER["id"].'_active_seed_count');
	if ($activeseed == ""){
		$activeseed = get_row_count("peers","WHERE userid=" . sqlesc($CURUSER["id"]) . " AND seeder='yes'");
		$Cache->cache_value('user_'.$CURUSER["id"].'_active_seed_count', $activeseed, 60);
	}
	$activeleech = $Cache->get_value('user_'.$CURUSER["id"].'_active_leech_count');
	if ($activeleech == ""){
		$activeleech = get_row_count("peers","WHERE userid=" . sqlesc($CURUSER["id"]) . " AND seeder='no'");
		$Cache->cache_value('user_'.$CURUSER["id"].'_active_leech_count', $activeleech, 60);
	}
	$unread = $Cache->get_value('user_'.$CURUSER["id"].'_unread_message_count');
	if ($unread == ""){
		$unread = get_row_count("messages","WHERE receiver=" . sqlesc($CURUSER["id"]) . " AND unread='yes'");
		$Cache->cache_value('user_'.$CURUSER["id"].'_unread_message_count', $unread, 60);
	}
	
	$inboxpic = "<img class=\"".($unread ? "inboxnew" : "inbox")."\" src=\"pic/trans.gif\" alt=\"inbox\" title=\"".($unread ? $lang_functions['title_inbox_new_messages'] : $lang_functions['title_inbox_no_new_messages'])."\" />";
?>

<table id="info_block" cellpadding="4" cellspacing="0" border="0" width="100%"><tr>
	<td><table width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
		<td class="bottom" align="left"><span class="medium"><?php echo $lang_functions['text_welcome_back'] ?>, <?php echo get_username($CURUSER['id'])?>  [<a href="logout.php"><?php echo $lang_functions['text_logout'] ?></a>]<?php if (get_user_class() >= UC_MODERATOR) { ?> [<a href="staffpanel.php"><?php echo $lang_functions['text_staff_panel'] ?></a>] <?php }?> <?php if (get_user_class() >= UC_SYSOP) { ?> [<a href="settings.php"><?php echo $lang_functions['text_site_settings'] ?></a>]<?php } ?> [<a href="torrents.php?inclbookmarked=1&amp;allsec=1&amp;incldead=0"><?php echo $lang_functions['text_bookmarks'] ?></a>] <font class = 'color_bonus'><?php echo $lang_functions['text_bonus'] ?></font>[<a href="mybonus.php"><?php echo $lang_functions['text_use'] ?></a>]: <?php echo number_format($CURUSER['seedbonus'], 1)?> <font class = 'color_invite'><?php echo $lang_functions['text_invite'] ?></font>[<a href="invite.php?id=<?php echo $CURUSER['id']?>"><?php echo $lang_functions['text_send'] ?></a>]: <?php echo $CURUSER['invites']?><br />

	<font class="color_ratio"><?php echo $lang_functions['text_ratio'] ?></font> <?php echo $ratio?>  <font class='color_uploaded'><?php echo $lang_functions['text_uploaded'] ?></font> <?php echo mksize($CURUSER['uploaded'])?><font class='color_downloaded'> <?php echo $lang_functions['text_downloaded'] ?></font> <?php echo mksize($CURUSER['downloaded'])?>  <font class='color_active'><?php echo $lang_functions['text_active_torrents'] ?></font> <img class="arrowup" alt="Torrents seeding" title="<?php echo $lang_functions['title_torrents_seeding'] ?>" src="pic/trans.gif" /><?php echo $activeseed?>  <img class="arrowdown" alt="Torrents leeching" title="<?php echo $lang_functions['title_torrents_leeching'] ?>" src="pic/trans.gif" /><?php echo $activeleech?>&nbsp;&nbsp;<font class='color_connectable'><?php echo $lang_functions['text_connectable'] ?></font><?php echo $connectable?> <?php echo maxslots();?></span></td>

	<td class="bottom" align="right"><span class="medium"><?php echo $lang_functions['text_the_time_is_now'] ?><?php echo $datum[hours].":".$datum[minutes]?><br />

<?php
	if (get_user_class() >= $staffmem_class){
	$totalreports = $Cache->get_value('staff_report_count');
	if ($totalreports == ""){
		$totalreports = get_row_count("reports");
		$Cache->cache_value('staff_report_count', $totalreports, 900);
	}
	$totalsm = $Cache->get_value('staff_message_count');
	if ($totalsm == ""){
		$totalsm = get_row_count("staffmessages");
		$Cache->cache_value('staff_message_count', $totalsm, 900);
	}
	$totalcheaters = $Cache->get_value('staff_cheater_count');
	if ($totalcheaters == ""){
		$totalcheaters = get_row_count("cheaters");
		$Cache->cache_value('staff_cheater_count', $totalcheaters, 900);
	}
	print("<a href=\"cheaterbox.php\"><img class=\"cheaterbox\" alt=\"cheaterbox\" title=\"".$lang_functions['title_cheaterbox']."\" src=\"pic/trans.gif\" />  </a>".$totalcheaters."  <a href=\"reports.php\"><img class=\"reportbox\" alt=\"reportbox\" title=\"".$lang_functions['title_reportbox']."\" src=\"pic/trans.gif\" />  </a>".$totalreports."  <a href=\"staffbox.php\"><img class=\"staffbox\" alt=\"staffbox\" title=\"".$lang_functions['title_staffbox']."\" src=\"pic/trans.gif\" />  </a>".$totalsm."  ");
	}

	print("<a href=\"messages.php\">".$inboxpic."</a> ".($messages ? $messages." (".$unread.$lang_functions['text_message_new'].")" : "0"));
	print("  <a href=\"messages.php?action=viewmailbox&amp;box=-1\"><img class=\"sentbox\" alt=\"sentbox\" title=\"".$lang_functions['title_sentbox']."\" src=\"pic/trans.gif\" /></a> ".($outmessages ? $outmessages : "0"));
	print(" <a href=\"friends.php\"><img class=\"buddylist\" alt=\"Buddylist\" title=\"".$lang_functions['title_buddylist']."\" src=\"pic/trans.gif\" /></a>");
	print(" <a href=\"getrss.php\"><img class=\"rss\" alt=\"RSS\" title=\"".$lang_functions['title_get_rss']."\" src=\"pic/trans.gif\" /></a>");
?>

	</span></td>
	</tr></table></td>
</tr></table>

</td></tr>

<tr><td id="outer" align="center" class="outer" style="padding-top: 20px; padding-bottom: 20px">
<?php
	if ($Advertisement->enable_ad()){
			$belownavad=$Advertisement->get_ad('belownav');
			if ($belownavad)
			echo "<div align=\"center\" style=\"margin-bottom: 10px\" id=\"ad_belownav\">".$belownavad[0]."</div>";
	}
if ($msgalert)
{
	function msgalert($url, $text, $bgcolor = "red")
	{
		print("<p><table border=\"0\" cellspacing=\"0\" cellpadding=\"10\"><tr><td style='border: none; padding: 10px; background: ".$bgcolor."'>\n");
		print("<b><a href=\"".$url."\"><font color=\"white\">".$text."</font></a></b>");
		print("</td></tr></table></p><br />");
	}
	if($CURUSER['leechwarn'] == 'yes')
	{
		$kicktimeout = gettime($CURUSER['leechwarnuntil'], false, false, true);
		$text = $lang_functions['text_please_improve_ratio_within'].$kicktimeout.$lang_functions['text_or_you_will_be_banned'];
		msgalert("faq.php#id17", $text, "orange");
	}
	if($deletenotransfertwo_account) //inactive account deletion notice
	{
		if ($CURUSER['downloaded'] == 0 && ($CURUSER['uploaded'] == 0 || $CURUSER['uploaded'] == $iniupload_main))
		{
			$neverdelete_account = ($neverdelete_account <= UC_VIP ? $neverdelete_account : UC_VIP);
			if (get_user_class() < $neverdelete_account)
			{
				$secs = $deletenotransfertwo_account*24*60*60;
				$addedtime = strtotime($CURUSER['added']);
				if (TIMENOW > $addedtime+($secs/3)) // start notification if one third of the time has passed
				{
					$kicktimeout = gettime(date("Y-m-d H:i:s", $addedtime+$secs), false, false, true);
					$text = $lang_functions['text_please_download_something_within'].$kicktimeout.$lang_functions['text_inactive_account_be_deleted'];
					msgalert("rules.php", $text, "gray");
				}
			}
		}
	}
	if($CURUSER['showclienterror'] == 'yes')
	{
		$text = $lang_functions['text_banned_client_warning'];
		msgalert("faq.php#id29", $text, "black");
	}
	if ($unread)
	{
		$text = $lang_functions['text_you_have'].$unread.$lang_functions['text_new_message'] . add_s($unread) . $lang_functions['text_click_here_to_read'];
		msgalert("messages.php",$text, "red");
	}
/*
	$pending_invitee = $Cache->get_value('user_'.$CURUSER["id"].'_pending_invitee_count');
	if ($pending_invitee == ""){
		$pending_invitee = get_row_count("users","WHERE status = 'pending' AND invited_by = ".sqlesc($CURUSER[id]));
		$Cache->cache_value('user_'.$CURUSER["id"].'_pending_invitee_count', $pending_invitee, 900);
	}
	if ($pending_invitee > 0)
	{
		$text = $lang_functions['text_your_friends'].add_s($pending_invitee).is_or_are($pending_invitee).$lang_functions['text_awaiting_confirmation'];
		msgalert("invite.php?id=".$CURUSER[id],$text, "red");
	}*/
	$settings_script_name = $_SERVER["SCRIPT_FILENAME"];
	if (!preg_match("/index/i", $settings_script_name))
	{
		$new_news = $Cache->get_value('user_'.$CURUSER["id"].'_unread_news_count');
		if ($new_news == ""){
			$new_news = get_row_count("news","WHERE notify = 'yes' AND added > ".sqlesc($CURUSER['last_home']));
			$Cache->cache_value('user_'.$CURUSER["id"].'_unread_news_count', $new_news, 300);
		}
		if ($new_news > 0)
		{
			$text = $lang_functions['text_there_is'].is_or_are($new_news).$new_news.$lang_functions['text_new_news'];
			msgalert("index.php",$text, "green");
		}
	}

	if (get_user_class() >= $staffmem_class)
	{
		$numreports = $Cache->get_value('staff_new_report_count');
		if ($numreports == ""){
			$numreports = get_row_count("reports","WHERE dealtwith=0");
			$Cache->cache_value('staff_new_report_count', $numreports, 900);
		}
		if ($numreports){
			$text = $lang_functions['text_there_is'].is_or_are($numreports).$numreports.$lang_functions['text_new_report'] .add_s($numreports);
			msgalert("reports.php",$text, "blue");
		}
		$nummessages = $Cache->get_value('staff_new_message_count');
		if ($nummessages == ""){
			$nummessages = get_row_count("staffmessages","WHERE answered='no'");
			$Cache->cache_value('staff_new_message_count', $nummessages, 900);
		}
		if ($nummessages > 0) {
			$text = $lang_functions['text_there_is'].is_or_are($nummessages).$nummessages.$lang_functions['text_new_staff_message'] . add_s($nummessages);
			msgalert("staffbox.php",$text, "blue");
		}
		$numcheaters = $Cache->get_value('staff_new_cheater_count');
		if ($numcheaters == ""){
			$numcheaters = get_row_count("cheaters","WHERE dealtwith=0");
			$Cache->cache_value('staff_new_cheater_count', $numcheaters, 900);
		}
		if ($numcheaters){
			$text = $lang_functions['text_there_is'].is_or_are($numcheaters).$numcheaters.$lang_functions['text_new_suspected_cheater'] .add_s($numcheaters);
			msgalert("cheaterbox.php",$text, "blue");
		}
	}
}
		if ($offlinemsg)
		{
			print("<p><table width=\"737\" border=\"1\" cellspacing=\"0\" cellpadding=\"10\"><tr><td style='padding: 10px; background: red' class=\"text\" align=\"center\">\n");
			print("<font color=\"white\">".$lang_functions['text_website_offline_warning']."</font>");
			print("</td></tr></table></p><br />\n");
		}
}
}


function stdfoot() {
	global $SITENAME,$BASEURL,$Cache,$datefounded,$tstart,$icplicense_main,$add_key_shortcut,$query_name, $USERUPDATESET, $CURUSER, $enablesqldebug_tweak, $sqldebug_tweak, $Advertisement, $analyticscode_tweak;
	print("</td></tr></table>");
	print("<div id=\"footer\">");
	if ($Advertisement->enable_ad()){
			$footerad=$Advertisement->get_ad('footer');
			if ($footerad)
			echo "<div align=\"center\" style=\"margin-top: 10px\" id=\"ad_footer\">".$footerad[0]."</div>";
	}
	print("<div style=\"margin-top: 10px; margin-bottom: 30px;\" align=\"center\">");
	if ($CURUSER){
		sql_query("UPDATE users SET " . join(",", $USERUPDATESET) . " WHERE id = ".$CURUSER['id']);
	}
	// Variables for End Time
	$tend = getmicrotime();
	$totaltime = ($tend - $tstart);
	$year = substr($datefounded, 0, 4);
	$yearfounded = ($year ? $year : 2007);
	print(" (c) "." <a href=\"" . get_protocol_prefix() . $BASEURL."\" target=\"_self\">".$SITENAME."</a> ".($icplicense_main ? " ".$icplicense_main." " : "").(date("Y") != $yearfounded ? $yearfounded."-" : "").date("Y")." ".VERSION."<br /><br />");
	printf ("[page created in <b> %f </b> sec", $totaltime);
	print (" with <b>".count($query_name)."</b> db queries, <b>".$Cache->getCacheReadTimes()."</b> reads and <b>".$Cache->getCacheWriteTimes()."</b> writes of memcached and <b>".mksize(memory_get_usage())."</b> ram]");
	print ("</div>\n");
	if ($enablesqldebug_tweak == 'yes' && get_user_class() >= $sqldebug_tweak) {
		print("<div id=\"sql_debug\">SQL query list: <ul>");
		foreach($query_name as $query) {
			print("<li>".htmlspecialchars($query)."</li>");
		}
		print("</ul>");
		print("Memcached key read: <ul>");
		foreach($Cache->getKeyHits('read') as $keyName => $hits) {
			print("<li>".htmlspecialchars($keyName)." : ".$hits."</li>");
		}
		print("</ul>");
		print("Memcached key write: <ul>");
		foreach($Cache->getKeyHits('write') as $keyName => $hits) {
			print("<li>".htmlspecialchars($keyName)." : ".$hits."</li>");
		}
		print("</ul>");
		print("</div>");
	}
	print ("<div style=\"display: none;\" id=\"lightbox\" class=\"lightbox\"></div><div style=\"display: none;\" id=\"curtain\" class=\"curtain\"></div>");
	if ($add_key_shortcut != "")
	print($add_key_shortcut);
	print("</div>");
	if ($analyticscode_tweak)
		print("\n".$analyticscode_tweak."\n");
	print("</body></html>");

	//echo replacePngTags(ob_get_clean());

	unset($_SESSION['queries']);
}

function genbark($x,$y) {
	stdhead($y);
	print("<h1>" . htmlspecialchars($y) . "</h1>\n");
	print("<p>" . htmlspecialchars($x) . "</p>\n");
	stdfoot();
	exit();
}

function mksecret($len = 20) {
	$ret = "";
	for ($i = 0; $i < $len; $i++)
	$ret .= chr(mt_rand(100, 120));
	return $ret;
}

function httperr($code = 404) {
	header("HTTP/1.0 404 Not found");
	print("<h1>Not Found</h1>\n");
	exit();
}

function logincookie($id, $passhash, $updatedb = 1, $expires = 0x7fffffff, $securelogin=false, $ssl=false, $trackerssl=false)
{
	if ($expires != 0x7fffffff)
	$expires = time()+$expires;

	setcookie("c_secure_uid", base64($id), $expires, "/");
	setcookie("c_secure_pass", $passhash, $expires, "/");
	if($ssl)
	setcookie("c_secure_ssl", base64("yeah"), $expires, "/");
	else
	setcookie("c_secure_ssl", base64("nope"), $expires, "/");

	if($trackerssl)
	setcookie("c_secure_tracker_ssl", base64("yeah"), $expires, "/");
	else
	setcookie("c_secure_tracker_ssl", base64("nope"), $expires, "/");

	if ($securelogin)
	setcookie("c_secure_login", base64("yeah"), $expires, "/");
	else
	setcookie("c_secure_login", base64("nope"), $expires, "/");


	if ($updatedb)
	sql_query("UPDATE users SET last_login = NOW(), lang=" . sqlesc(get_langid_from_langcookie()) . " WHERE id = ".sqlesc($id));
}

function set_langfolder_cookie($folder, $expires = 0x7fffffff)
{
	if ($expires != 0x7fffffff)
	$expires = time()+$expires;

	setcookie("c_lang_folder", $folder, $expires, "/");
}

function get_protocol_prefix()
{
	global $securelogin;
	if ($securelogin == "yes") {
		return "https://";
	} elseif ($securelogin == "no") {
		return "http://";
	} else {
		if (!isset($_COOKIE["c_secure_ssl"])) {
			return "http://";
		} else {
			return base64_decode($_COOKIE["c_secure_ssl"]) == "yeah" ? "https://" : "http://";
		}
	}
}

function get_langid_from_langcookie()
{
	global $CURLANGDIR;
	$row = mysql_fetch_array(sql_query("SELECT id FROM language WHERE site_lang = 1 AND site_lang_folder = " . sqlesc($CURLANGDIR) . "ORDER BY id ASC")) or sqlerr(__FILE__, __LINE__);
	return $row['id'];
}

function make_folder($pre, $folder_name)
{
	$path = $pre . $folder_name;
	if(!file_exists($path))
	mkdir($path,0777,true);
	return $path;
}

function logoutcookie() {
	setcookie("c_secure_uid", "", 0x7fffffff, "/");
	setcookie("c_secure_pass", "", 0x7fffffff, "/");
// setcookie("c_secure_ssl", "", 0x7fffffff, "/");
	setcookie("c_secure_tracker_ssl", "", 0x7fffffff, "/");
	setcookie("c_secure_login", "", 0x7fffffff, "/");
//	setcookie("c_lang_folder", "", 0x7fffffff, "/");
}

function base64 ($string, $encode=true) {
	if ($encode)
	return base64_encode($string);
	else
	return base64_decode($string);
}

function loggedinorreturn($mainpage = false) {
	global $CURUSER,$BASEURL;
	if (!$CURUSER) {
		if ($mainpage)
		header("Location: " . get_protocol_prefix() . "$BASEURL/login.php");
		else {
			$to = $_SERVER["REQUEST_URI"];
			$to = basename($to);
			header("Location: " . get_protocol_prefix() . "$BASEURL/login.php?returnto=" . rawurlencode($to));
		}
		exit();
	}
}

function deletetorrent($id) {
	global $torrent_dir;
	sql_query("DELETE FROM torrents WHERE id = ".mysql_real_escape_string($id));
	sql_query("DELETE FROM snatched WHERE torrentid = ".mysql_real_escape_string($id));
	foreach(array("peers", "files", "comments") as $x) {
		sql_query("DELETE FROM $x WHERE torrent = ".mysql_real_escape_string($id));
	}
	unlink("$torrent_dir/$id.torrent");
}

function pager($rpp, $count, $href, $opts = array(), $pagename = "page") {
	global $lang_functions,$add_key_shortcut;
	$pages = ceil($count / $rpp);

	if (!$opts["lastpagedefault"])
	$pagedefault = 0;
	else {
		$pagedefault = floor(($count - 1) / $rpp);
		if ($pagedefault < 0)
		$pagedefault = 0;
	}

	if (isset($_GET[$pagename])) {
		$page = 0 + $_GET[$pagename];
		if ($page < 0)
		$page = $pagedefault;
	}
	else
	$page = $pagedefault;

	$pager = "";
	$mp = $pages - 1;

	//Opera (Presto) doesn't know about event.altKey
	$is_presto = strpos($_SERVER['HTTP_USER_AGENT'], 'Presto');
	$as = "<b title=\"".($is_presto ? $lang_functions['text_shift_pageup_shortcut'] : $lang_functions['text_alt_pageup_shortcut'])."\">&lt;&lt;&nbsp;".$lang_functions['text_prev']."</b>";
	if ($page >= 1) {
		$pager .= "<a href=\"".htmlspecialchars($href.$pagename."=" . ($page - 1) ). "\">";
		$pager .= $as;
		$pager .= "</a>";
	}
	else
	$pager .= "<font class=\"gray\">".$as."</font>";
	$pager .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
	$as = "<b title=\"".($is_presto ? $lang_functions['text_shift_pagedown_shortcut'] : $lang_functions['text_alt_pagedown_shortcut'])."\">".$lang_functions['text_next']."&nbsp;&gt;&gt;</b>";
	if ($page < $mp && $mp >= 0) {
		$pager .= "<a href=\"".htmlspecialchars($href.$pagename."=" . ($page + 1) ). "\">";
		$pager .= $as;
		$pager .= "</a>";
	}
	else
	$pager .= "<font class=\"gray\">".$as."</font>";

	if ($count) {
		$pagerarr = array();
		$dotted = 0;
		$dotspace = 3;
		$dotend = $pages - $dotspace;
		$curdotend = $page - $dotspace;
		$curdotstart = $page + $dotspace;
		for ($i = 0; $i < $pages; $i++) {
			if (($i >= $dotspace && $i <= $curdotend) || ($i >= $curdotstart && $i < $dotend)) {
				if (!$dotted)
				$pagerarr[] = "...";
				$dotted = 1;
				continue;
			}
			$dotted = 0;
			$start = $i * $rpp + 1;
			$end = $start + $rpp - 1;
			if ($end > $count)
			$end = $count;
			$text = "$start&nbsp;-&nbsp;$end";
			if ($i != $page)
			$pagerarr[] = "<a href=\"".htmlspecialchars($href.$pagename."=".$i)."\"><b>$text</b></a>";
			else
			$pagerarr[] = "<font class=\"gray\"><b>$text</b></font>";
		}
		$pagerstr = join(" | ", $pagerarr);
		$pagertop = "<p align=\"center\">$pager<br />$pagerstr</p>\n";
		$pagerbottom = "<p align=\"center\">$pagerstr<br />$pager</p>\n";
	}
	else {
		$pagertop = "<p align=\"center\">$pager</p>\n";
		$pagerbottom = $pagertop;
	}

	$start = $page * $rpp;
	$add_key_shortcut = key_shortcut($page,$pages-1);
	return array($pagertop, $pagerbottom, "LIMIT $start,$rpp");
}

function commenttable($rows, $type, $parent_id, $review = false)
{
	global $lang_functions;
	global $CURUSER, $commanage_class;
	global $Advertisement;
	begin_main_frame();
	begin_frame();

	$count = 0;
	if ($Advertisement->enable_ad())
		$commentad = $Advertisement->get_ad('comment');
	foreach ($rows as $row)
	{
		$userRow = get_user_row($row['user']);
		if ($count>=1)
		{
			if ($Advertisement->enable_ad()){
				if ($commentad[$count-1])
				echo "<div align=\"center\" style=\"margin-top: 10px\" id=\"ad_comment_".$count."\">".$commentad[$count-1]."</div>";
			}
		}
		print("<div style=\"margin-top: 8pt; margin-bottom: 8pt;\"><table id=\"cid".$row["id"]."\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" width=\"100%\"><tr><td class=\"embedded\" width=\"99%\">#" . $row["id"] . "&nbsp;&nbsp;<font color=\"gray\">".$lang_functions['text_by']."</font>");
		print(get_username($row["user"],false,true,true,false,false,true));
		print("&nbsp;&nbsp;<font color=\"gray\">".$lang_functions['text_at']."</font>".gettime($row["added"]).
		($row["editedby"] && get_user_class() >= $commanage_class ? " - [<a href=\"comment.php?action=vieworiginal&amp;cid=".$row[id]."&amp;type=".$type."\">".$lang_functions['text_view_original']."</a>]" : "") . "</td><td class=\"embedded nowrap\" width=\"1%\"><a href=\"#top\"><img class=\"top\" src=\"pic/trans.gif\" alt=\"Top\" title=\"Top\" /></a>&nbsp;&nbsp;</td></tr></table></div>");
		$avatar = ($CURUSER["avatars"] == "yes" ? htmlspecialchars(trim($userRow["avatar"])) : "");
		if (!$avatar)
			$avatar = "pic/default_avatar.png";
		$text = format_comment($row["text"]);
		$text_editby = "";
		if ($row["editedby"]){
			$lastedittime = gettime($row['editdate'],true,false);
			$text_editby = "<br /><p><font class=\"small\">".$lang_functions['text_last_edited_by'].get_username($row['editedby']).$lang_functions['text_edited_at'].$lastedittime."</font></p>\n";
		}

		print("<table class=\"main\" width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"5\">\n");
		$secs = 900;
		$dt = sqlesc(date("Y-m-d H:i:s",(TIMENOW - $secs))); // calculate date.
		print("<tr>\n");
		print("<td class=\"rowfollow\" width=\"150\" valign=\"top\" style=\"padding: 0px;\">".return_avatar_image($avatar)."</td>\n");
		print("<td class=\"rowfollow\" valign=\"top\"><br />".$text.$text_editby."</td>\n");
		print("</tr>\n");
		$actionbar = "<a href=\"comment.php?action=add&amp;sub=quote&amp;cid=".$row[id]."&amp;pid=".$parent_id."&amp;type=".$type."\"><img class=\"f_quote\" src=\"pic/trans.gif\" alt=\"Quote\" title=\"".$lang_functions['title_reply_with_quote']."\" /></a>".
		"<a href=\"comment.php?action=add&amp;pid=".$parent_id."&amp;type=".$type."\"><img class=\"f_reply\" src=\"pic/trans.gif\" alt=\"Add Reply\" title=\"".$lang_functions['title_add_reply']."\" /></a>".(get_user_class() >= $commanage_class ? "<a href=\"comment.php?action=delete&amp;cid=".$row[id]."&amp;type=".$type."\"><img class=\"f_delete\" src=\"pic/trans.gif\" alt=\"Delete\" title=\"".$lang_functions['title_delete']."\" /></a>" : "").($row["user"] == $CURUSER["id"] || get_user_class() >= $commanage_class ? "<a href=\"comment.php?action=edit&amp;cid=".$row[id]."&amp;type=".$type."\"><img class=\"f_edit\" src=\"pic/trans.gif\" alt=\"Edit\" title=\"".$lang_functions['title_edit']."\" />"."</a>" : "");
		print("<tr><td class=\"toolbox\"> ".("'".$userRow['last_access']."'"> $dt ? "<img class=\"f_online\" src=\"pic/trans.gif\" alt=\"Online\" title=\"".$lang_functions['title_online']."\" />":"<img class=\"f_offline\" src=\"pic/trans.gif\" alt=\"Offline\" title=\"".$lang_functions['title_offline']."\" />" )."<a href=\"sendmessage.php?receiver=".htmlspecialchars(trim($row["user"]))."\"><img class=\"f_pm\" src=\"pic/trans.gif\" alt=\"PM\" title=\"".$lang_functions['title_send_message_to'].htmlspecialchars($userRow["username"])."\" /></a><a href=\"report.php?commentid=".htmlspecialchars(trim($row["id"]))."\"><img class=\"f_report\" src=\"pic/trans.gif\" alt=\"Report\" title=\"".$lang_functions['title_report_this_comment']."\" /></a></td><td class=\"toolbox\" align=\"right\">".$actionbar."</td>");

		print("</tr></table>\n");
		$count++;
	}
	end_frame();
	end_main_frame();
}

function searchfield($s) {
	return preg_replace(array('/[^a-z0-9]/si', '/^\s*/s', '/\s*$/s', '/\s+/s'), array(" ", "", "", " "), $s);
}

function genrelist($catmode = 1) {
	global $Cache;
	if (!$ret = $Cache->get_value('category_list_mode_'.$catmode)){
		$ret = array();
		$res = sql_query("SELECT id, mode, name, image FROM categories WHERE mode = ".sqlesc($catmode)." ORDER BY sort_index, id");
		while ($row = mysql_fetch_array($res))
			$ret[] = $row;
		$Cache->cache_value('category_list_mode_'.$catmode, $ret, 152800);
	}
	return $ret;
}

function searchbox_item_list($table = "sources"){
	global $Cache;
	if (!$ret = $Cache->get_value($table.'_list')){
		$ret = array();
		$res = sql_query("SELECT * FROM ".$table." ORDER BY sort_index, id");
		while ($row = mysql_fetch_array($res))
			$ret[] = $row;
		$Cache->cache_value($table.'_list', $ret, 152800);
	}
	return $ret;
}

function langlist($type) {
	global $Cache;
	if (!$ret = $Cache->get_value($type.'_lang_list')){
		$ret = array();
		$res = sql_query("SELECT id, lang_name, flagpic, site_lang_folder FROM language WHERE ". $type ."=1 ORDER BY site_lang DESC, id ASC");
		while ($row = mysql_fetch_array($res))
			$ret[] = $row;
		$Cache->cache_value($type.'_lang_list', $ret, 152800);
	}
	return $ret;
}

function linkcolor($num) {
	if (!$num)
	return "red";
	//    if ($num == 1)
	//        return "yellow";
	return "green";
}

function writecomment($userid, $comment) {
	$res = sql_query("SELECT modcomment FROM users WHERE id = '$userid'") or sqlerr(__FILE__, __LINE__);
	$arr = mysql_fetch_assoc($res);

	$modcomment = date("d-m-Y") . " - " . $comment . "" . ($arr[modcomment] != "" ? "\n\n" : "") . "$arr[modcomment]";
	$modcom = sqlesc($modcomment);

	return sql_query("UPDATE users SET modcomment = $modcom WHERE id = '$userid'") or sqlerr(__FILE__, __LINE__);
}

function return_torrent_bookmark_array($userid)
{
	global $Cache;
	static $ret;
	if (!$ret){
		if (!$ret = $Cache->get_value('user_'.$userid.'_bookmark_array')){
			$ret = array();
			$res = sql_query("SELECT * FROM bookmarks WHERE userid=" . sqlesc($userid));
			if (mysql_num_rows($res) != 0){
				while ($row = mysql_fetch_array($res))
					$ret[] = $row['torrentid'];
				$Cache->cache_value('user_'.$userid.'_bookmark_array', $ret, 132800);
			} else {
				$Cache->cache_value('user_'.$userid.'_bookmark_array', array(0), 132800);
			}
		}
	}
	return $ret;
}
function get_torrent_bookmark_state($userid, $torrentid, $text = false)
{
	global $lang_functions;
	$userid = 0 + $userid;
	$torrentid = 0 + $torrentid;
	$ret = array();
	$ret = return_torrent_bookmark_array($userid);
	if (!count($ret) || !in_array($torrentid, $ret, false)) // already bookmarked
		$act = ($text == true ?  $lang_functions['title_bookmark_torrent']  : "<img class=\"delbookmark\" src=\"pic/trans.gif\" alt=\"Unbookmarked\" title=\"".$lang_functions['title_bookmark_torrent']."\" />");
	else
		$act = ($text == true ? $lang_functions['title_delbookmark_torrent'] : "<img class=\"bookmark\" src=\"pic/trans.gif\" alt=\"Bookmarked\" title=\"".$lang_functions['title_delbookmark_torrent']."\" />");
	return $act;
}

function torrenttable($res, $variant = "torrent") {
	global $Cache;
	global $lang_functions;
	global $CURUSER, $waitsystem;
	global $showextinfo;
	global $torrentmanage_class, $smalldescription_main, $enabletooltip_tweak;
	global $CURLANGDIR;

	if ($variant == "torrent"){
		$last_browse = $CURUSER['last_browse'];
		$sectiontype = $browsecatmode;
	}
	elseif($variant == "music"){
		$last_browse = $CURUSER['last_music'];
		$sectiontype = $specialcatmode;
	}
	else{
		$last_browse = $CURUSER['last_browse'];
		$sectiontype = "";
	}

	$time_now = TIMENOW;
	if ($last_browse > $time_now) {
		$last_browse=$time_now;
	}

	if (get_user_class() < UC_VIP && $waitsystem == "yes") {
		$ratio = get_ratio($CURUSER["id"], false);
		$gigs = $CURUSER["uploaded"] / (1024*1024*1024);
		if($gigs > 10)
		{
			if ($ratio < 0.4) $wait = 24;
			elseif ($ratio < 0.5) $wait = 12;
			elseif ($ratio < 0.6) $wait = 6;
			elseif ($ratio < 0.8) $wait = 3;
			else $wait = 0;
		}
		else $wait = 0;
	}
?>
<table class="torrents" cellspacing="0" cellpadding="5" width="100%">
<tr>
<?php
$count_get = 0;
$oldlink = "";
foreach ($_GET as $get_name => $get_value) {
	$get_name = mysql_real_escape_string(strip_tags(str_replace(array("\"","'"),array("",""),$get_name)));
	$get_value = mysql_real_escape_string(strip_tags(str_replace(array("\"","'"),array("",""),$get_value)));

	if ($get_name != "sort" && $get_name != "type") {
		if ($count_get > 0) {
			$oldlink .= "&amp;" . $get_name . "=" . $get_value;
		}
		else {
			$oldlink .= $get_name . "=" . $get_value;
		}
		$count_get++;
	}
}
if ($count_get > 0) {
	$oldlink = $oldlink . "&amp;";
}
$sort = $_GET['sort'];
$link = array();
for ($i=1; $i<=9; $i++){
	if ($sort == $i)
		$link[$i] = ($_GET['type'] == "desc" ? "asc" : "desc");
	else $link[$i] = ($i == 1 ? "asc" : "desc");
}
?>
<td class="colhead" style="padding: 0px"><?php echo $lang_functions['col_type'] ?></td>
<td class="colhead"><a href="?<?php echo $oldlink?>sort=1&amp;type=<?php echo $link[1]?>"><?php echo $lang_functions['col_name'] ?></a></td>
<?php

if ($wait)
{
	print("<td class=\"colhead\">".$lang_functions['col_wait']."</td>\n");
}
if ($CURUSER['showcomnum'] != 'no') { ?>
<td class="colhead"><a href="?<?php echo $oldlink?>sort=3&amp;type=<?php echo $link[3]?>"><img class="comments" src="pic/trans.gif" alt="comments" title="<?php echo $lang_functions['title_number_of_comments'] ?>" /></a></td>
<?php } ?>

<td class="colhead"><a href="?<?php echo $oldlink?>sort=4&amp;type=<?php echo $link[4]?>"><img class="time" src="pic/trans.gif" alt="time" title="<?php echo ($CURUSER['timetype'] != 'timealive' ? $lang_functions['title_time_added'] : $lang_functions['title_time_alive'])?>" /></a></td>
<td class="colhead"><a href="?<?php echo $oldlink?>sort=5&amp;type=<?php echo $link[5]?>"><img class="size" src="pic/trans.gif" alt="size" title="<?php echo $lang_functions['title_size'] ?>" /></a></td>
<td class="colhead"><a href="?<?php echo $oldlink?>sort=7&amp;type=<?php echo $link[7]?>"><img class="seeders" src="pic/trans.gif" alt="seeders" title="<?php echo $lang_functions['title_number_of_seeders'] ?>" /></a></td>
<td class="colhead"><a href="?<?php echo $oldlink?>sort=8&amp;type=<?php echo $link[8]?>"><img class="leechers" src="pic/trans.gif" alt="leechers" title="<?php echo $lang_functions['title_number_of_leechers'] ?>" /></a></td>
<td class="colhead"><a href="?<?php echo $oldlink?>sort=6&amp;type=<?php echo $link[6]?>"><img class="snatched" src="pic/trans.gif" alt="snatched" title="<?php echo $lang_functions['title_number_of_snatched']?>" /></a></td>
<td class="colhead"><a href="?<?php echo $oldlink?>sort=9&amp;type=<?php echo $link[9]?>"><?php echo $lang_functions['col_uploader']?></a></td>
<?php
if (get_user_class() >= $torrentmanage_class) { ?>
	<td class="colhead"><?php echo $lang_functions['col_action'] ?></td>
<?php } ?>
</tr>
<?php
$caticonrow = get_category_icon_row($CURUSER['caticon']);
if ($caticonrow['secondicon'] == 'yes')
$has_secondicon = true;
else $has_secondicon = false;
$counter = 0;
if ($smalldescription_main == 'no' || $CURUSER['showsmalldescr'] == 'no')
	$displaysmalldescr = false;
else $displaysmalldescr = true;
while ($row = mysql_fetch_assoc($res))
{
	$id = $row["id"];
	$sphighlight = get_torrent_bg_color($row['sp_state']);
	print("<tr" . $sphighlight . ">\n");

	print("<td class=\"rowfollow nowrap\" valign=\"middle\" style='padding: 0px'>");
	if (isset($row["category"])) {
		print(return_category_image($row["category"], "?"));
		if ($has_secondicon){
			print(get_second_icon($row, "pic/".$catimgurl."additional/"));
		}
	}
	else
		print("-");
	print("</td>\n");

	//torrent name
	$dispname = trim($row["name"]);
	$short_torrent_name_alt = "";
	$mouseovertorrent = "";
	$tooltipblock = "";
	$has_tooltip = false;
	if ($enabletooltip_tweak == 'yes')
		$tooltiptype = $CURUSER['tooltip'];
	else
		$tooltiptype = 'off';
	switch ($tooltiptype){
		case 'minorimdb' : {
			if ($showextinfo['imdb'] == 'yes' && $row["url"])
				{
				$url = $row['url'];
				$cache = $row['cache_stamp'];
				$type = 'minor';
				$has_tooltip = true;
				}
			break;
			}
		case 'medianimdb' :
			{
			if ($showextinfo['imdb'] == 'yes' && $row["url"])
				{
				$url = $row['url'];
				$cache = $row['cache_stamp'];
				$type = 'median';
				$has_tooltip = true;
				}
			break;
			}
		case 'off' :  break;
	}
	if (!$has_tooltip)
		$short_torrent_name_alt = "title=\"".htmlspecialchars($dispname)."\"";
	else{
	$torrent_tooltip[$counter]['id'] = "torrent_" . $counter;
	$torrent_tooltip[$counter]['content'] = "";
	$mouseovertorrent = "onmouseover=\"get_ext_info_ajax('".$torrent_tooltip[$counter]['id']."','".$url."','".$cache."','".$type."'); domTT_activate(this, event, 'content', document.getElementById('" . $torrent_tooltip[$counter]['id'] . "'), 'trail', false, 'delay',600,'lifetime',6000,'fade','both','styleClass','niceTitle', 'fadeMax',87, 'maxWidth', 500);\"";
	}
	$count_dispname=mb_strlen($dispname,"UTF-8");
	if (!$displaysmalldescr || $row["small_descr"] == "")// maximum length of torrent name
		$max_length_of_torrent_name = 120;
	elseif ($CURUSER['fontsize'] == 'large')
		$max_length_of_torrent_name = 60;
	elseif ($CURUSER['fontsize'] == 'small')
		$max_length_of_torrent_name = 80;
	else $max_length_of_torrent_name = 70;

	if($count_dispname > $max_length_of_torrent_name)
		$dispname=mb_substr($dispname, 0, $max_length_of_torrent_name-2,"UTF-8") . "..";

	if ($row['pos_state'] == 'sticky' && $CURUSER['appendsticky'] == 'yes')
		$stickyicon = "<img class=\"sticky\" src=\"pic/trans.gif\" alt=\"Sticky\" title=\"".$lang_functions['title_sticky']."\" />&nbsp;";
	else $stickyicon = "";
	
	print("<td class=\"rowfollow\" width=\"100%\" align=\"left\"><table class=\"torrentname\" width=\"100%\"><tr" . $sphighlight . "><td class=\"embedded\">".$stickyicon."<a $short_torrent_name_alt $mouseovertorrent href=\"details.php?id=".$id."&amp;hit=1\"><b>".htmlspecialchars($dispname)."</b></a>");
	$sp_torrent = get_torrent_promotion_append($row['sp_state'],"",true,$row["added"], $row['promotion_time_type'], $row['promotion_until']);
	$picked_torrent = "";
	if ($CURUSER['appendpicked'] != 'no'){
	if($row['picktype']=="hot")
	$picked_torrent = " <b>[<font class='hot'>".$lang_functions['text_hot']."</font>]</b>";
	elseif($row['picktype']=="classic")
	$picked_torrent = " <b>[<font class='classic'>".$lang_functions['text_classic']."</font>]</b>";
	elseif($row['picktype']=="recommended")
	$picked_torrent = " <b>[<font class='recommended'>".$lang_functions['text_recommended']."</font>]</b>";
	}
	if ($CURUSER['appendnew'] != 'no' && strtotime($row["added"]) >= $last_browse)
		print("<b> (<font class='new'>".$lang_functions['text_new_uppercase']."</font>)</b>");

	$banned_torrent = ($row["banned"] == 'yes' ? " <b>(<font class=\"striking\">".$lang_functions['text_banned']."</font>)</b>" : "");
	print($banned_torrent.$picked_torrent.$sp_torrent);
	if ($displaysmalldescr){
		//small descr
		$dissmall_descr = trim($row["small_descr"]);
		$count_dissmall_descr=mb_strlen($dissmall_descr,"UTF-8");
		$max_lenght_of_small_descr=$max_length_of_torrent_name; // maximum length
		if($count_dissmall_descr > $max_lenght_of_small_descr)
		{
			$dissmall_descr=mb_substr($dissmall_descr, 0, $max_lenght_of_small_descr-2,"UTF-8") . "..";
		}
		print($dissmall_descr == "" ? "" : "<br />".htmlspecialchars($dissmall_descr));
	}
	print("</td>");

		$act = "";
		if ($CURUSER["dlicon"] != 'no' && $CURUSER["downloadpos"] != "no")
		$act .= "<a href=\"download.php?id=".$id."\"><img class=\"download\" src=\"pic/trans.gif\" style='padding-bottom: 2px;' alt=\"download\" title=\"".$lang_functions['title_download_torrent']."\" /></a>" ;
		if ($CURUSER["bmicon"] == 'yes'){
			$bookmark = " href=\"javascript: bookmark(".$id.",".$counter.");\"";
			$act .= ($act ? "<br />" : "")."<a id=\"bookmark".$counter."\" ".$bookmark." >".get_torrent_bookmark_state($CURUSER['id'], $id)."</a>";
		}

	print("<td width=\"20\" class=\"embedded\" style=\"text-align: right; \" valign=\"middle\">".$act."</td>\n");

	print("</tr></table></td>");
	if ($wait)
	{
		$elapsed = floor((TIMENOW - strtotime($row["added"])) / 3600);
		if ($elapsed < $wait)
		{
			$color = dechex(floor(127*($wait - $elapsed)/48 + 128)*65536);
			print("<td class=\"rowfollow nowrap\"><a href=\"faq.php#id46\"><font color=\"".$color."\">" . number_format($wait - $elapsed) . $lang_functions['text_h']."</font></a></td>\n");
		}
		else
		print("<td class=\"rowfollow nowrap\">".$lang_functions['text_none']."</td>\n");
	}
	
	if ($CURUSER['showcomnum'] != 'no')
	{
	print("<td class=\"rowfollow\">");
	$nl = "";

	//comments

	$nl = "<br />";
	if (!$row["comments"]) {
		print("<a href=\"comment.php?action=add&amp;pid=".$id."&amp;type=torrent\" title=\"".$lang_functions['title_add_comments']."\">" . $row["comments"] .  "</a>");
	} else {
		if ($enabletooltip_tweak == 'yes' && $CURUSER['showlastcom'] != 'no')
		{
			if (!$lastcom = $Cache->get_value('torrent_'.$id.'_last_comment_content')){
				$res2 = sql_query("SELECT user, added, text FROM comments WHERE torrent = $id ORDER BY id DESC LIMIT 1");
				$lastcom = mysql_fetch_array($res2);
				$Cache->cache_value('torrent_'.$id.'_last_comment_content', $lastcom, 1855);
			}
			$timestamp = strtotime($lastcom["added"]);
			$hasnewcom = ($lastcom['user'] != $CURUSER['id'] && $timestamp >= $last_browse);
			if ($lastcom)
			{
				if ($CURUSER['timetype'] != 'timealive')
					$lastcomtime = $lang_functions['text_at_time'].$lastcom['added'];
				else
					$lastcomtime = $lang_functions['text_blank'].gettime($lastcom["added"],true,false,true);
					$lastcom_tooltip[$counter]['id'] = "lastcom_" . $counter;
					$lastcom_tooltip[$counter]['content'] = ($hasnewcom ? "<b>(<font class='new'>".$lang_functions['text_new_uppercase']."</font>)</b> " : "").$lang_functions['text_last_commented_by'].get_username($lastcom['user']) . $lastcomtime."<br />". format_comment(mb_substr($lastcom['text'],0,100,"UTF-8") . (mb_strlen($lastcom['text'],"UTF-8") > 100 ? " ......" : "" ),true,false,false,true,600,false,false);
					$onmouseover = "onmouseover=\"domTT_activate(this, event, 'content', document.getElementById('" . $lastcom_tooltip[$counter]['id'] . "'), 'trail', false, 'delay', 500,'lifetime',3000,'fade','both','styleClass','niceTitle','fadeMax', 87,'maxWidth', 400);\"";
			}
		} else {
			$hasnewcom = false;
			$onmouseover = "";
		}
		print("<b><a href=\"details.php?id=".$id."&amp;hit=1&amp;cmtpage=1#startcomments\" ".$onmouseover.">". ($hasnewcom ? "<font class='new'>" : ""). $row["comments"] .($hasnewcom ? "</font>" : ""). "</a></b>");
	}

	print("</td>");
	}

	$time = $row["added"];
	$time = gettime($time,false,true);
	print("<td class=\"rowfollow nowrap\">". $time. "</td>");

	//size
	print("<td class=\"rowfollow\">" . mksize_compact($row["size"])."</td>");

	if ($row["seeders"]) {
			$ratio = ($row["leechers"] ? ($row["seeders"] / $row["leechers"]) : 1);
			$ratiocolor = get_slr_color($ratio);
			print("<td class=\"rowfollow\" align=\"center\"><b><a href=\"details.php?id=".$id."&amp;hit=1&amp;dllist=1#seeders\">".($ratiocolor ? "<font color=\"" .
			$ratiocolor . "\">" . number_format($row["seeders"]) . "</font>" : number_format($row["seeders"]))."</a></b></td>\n");
	}
	else
		print("<td class=\"rowfollow\"><span class=\"" . linkcolor($row["seeders"]) . "\">" . number_format($row["seeders"]) . "</span></td>\n");

	if ($row["leechers"]) {
		print("<td class=\"rowfollow\"><b><a href=\"details.php?id=".$id."&amp;hit=1&amp;dllist=1#leechers\">" .
		number_format($row["leechers"]) . "</a></b></td>\n");
	}
	else
		print("<td class=\"rowfollow\">0</td>\n");

	if ($row["times_completed"] >=1)
	print("<td class=\"rowfollow\"><a href=\"viewsnatches.php?id=".$row[id]."\"><b>" . number_format($row["times_completed"]) . "</b></a></td>\n");
	else
	print("<td class=\"rowfollow\">" . number_format($row["times_completed"]) . "</td>\n");

		if ($row["anonymous"] == "yes" && get_user_class() >= $torrentmanage_class)
		{
			print("<td class=\"rowfollow\" align=\"center\"><i>".$lang_functions['text_anonymous']."</i><br />".(isset($row["owner"]) ? "(" . get_username($row["owner"]) .")" : "<i>".$lang_functions['text_orphaned']."</i>") . "</td>\n");
		}
		elseif ($row["anonymous"] == "yes")
		{
			print("<td class=\"rowfollow\"><i>".$lang_functions['text_anonymous']."</i></td>\n");
		}
		else
		{
			print("<td class=\"rowfollow\">" . (isset($row["owner"]) ? get_username($row["owner"]) : "<i>".$lang_functions['text_orphaned']."</i>") . "</td>\n");
		}

	if (get_user_class() >= $torrentmanage_class)
	{
		print("<td class=\"rowfollow\"><a href=\"".htmlspecialchars("fastdelete.php?id=".$row[id])."\"><img class=\"staff_delete\" src=\"pic/trans.gif\" alt=\"D\" title=\"".$lang_functions['text_delete']."\" /></a>");
		print("<br /><a href=\"edit.php?returnto=" . rawurlencode($_SERVER["REQUEST_URI"]) . "&amp;id=" . $row["id"] . "\"><img class=\"staff_edit\" src=\"pic/trans.gif\" alt=\"E\" title=\"".$lang_functions['text_edit']."\" /></a></td>\n");
	}
	print("</tr>\n");
	$counter++;
}
print("</table>");
if ($CURUSER['appendpromotion'] == 'highlight')
	print("<p align=\"center\"> ".$lang_functions['text_promoted_torrents_note']."</p>\n");

if($enabletooltip_tweak == 'yes' && (!isset($CURUSER) || $CURUSER['showlastcom'] == 'yes'))
create_tooltip_container($lastcom_tooltip, 400);
create_tooltip_container($torrent_tooltip, 500);
}

function get_username($id, $big = false, $link = true, $bold = true, $target = false, $bracket = false, $withtitle = false, $link_ext = "", $underline = false)
{
	static $usernameArray = array();
	global $lang_functions;
	$id = 0+$id;

	if (func_num_args() == 1 && $usernameArray[$id]) {  //One argument=is default display of username. Get it directly from static array if available
		return $usernameArray[$id];
	}
	$arr = get_user_row($id);
	if ($arr){
		if ($big)
		{
			$donorpic = "starbig";
			$leechwarnpic = "leechwarnedbig";
			$warnedpic = "warnedbig";
			$disabledpic = "disabledbig";
			$style = "style='margin-left: 4pt'";
		}
		else
		{
			$donorpic = "star";
			$leechwarnpic = "leechwarned";
			$warnedpic = "warned";
			$disabledpic = "disabled";
			$style = "style='margin-left: 2pt'";
		}
		$pics = $arr["donor"] == "yes" ? "<img class=\"".$donorpic."\" src=\"pic/trans.gif\" alt=\"Donor\" ".$style." />" : "";

		if ($arr["enabled"] == "yes")
			$pics .= ($arr["leechwarn"] == "yes" ? "<img class=\"".$leechwarnpic."\" src=\"pic/trans.gif\" alt=\"Leechwarned\" ".$style." />" : "") . ($arr["warned"] == "yes" ? "<img class=\"".$warnedpic."\" src=\"pic/trans.gif\" alt=\"Warned\" ".$style." />" : "");
		else
			$pics .= "<img class=\"".$disabledpic."\" src=\"pic/trans.gif\" alt=\"Disabled\" ".$style." />\n";

		$username = ($underline == true ? "<u>" . $arr['username'] . "</u>" : $arr['username']);
		$username = ($bold == true ? "<b>" . $username . "</b>" : $username);
		$username = ($link == true ? "<a ". $link_ext . " href=\"userdetails.php?id=" . $id . "\"" . ($target == true ? " target=\"_blank\"" : "") . " class='". get_user_class_name($arr['class'],true) . "_Name'>" . $username . "</a>" : $username) . $pics . ($withtitle == true ? " (" . ($arr['title'] == "" ?  get_user_class_name($arr['class'],false,true,true) : "<span class='".get_user_class_name($arr['class'],true) . "_Name'><b>".htmlspecialchars($arr['title'])) . "</b></span>)" : "");

		$username = "<span class=\"nowrap\">" . ( $bracket == true ? "(" . $username . ")" : $username) . "</span>";
	}
	else
	{
		$username = "<i>".$lang_functions['text_orphaned']."</i>";
		$username = "<span class=\"nowrap\">" . ( $bracket == true ? "(" . $username . ")" : $username) . "</span>";
	}
	if (func_num_args() == 1) { //One argument=is default display of username, save it in static array
		$usernameArray[$id] = $username;
	}
	return $username;
}

function get_percent_completed_image($p) {
	$maxpx = "45"; // Maximum amount of pixels for the progress bar

	if ($p == 0) $progress = "<img class=\"progbarrest\" src=\"pic/trans.gif\" style=\"width: " . ($maxpx) . "px;\" alt=\"\" />";
	if ($p == 100) $progress = "<img class=\"progbargreen\" src=\"pic/trans.gif\" style=\"width: " . ($maxpx) . "px;\" alt=\"\" />";
	if ($p >= 1 && $p <= 30) $progress = "<img class=\"progbarred\" src=\"pic/trans.gif\" style=\"width: " . ($p*($maxpx/100)) . "px;\" alt=\"\" /><img class=\"progbarrest\" src=\"pic/trans.gif\" style=\"width: " . ((100-$p)*($maxpx/100)) . "px;\" alt=\"\" />";
	if ($p >= 31 && $p <= 65) $progress = "<img class=\"progbaryellow\" src=\"pic/trans.gif\" style=\"width: " . ($p*($maxpx/100)) . "px;\" alt=\"\" /><img class=\"progbarrest\" src=\"pic/trans.gif\" style=\"width: " . ((100-$p)*($maxpx/100)) . "px;\" alt=\"\" />";
	if ($p >= 66 && $p <= 99) $progress = "<img class=\"progbargreen\" src=\"pic/trans.gif\" style=\"width: " . ($p*($maxpx/100)) . "px;\" alt=\"\" /><img class=\"progbarrest\" src=\"pic/trans.gif\" style=\"width: " . ((100-$p)*($maxpx/100)) . "px;\" alt=\"\" />";
	return "<img class=\"bar_left\" src=\"pic/trans.gif\" alt=\"\" />" . $progress ."<img class=\"bar_right\" src=\"pic/trans.gif\" alt=\"\" />";
}

function get_ratio_img($ratio)
{
	if ($ratio >= 16)
	$s = "163";
	else if ($ratio >= 8)
	$s = "117";
	else if ($ratio >= 4)
	$s = "5";
	else if ($ratio >= 2)
	$s = "3";
	else if ($ratio >= 1)
	$s = "2";
	else if ($ratio >= 0.5)
	$s = "34";
	else if ($ratio >= 0.25)
	$s = "10";
	else
	$s = "52";

	return "<img src=\"pic/smilies/".$s.".gif\" alt=\"\" />";
}

function GetVar ($name) {
	if ( is_array($name) ) {
		foreach ($name as $var) GetVar ($var);
	} else {
		if ( !isset($_REQUEST[$name]) )
		return false;
		$GLOBALS[$name] = $_REQUEST[$name];
		return $GLOBALS[$name];
	}
}

function ssr ($arg) {
	if (is_array($arg)) {
		foreach ($arg as $key=>$arg_bit) {
			$arg[$key] = ssr($arg_bit);
		}
	} else {
		$arg = stripslashes($arg);
	}
	return $arg;
}

function parked()
{
	global $lang_functions;
	global $CURUSER;
	if ($CURUSER["parked"] == "yes")
	stderr($lang_functions['std_access_denied'], $lang_functions['std_your_account_parked']);
}

function validusername($username)
{
	if ($username == "")
	return false;

	// The following characters are allowed in user names
	$allowedchars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";

	for ($i = 0; $i < strlen($username); ++$i)
	if (strpos($allowedchars, $username[$i]) === false)
	return false;

	return true;
}

//Code for Viewing NFO file

// code: Takes a string and does a IBM-437-to-HTML-Unicode-Entities-conversion.
// swedishmagic specifies special behavior for Swedish characters.
// Some Swedish Latin-1 letters collide with popular DOS glyphs. If these
// characters are between ASCII-characters (a-zA-Z and more) they are
// treated like the Swedish letters, otherwise like the DOS glyphs.
function code($ibm_437, $swedishmagic = false) {
$table437 = array("\200", "\201", "\202", "\203", "\204", "\205", "\206", "\207",
"\210", "\211", "\212", "\213", "\214", "\215", "\216", "\217", "\220",
"\221", "\222", "\223", "\224", "\225", "\226", "\227", "\230", "\231",
"\232", "\233", "\234", "\235", "\236", "\237", "\240", "\241", "\242",
"\243", "\244", "\245", "\246", "\247", "\250", "\251", "\252", "\253",
"\254", "\255", "\256", "\257", "\260", "\261", "\262", "\263", "\264",
"\265", "\266", "\267", "\270", "\271", "\272", "\273", "\274", "\275",
"\276", "\277", "\300", "\301", "\302", "\303", "\304", "\305", "\306",
"\307", "\310", "\311", "\312", "\313", "\314", "\315", "\316", "\317",
"\320", "\321", "\322", "\323", "\324", "\325", "\326", "\327", "\330",
"\331", "\332", "\333", "\334", "\335", "\336", "\337", "\340", "\341",
"\342", "\343", "\344", "\345", "\346", "\347", "\350", "\351", "\352",
"\353", "\354", "\355", "\356", "\357", "\360", "\361", "\362", "\363",
"\364", "\365", "\366", "\367", "\370", "\371", "\372", "\373", "\374",
"\375", "\376", "\377");

$tablehtml = array("&#x00c7;", "&#x00fc;", "&#x00e9;", "&#x00e2;", "&#x00e4;",
"&#x00e0;", "&#x00e5;", "&#x00e7;", "&#x00ea;", "&#x00eb;", "&#x00e8;",
"&#x00ef;", "&#x00ee;", "&#x00ec;", "&#x00c4;", "&#x00c5;", "&#x00c9;",
"&#x00e6;", "&#x00c6;", "&#x00f4;", "&#x00f6;", "&#x00f2;", "&#x00fb;",
"&#x00f9;", "&#x00ff;", "&#x00d6;", "&#x00dc;", "&#x00a2;", "&#x00a3;",
"&#x00a5;", "&#x20a7;", "&#x0192;", "&#x00e1;", "&#x00ed;", "&#x00f3;",
"&#x00fa;", "&#x00f1;", "&#x00d1;", "&#x00aa;", "&#x00ba;", "&#x00bf;",
"&#x2310;", "&#x00ac;", "&#x00bd;", "&#x00bc;", "&#x00a1;", "&#x00ab;",
"&#x00bb;", "&#x2591;", "&#x2592;", "&#x2593;", "&#x2502;", "&#x2524;",
"&#x2561;", "&#x2562;", "&#x2556;", "&#x2555;", "&#x2563;", "&#x2551;",
"&#x2557;", "&#x255d;", "&#x255c;", "&#x255b;", "&#x2510;", "&#x2514;",
"&#x2534;", "&#x252c;", "&#x251c;", "&#x2500;", "&#x253c;", "&#x255e;",
"&#x255f;", "&#x255a;", "&#x2554;", "&#x2569;", "&#x2566;", "&#x2560;",
"&#x2550;", "&#x256c;", "&#x2567;", "&#x2568;", "&#x2564;", "&#x2565;",
"&#x2559;", "&#x2558;", "&#x2552;", "&#x2553;", "&#x256b;", "&#x256a;",
"&#x2518;", "&#x250c;", "&#x2588;", "&#x2584;", "&#x258c;", "&#x2590;",
"&#x2580;", "&#x03b1;", "&#x00df;", "&#x0393;", "&#x03c0;", "&#x03a3;",
"&#x03c3;", "&#x03bc;", "&#x03c4;", "&#x03a6;", "&#x0398;", "&#x03a9;",
"&#x03b4;", "&#x221e;", "&#x03c6;", "&#x03b5;", "&#x2229;", "&#x2261;",
"&#x00b1;", "&#x2265;", "&#x2264;", "&#x2320;", "&#x2321;", "&#x00f7;",
"&#x2248;", "&#x00b0;", "&#x2219;", "&#x00b7;", "&#x221a;", "&#x207f;",
"&#x00b2;", "&#x25a0;", "&#x00a0;");
$s = htmlspecialchars($ibm_437);


// 0-9, 11-12, 14-31, 127 (decimalt)
$control =
array("\000", "\001", "\002", "\003", "\004", "\005", "\006", "\007",
"\010", "\011", /*"\012",*/ "\013", "\014", /*"\015",*/ "\016", "\017",
"\020", "\021", "\022", "\023", "\024", "\025", "\026", "\027",
"\030", "\031", "\032", "\033", "\034", "\035", "\036", "\037",
"\177");

/* Code control characters to control pictures.
http://www.unicode.org/charts/PDF/U2400.pdf
(This is somewhat the Right Thing, but looks crappy with Courier New.)
$controlpict = array("&#x2423;","&#x2404;");
$s = str_replace($control,$controlpict,$s); */

// replace control chars with space - feel free to fix the regexp smile.gif
/*echo "[a\\x00-\\x1F]";
//$s = preg_replace("/[ \\x00-\\x1F]/", " ", $s);
$s = preg_replace("/[ \000-\037]/", " ", $s); */
$s = str_replace($control," ",$s);




if ($swedishmagic){
$s = str_replace("\345","\206",$s);
$s = str_replace("\344","\204",$s);
$s = str_replace("\366","\224",$s);
// $s = str_replace("\304","\216",$s);
//$s = "[ -~]\\xC4[a-za-z]";

// couldn't get ^ and $ to work, even through I read the man-pages,
// i'm probably too tired and too unfamiliar with posix regexps right now.
$s = preg_replace("/([ -~])\305([ -~])/", "\\1\217\\2", $s);
$s = preg_replace("/([ -~])\304([ -~])/", "\\1\216\\2", $s);
$s = preg_replace("/([ -~])\326([ -~])/", "\\1\231\\2", $s);

$s = str_replace("\311", "\220", $s); //
$s = str_replace("\351", "\202", $s); //
}

$s = str_replace($table437, $tablehtml, $s);
return $s;
}


//Tooltip container for hot movie, classic movie, etc
function create_tooltip_container($id_content_arr, $width = 400)
{
	if(count($id_content_arr))
	{
		$result = "<div id=\"tooltipPool\" style=\"display: none\">";
		foreach($id_content_arr as $id_content_arr_each)
		{
			$result .= "<div id=\"" . $id_content_arr_each['id'] . "\">" . $id_content_arr_each['content'] . "</div>";
		}
		$result .= "</div>";
		print($result);
	}
}

function getimdb($imdb_id, $cache_stamp, $mode = 'minor')
{
	global $lang_functions;
	global $showextinfo;
	$thenumbers = $imdb_id;
	$movie = new imdb ($thenumbers);
	$movieid = $thenumbers;
	$movie->setid ($movieid);

	$target = array('Title', 'Credits', 'Plot');
	switch ($movie->cachestate($target))
	{
		case "0": //cache is not ready
			{
			return false;
			break;
			}
		case "1": //normal
			{
				$title = $movie->title ();
				$year = $movie->year ();
				$country = $movie->country ();
				$countries = "";
				$temp = "";
				for ($i = 0; $i < count ($country); $i++)
				{
					$temp .="$country[$i], ";
				}
				$countries = rtrim(trim($temp), ",");

				$director = $movie->director();
				$director_or_creator = "";
				if ($director)
				{
					$temp = "";
					for ($i = 0; $i < count ($director); $i++)
					{
						$temp .= $director[$i]["name"].", ";
					}
					$director_or_creator = "<strong><font color=\"DarkRed\">".$lang_functions['text_director'].": </font></strong>".rtrim(trim($temp), ",");
				}
				else { //for tv series
					$creator = $movie->creator();
					$director_or_creator = "<strong><font color=\"DarkRed\">".$lang_functions['text_creator'].": </font></strong>".$creator;
				}
				$cast = $movie->cast();
				$temp = "";
				for ($i = 0; $i < count ($cast); $i++) //get names of first three casts
				{
					if ($i > 2)
					{
						break;
					}
					$temp .= $cast[$i]["name"].", ";
				}
				$casts = rtrim(trim($temp), ",");
				$gen = $movie->genres();
				$genres = $gen[0].(count($gen) > 1 ? ", ".$gen[1] : ""); //get first two genres;
				$rating = $movie->rating ();
				$votes = $movie->votes ();
				if ($votes)
					$imdbrating = "<b>".$rating."</b>/10 (".$votes.$lang_functions['text_votes'].")";
				else $imdbrating = $lang_functions['text_awaiting_five_votes'];

				$tagline = $movie->tagline ();
				switch ($mode)
				{
				case 'minor' : 
					{
					$autodata = "<font class=\"big\"><b>".$title."</b></font> (".$year.") <br /><strong><font color=\"DarkRed\">".$lang_functions['text_imdb'].": </font></strong>".$imdbrating." <strong><font color=\"DarkRed\">".$lang_functions['text_country'].": </font></strong>".$countries." <strong><font color=\"DarkRed\">".$lang_functions['text_genres'].": </font></strong>".$genres."<br />".$director_or_creator."<strong><font color=\"DarkRed\"> ".$lang_functions['text_starring'].": </font></strong>".$casts."<br /><p><strong>".$tagline."</strong></p>";
					break;
					}
				case 'median':
					{
					if (($photo_url = $movie->photo_localurl() ) != FALSE)
						$smallth = "<img src=\"".$photo_url. "\" width=\"105\" alt=\"poster\" />";
					else $smallth = "";
					$runtime = $movie->runtime ();
					$language = $movie->language ();
					$plot = $movie->plot ();
					$plots = "";
					if(count($plot) != 0){ //get plots from plot page
							$plots .= "<font color=\"DarkRed\">*</font> ".strip_tags($plot[0], '<br /><i>');
							$plots = mb_substr($plots,0,300,"UTF-8") . (mb_strlen($plots,"UTF-8") > 300 ? " ..." : "" );
							$plots .= (strpos($plots,"<i>") == true && strpos($plots,"</i>") == false ? "</i>" : "");//sometimes <i> is open and not ended because of mb_substr;
							$plots = "<font class=\"small\">".$plots."</font>";
						}
					elseif ($plotoutline = $movie->plotoutline ()){ //get plot from title page
						$plots .= "<font color=\"DarkRed\">*</font> ".strip_tags($plotoutline, '<br /><i>');
						$plots = mb_substr($plots,0,300,"UTF-8") . (mb_strlen($plots,"UTF-8") > 300 ? " ..." : "" );
						$plots .= (strpos($plots,"<i>") == true && strpos($plots,"</i>") == false ? "</i>" : "");//sometimes <i> is open and not ended because of mb_substr;
						$plots = "<font class=\"small\">".$plots."</font>";
						}
					$autodata = "<table style=\"background-color: transparent;\" border=\"0\" cellspacing=\"0\" cellpadding=\"3\">
".($smallth ? "<td class=\"clear\" valign=\"top\" align=\"right\">
$smallth
</td>" : "")
."<td class=\"clear\" valign=\"top\" align=\"left\">
<table style=\"background-color: transparent;\" border=\"0\" cellspacing=\"0\" cellpadding=\"3\" width=\"350\">
<tr><td class=\"clear\" colspan=\"2\"><img class=\"imdb\" src=\"pic/trans.gif\" alt=\"imdb\" /> <font class=\"big\"><b>".$title."</b></font> (".$year.") </td></tr>
<tr><td class=\"clear\"><strong><font color=\"DarkRed\">".$lang_functions['text_imdb'].": </font></strong>".$imdbrating."</td>
".( $runtime ? "<td class=\"clear\"><strong><font color=\"DarkRed\">".$lang_functions['text_runtime'].": </font></strong>".$runtime.$lang_functions['text_min']."</td>" : "<td class=\"clear\"></td>")."</tr>
<tr><td class=\"clear\"><strong><font color=\"DarkRed\">".$lang_functions['text_country'].": </font></strong>".$countries."</td>
".( $language ? "<td class=\"clear\"><strong><font color=\"DarkRed\">".$lang_functions['text_language'].": </font></strong>".$language."</td>" : "<td class=\"clear\"></td>")."</tr>
<tr><td class=\"clear\">".$director_or_creator."</td>
<td class=\"clear\"><strong><font color=\"DarkRed\">".$lang_functions['text_genres'].": </font></strong>".$genres."</td></tr>
<tr><td class=\"clear\" colspan=\"2\"><strong><font color=\"DarkRed\">".$lang_functions['text_starring'].": </font></strong>".$casts."</td></tr>
".( $plots ? "<tr><td class=\"clear\" colspan=\"2\">".$plots."</td></tr>" : "")."
</table>
</td>
</table>";
					break;
					}
				}
				return $autodata;
			}
			case "2" : 
			{
				return false;
				break;
			}
			case "3" :
			{
				return false;
				break;
			}
	}
}

function quickreply($formname, $taname,$submit){
	print("<textarea name='".$taname."' cols=\"100\" rows=\"8\" style=\"width: 450px\" onkeydown=\"ctrlenter(event,'compose','qr')\"></textarea>");
	print(smile_row($formname, $taname));
	print("<br />");
 	print("<input type=\"submit\" id=\"qr\" class=\"btn\" value=\"".$submit."\" />");
}

function smile_row($formname, $taname){
	$quickSmilesNumbers = array(4, 5, 39, 25, 11, 8, 10, 15, 27, 57, 42, 122, 52, 28, 29, 30, 176);
	$smilerow = "<div align=\"center\">";
	foreach ($quickSmilesNumbers as $smilyNumber) {
		$smilerow .= getSmileIt($formname, $taname, $smilyNumber);
	}
	$smilerow .= "</div>";
	return $smilerow;
}
function getSmileIt($formname, $taname, $smilyNumber) {
	return "<a href=\"javascript: SmileIT('[em$smilyNumber]','".$formname."','".$taname."')\"  onmouseover=\"domTT_activate(this, event, 'content', '".htmlspecialchars("<table><tr><td><img src=\'pic/smilies/$smilyNumber.gif\' alt=\'\' /></td></tr></table>")."', 'trail', false, 'delay', 0,'lifetime',10000,'styleClass','smilies','maxWidth', 400);\"><img style=\"max-width: 25px;\" src=\"pic/smilies/$smilyNumber.gif\" alt=\"\" /></a>";
}

function classlist($selectname,$maxclass, $selected, $minClass = 0){
	$list = "<select name=\"".$selectname."\">";
	for ($i = $minClass; $i <= $maxclass; $i++)
		$list .= "<option value=\"".$i."\"" . ($selected == $i ? " selected=\"selected\"" : "") . ">" . get_user_class_name($i,false,false,true) . "</option>\n";
	$list .= "</select>";
	return $list;
}

function permissiondenied(){
	global $lang_functions;
	stderr($lang_functions['std_error'], $lang_functions['std_permission_denied']);
}

function gettime($time, $withago = true, $twoline = false, $forceago = false, $oneunit = false, $isfuturetime = false){
	global $lang_functions, $CURUSER;
	if ($CURUSER['timetype'] != 'timealive' && !$forceago){
		$newtime = $time;
		if ($twoline){
		$newtime = str_replace(" ", "<br />", $newtime);
		}
	}
	else{
		$timestamp = strtotime($time);
		if ($isfuturetime && $timestamp < TIMENOW)
			$newtime = false;
		else
		{
			$newtime = get_elapsed_time($timestamp,$oneunit).($withago ? $lang_functions['text_ago'] : "");
			if($twoline){
				$newtime = str_replace("&nbsp;", "<br />", $newtime);
			}
			elseif($oneunit){
				if ($length = strpos($newtime, "&nbsp;"))
					$newtime = substr($newtime,0,$length);
			}
			else $newtime = str_replace("&nbsp;", $lang_functions['text_space'], $newtime);
			$newtime = "<span title=\"".$time."\">".$newtime."</span>";
		}
	}
	return $newtime;
}

function get_forum_pic_folder(){
	global $CURLANGDIR;
	return "pic/forum_pic/".$CURLANGDIR;
}

function get_category_icon_row($typeid)
{
	global $Cache;
	static $rows;
	if (!$typeid) {
		$typeid=1;
	}
	if (!$rows && !$rows = $Cache->get_value('category_icon_content')){
		$rows = array();
		$res = sql_query("SELECT * FROM caticons ORDER BY id ASC");
		while($row = mysql_fetch_array($res)) {
			$rows[$row['id']] = $row;
		}
		$Cache->cache_value('category_icon_content', $rows, 156400);
	}
	return $rows[$typeid];
}
function get_category_row($catid = NULL)
{
	global $Cache;
	static $rows;
	if (!$rows && !$rows = $Cache->get_value('category_content')){
		$res = sql_query("SELECT categories.*, searchbox.name AS catmodename FROM categories LEFT JOIN searchbox ON categories.mode=searchbox.id");
		while($row = mysql_fetch_array($res)) {
			$rows[$row['id']] = $row;
		}
		$Cache->cache_value('category_content', $rows, 126400);
	}
	if ($catid) {
		return $rows[$catid];
	} else {
		return $rows;
	}
}

function get_second_icon($row, $catimgurl) //for CHDBits
{
	global $CURUSER, $Cache;
	$source=$row['source'];
	$medium=$row['medium'];
	$codec=$row['codec'];
	$standard=$row['standard'];
	$processing=$row['processing'];
	$team=$row['team'];
	$audiocodec=$row['audiocodec'];
	if (!$sirow = $Cache->get_value('secondicon_'.$source.'_'.$medium.'_'.$codec.'_'.$standard.'_'.$processing.'_'.$team.'_'.$audiocodec.'_content')){
		$res = sql_query("SELECT * FROM secondicons WHERE (source = ".sqlesc($source)." OR source=0) AND (medium = ".sqlesc($medium)." OR medium=0) AND (codec = ".sqlesc($codec)." OR codec = 0) AND (standard = ".sqlesc($standard)." OR standard = 0) AND (processing = ".sqlesc($processing)." OR processing = 0) AND (team = ".sqlesc($team)." OR team = 0) AND (audiocodec = ".sqlesc($audiocodec)." OR audiocodec = 0) LIMIT 1");
		$sirow = mysql_fetch_array($res);
		if (!$sirow)
			$sirow = 'not allowed';
		$Cache->cache_value('secondicon_'.$source.'_'.$medium.'_'.$codec.'_'.$standard.'_'.$processing.'_'.$team.'_'.$audiocodec.'_content', $sirow, 116400);
	}
	$catimgurl = get_cat_folder($row['category']);
	if ($sirow == 'not allowed')
		return "<img src=\"pic/cattrans.gif\" style=\"background-image: url(pic/". $catimgurl. "additional/notallowed.png);\" alt=\"" . $sirow["name"] . "\" alt=\"Not Allowed\" />";
	else {
		return "<img".($sirow['class_name'] ? " class=\"".$sirow['class_name']."\"" : "")." src=\"pic/cattrans.gif\" style=\"background-image: url(pic/". $catimgurl. "additional/". $sirow['image'].");\" alt=\"" . $sirow["name"] . "\" title=\"".$sirow['name']."\" />";
	}
}

function get_torrent_bg_color($promotion = 1)
{
	global $CURUSER;

	if ($CURUSER['appendpromotion'] == 'highlight'){
		$global_promotion_state = get_global_sp_state();
		if ($global_promotion_state == 1){
			if($promotion==1)
				$sphighlight = "";
			elseif($promotion==2)
				$sphighlight = " class='free_bg'";
			elseif($promotion==3)
				$sphighlight = " class='twoup_bg'";
			elseif($promotion==4)
				$sphighlight = " class='twoupfree_bg'";
			elseif($promotion==5)
				$sphighlight = " class='halfdown_bg'";
			elseif($promotion==6)
				$sphighlight = " class='twouphalfdown_bg'";
			elseif($promotion==7)
				$sphighlight = " class='thirtypercentdown_bg'";
			else $sphighlight = "";
		}
		elseif($global_promotion_state == 2)
			$sphighlight = " class='free_bg'";
		elseif($global_promotion_state == 3)
			$sphighlight = " class='twoup_bg'";
		elseif($global_promotion_state == 4)
			$sphighlight = " class='twoupfree_bg'";
		elseif($global_promotion_state == 5)
			$sphighlight = " class='halfdown_bg'";
		elseif($global_promotion_state == 6)
			$sphighlight = " class='twouphalfdown_bg'";
		elseif($global_promotion_state == 7)
			$sphighlight = " class='thirtypercentdown_bg'";
		else
			$sphighlight = "";
	}
	else $sphighlight = "";
	return $sphighlight;
}

function get_torrent_promotion_append($promotion = 1,$forcemode = "",$showtimeleft = false, $added = "", $promotionTimeType = 0, $promotionUntil = ''){
	global $CURUSER,$lang_functions;
	global $expirehalfleech_torrent, $expirefree_torrent, $expiretwoup_torrent, $expiretwoupfree_torrent, $expiretwouphalfleech_torrent, $expirethirtypercentleech_torrent;

	$sp_torrent = "";
	$onmouseover = "";
	if (get_global_sp_state() == 1) {
	switch ($promotion){
		case 2:
		{
			if ($showtimeleft && (($expirefree_torrent && $promotionTimeType == 0) || $promotionTimeType == 2))
			{
				if ($promotionTimeType == 2) {
					$futuretime = strtotime($promotionUntil);
				} else {
					$futuretime = strtotime($added) + $expirefree_torrent * 86400;
				}
				$timeout = gettime(date("Y-m-d H:i:s", $futuretime), false, false, true, false, true);
				if ($timeout)
				$onmouseover = " onmouseover=\"domTT_activate(this, event, 'content', '".htmlspecialchars("<b><font class=\"free\">".$lang_functions['text_free']."</font></b>".$lang_functions['text_will_end_in']."<b>".$timeout."</b>")."', 'trail', false, 'delay',500,'lifetime',3000,'fade','both','styleClass','niceTitle', 'fadeMax',87, 'maxWidth', 300);\"";
				else $promotion = 1;
			}
			break;
		}
		case 3:
		{
			if ($showtimeleft && (($expiretwoup_torrent && $promotionTimeType == 0) || $promotionTimeType == 2))
			{
				if ($promotionTimeType == 2) {
					$futuretime = strtotime($promotionUntil);
				} else {
					$futuretime = strtotime($added) + $expiretwoup_torrent * 86400;
				}
				$timeout = gettime(date("Y-m-d H:i:s", $futuretime), false, false, true, false, true);
				if ($timeout)
				$onmouseover = " onmouseover=\"domTT_activate(this, event, 'content', '".htmlspecialchars("<b><font class=\"twoup\">".$lang_functions['text_two_times_up']."</font></b>".$lang_functions['text_will_end_in']."<b>".$timeout."</b>")."', 'trail', false, 'delay',500,'lifetime',3000,'fade','both','styleClass','niceTitle', 'fadeMax',87, 'maxWidth', 300);\"";
				else $promotion = 1;
			}
			break;
		}
		case 4:
		{
			if ($showtimeleft && (($expiretwoupfree_torrent && $promotionTimeType == 0) || $promotionTimeType == 2))
			{
				if ($promotionTimeType == 2) {
					$futuretime = strtotime($promotionUntil);
				} else {
					$futuretime = strtotime($added) + $expiretwoupfree_torrent * 86400;
				}
				$timeout = gettime(date("Y-m-d H:i:s", $futuretime), false, false, true, false, true);
				if ($timeout)
				$onmouseover = " onmouseover=\"domTT_activate(this, event, 'content', '".htmlspecialchars("<b><font class=\"twoupfree\">".$lang_functions['text_free_two_times_up']."</font></b>".$lang_functions['text_will_end_in']."<b>".$timeout."</b>")."', 'trail', false, 'delay',500,'lifetime',3000,'fade','both','styleClass','niceTitle', 'fadeMax',87, 'maxWidth', 300);\"";
				else $promotion = 1;
			}
			break;
		}
		case 5:
		{
			if ($showtimeleft && (($expirehalfleech_torrent && $promotionTimeType == 0) || $promotionTimeType == 2))
			{
				if ($promotionTimeType == 2) {
					$futuretime = strtotime($promotionUntil);
				} else {
					$futuretime = strtotime($added) + $expirehalfleech_torrent * 86400;
				}
				$timeout = gettime(date("Y-m-d H:i:s", $futuretime), false, false, true, false, true);
				if ($timeout)
				$onmouseover = " onmouseover=\"domTT_activate(this, event, 'content', '".htmlspecialchars("<b><font class=\"halfdown\">".$lang_functions['text_half_down']."</font></b>".$lang_functions['text_will_end_in']."<b>".$timeout."</b>")."', 'trail', false, 'delay',500,'lifetime',3000,'fade','both','styleClass','niceTitle', 'fadeMax',87, 'maxWidth', 300);\"";
				else $promotion = 1;
			}
			break;
		}
		case 6:
		{
			if ($showtimeleft && (($expiretwouphalfleech_torrent && $promotionTimeType == 0) || $promotionTimeType == 2))
			{
				if ($promotionTimeType == 2) {
					$futuretime = strtotime($promotionUntil);
				} else {
					$futuretime = strtotime($added) + $expiretwouphalfleech_torrent * 86400;
				}
				$timeout = gettime(date("Y-m-d H:i:s", $futuretime), false, false, true, false, true);
				if ($timeout)
				$onmouseover = " onmouseover=\"domTT_activate(this, event, 'content', '".htmlspecialchars("<b><font class=\"twouphalfdown\">".$lang_functions['text_half_down_two_up']."</font></b>".$lang_functions['text_will_end_in']."<b>".$timeout."</b>")."', 'trail', false, 'delay',500,'lifetime',3000,'fade','both','styleClass','niceTitle', 'fadeMax',87, 'maxWidth', 300);\"";
				else $promotion = 1;
			}
			break;
		}
		case 7:
		{
			if ($showtimeleft && (($expirethirtypercentleech_torrent && $promotionTimeType == 0) || $promotionTimeType == 2))
			{
				if ($promotionTimeType == 2) {
					$futuretime = strtotime($promotionUntil);
				} else {
					$futuretime = strtotime($added) + $expirethirtypercentleech_torrent * 86400;
				}
				$timeout = gettime(date("Y-m-d H:i:s", $futuretime), false, false, true, false, true);
				if ($timeout)
				$onmouseover = " onmouseover=\"domTT_activate(this, event, 'content', '".htmlspecialchars("<b><font class=\"thirtypercent\">".$lang_functions['text_thirty_percent_down']."</font></b>".$lang_functions['text_will_end_in']."<b>".$timeout."</b>")."', 'trail', false, 'delay',500,'lifetime',3000,'fade','both','styleClass','niceTitle', 'fadeMax',87, 'maxWidth', 300);\"";
				else $promotion = 1;
			}
			break;
		}
	}
	}
	if (($CURUSER['appendpromotion'] == 'word' && $forcemode == "" ) || $forcemode == 'word'){
		if(($promotion==2 && get_global_sp_state() == 1) || get_global_sp_state() == 2){
			$sp_torrent = " <b>[<font class='free' ".$onmouseover.">".$lang_functions['text_free']."</font>]</b>";
		}
		elseif(($promotion==3 && get_global_sp_state() == 1) || get_global_sp_state() == 3){
			$sp_torrent = " <b>[<font class='twoup' ".$onmouseover.">".$lang_functions['text_two_times_up']."</font>]</b>";
		}
		elseif(($promotion==4 && get_global_sp_state() == 1) || get_global_sp_state() == 4){
			$sp_torrent = " <b>[<font class='twoupfree' ".$onmouseover.">".$lang_functions['text_free_two_times_up']."</font>]</b>";
		}
		elseif(($promotion==5 && get_global_sp_state() == 1) || get_global_sp_state() == 5){
			$sp_torrent = " <b>[<font class='halfdown' ".$onmouseover.">".$lang_functions['text_half_down']."</font>]</b>";
		}
		elseif(($promotion==6 && get_global_sp_state() == 1) || get_global_sp_state() == 6){
			$sp_torrent = " <b>[<font class='twouphalfdown' ".$onmouseover.">".$lang_functions['text_half_down_two_up']."</font>]</b>";
		}
		elseif(($promotion==7 && get_global_sp_state() == 1) || get_global_sp_state() == 7){
			$sp_torrent = " <b>[<font class='thirtypercent' ".$onmouseover.">".$lang_functions['text_thirty_percent_down']."</font>]</b>";
		}
	}
	elseif (($CURUSER['appendpromotion'] == 'icon' && $forcemode == "") || $forcemode == 'icon'){
		if(($promotion==2 && get_global_sp_state() == 1) || get_global_sp_state() == 2)
			$sp_torrent = " <img class=\"pro_free\" src=\"pic/trans.gif\" alt=\"Free\" ".($onmouseover ? $onmouseover : "title=\"".$lang_functions['text_free']."\"")." />";
		elseif(($promotion==3 && get_global_sp_state() == 1) || get_global_sp_state() == 3)
			$sp_torrent = " <img class=\"pro_2up\" src=\"pic/trans.gif\" alt=\"2X\" ".($onmouseover ? $onmouseover : "title=\"".$lang_functions['text_two_times_up']."\"")." />";
		elseif(($promotion==4 && get_global_sp_state() == 1) || get_global_sp_state() == 4)
			$sp_torrent = " <img class=\"pro_free2up\" src=\"pic/trans.gif\" alt=\"2X Free\" ".($onmouseover ? $onmouseover : "title=\"".$lang_functions['text_free_two_times_up']."\"")." />";
		elseif(($promotion==5 && get_global_sp_state() == 1) || get_global_sp_state() == 5)
			$sp_torrent = " <img class=\"pro_50pctdown\" src=\"pic/trans.gif\" alt=\"50%\" ".($onmouseover ? $onmouseover : "title=\"".$lang_functions['text_half_down']."\"")." />";
		elseif(($promotion==6 && get_global_sp_state() == 1) || get_global_sp_state() == 6)
			$sp_torrent = " <img class=\"pro_50pctdown2up\" src=\"pic/trans.gif\" alt=\"2X 50%\" ".($onmouseover ? $onmouseover : "title=\"".$lang_functions['text_half_down_two_up']."\"")." />";
		elseif(($promotion==7 && get_global_sp_state() == 1) || get_global_sp_state() == 7)
			$sp_torrent = " <img class=\"pro_30pctdown\" src=\"pic/trans.gif\" alt=\"30%\" ".($onmouseover ? $onmouseover : "title=\"".$lang_functions['text_thirty_percent_down']."\"")." />";
	}
	return $sp_torrent;
}

function get_user_id_from_name($username){
	global $lang_functions;
	$res = sql_query("SELECT id FROM users WHERE LOWER(username)=LOWER(" . sqlesc($username).")");
	$arr = mysql_fetch_array($res);
	if (!$arr){
		stderr($lang_functions['std_error'],$lang_functions['std_no_user_named']."'".$username."'");
	}
	else return $arr['id'];
}

function is_forum_moderator($id, $in = 'post'){
	global $CURUSER;
	switch($in){
		case 'post':{
			$res = sql_query("SELECT topicid FROM posts WHERE id=$id") or sqlerr(__FILE__, __LINE__);
			if ($arr = mysql_fetch_array($res)){
				if (is_forum_moderator($arr['topicid'],'topic'))
					return true;
			}
			return false;
			break;
		}
		case 'topic':{
			$modcount = sql_query("SELECT COUNT(forummods.userid) FROM forummods LEFT JOIN topics ON forummods.forumid = topics.forumid WHERE topics.id=$id AND forummods.userid=".sqlesc($CURUSER['id'])) or sqlerr(__FILE__, __LINE__);
			$arr = mysql_fetch_array($modcount);
			if ($arr[0])
				return true;
			else return false;
			break;
		}
		case 'forum':{
			$modcount = get_row_count("forummods","WHERE forumid=$id AND userid=".sqlesc($CURUSER['id']));
			if ($modcount)
				return true;
			else return false;
			break;
		}
		default: {
		return false;
		}
	}
}

function get_guest_lang_id(){
	global $CURLANGDIR;
	$langfolder=$CURLANGDIR;
	$res = sql_query("SELECT id FROM language WHERE site_lang_folder=".sqlesc($langfolder)." AND site_lang=1");
	$row = mysql_fetch_array($res);
	if ($row){
		return $row['id'];
	}
	else return 6;//return English
}

function set_forum_moderators($name, $forumid, $limit=3){
	$name = rtrim(trim($name), ",");
	$users = explode(",", $name);
	$userids = array();
	foreach ($users as $user){
		$userids[]=get_user_id_from_name(trim($user));
	}
	$max = count($userids);
	sql_query("DELETE FROM forummods WHERE forumid=".sqlesc($forumid)) or sqlerr(__FILE__, __LINE__);
	for($i=0; $i < $limit && $i < $max; $i++){
		sql_query("INSERT INTO forummods (forumid, userid) VALUES (".sqlesc($forumid).",".sqlesc($userids[$i]).")") or sqlerr(__FILE__, __LINE__);
	}
}

function get_plain_username($id){
	$row = get_user_row($id);	
	if ($row)
		$username = $row['username'];
	else $username = "";
	return $username;
}

function get_searchbox_value($mode = 1, $item = 'showsubcat'){
	global $Cache;
	static $rows;
	if (!$rows && !$rows = $Cache->get_value('searchbox_content')){
		$rows = array();
		$res = sql_query("SELECT * FROM searchbox ORDER BY id ASC");
		while ($row = mysql_fetch_array($res)) {
			$rows[$row['id']] = $row;
		}
		$Cache->cache_value('searchbox_content', $rows, 100500);
	}
	return $rows[$mode][$item];
}

function get_ratio($userid, $html = true){
	global $lang_functions;
	$row = get_user_row($userid);
	$uped = $row['uploaded'];
	$downed = $row['downloaded'];
	if ($html == true){
		if ($downed > 0)
		{
			$ratio = $uped / $downed;
			$color = get_ratio_color($ratio);
			$ratio = number_format($ratio, 3);

			if ($color)
				$ratio = "<font color=\"".$color."\">".$ratio."</font>";
		}
		elseif ($uped > 0)
			$ratio = $lang_functions['text_inf'];
		else
			$ratio = "---";
	}
	else{
		if ($downed > 0)
		{
			$ratio = $uped / $downed;
		}
		else $ratio = 1;
	}
	return $ratio;
}

function add_s($num, $es = false)
{
	global $lang_functions;
	return ($num > 1 ? ($es ? $lang_functions['text_es'] : $lang_functions['text_s']) : "");
}

function is_or_are($num)
{
	global $lang_functions;
	return ($num > 1 ? $lang_functions['text_are'] : $lang_functions['text_is']);
}

function getmicrotime(){
	list($usec, $sec) = explode(" ",microtime());
	return ((float)$usec + (float)$sec);
}

function get_user_class_image($class){
	$UC = array(
		"Staff Leader" => "pic/staffleader.gif",
		"SysOp" => "pic/sysop.gif",
		"Administrator" => "pic/administrator.gif",
		"Moderator" => "pic/moderator.gif",
		"Forum Moderator" => "pic/forummoderator.gif",
		"Uploader" => "pic/uploader.gif",
		"Retiree" => "pic/retiree.gif",
		"VIP" => "pic/vip.gif",
		"Nexus Master" => "pic/nexus.gif",
		"Ultimate User" => "pic/ultimate.gif",
		"Extreme User" => "pic/extreme.gif",
		"Veteran User" => "pic/veteran.gif",
		"Insane User" => "pic/insane.gif",
		"Crazy User" => "pic/crazy.gif",
		"Elite User" => "pic/elite.gif",
		"Power User" => "pic/power.gif",
		"User" => "pic/user.gif",
		"Peasant" => "pic/peasant.gif"
	);
	if (isset($class))
		$uclass = $UC[get_user_class_name($class,false,false,false)];
	else $uclass = "pic/banned.gif";
	return $uclass;
}

function user_can_upload($where = "torrents"){
	global $CURUSER,$upload_class,$enablespecial,$uploadspecial_class;

	if ($CURUSER["uploadpos"] != 'yes')
		return false;
	if ($where == "torrents")
	{
		if (get_user_class() >= $upload_class)
			return true;
		if (get_if_restricted_is_open())
			return true;
	}
	if ($where == "music")
	{
		if ($enablespecial == 'yes' && get_user_class() >= $uploadspecial_class)
			return true;
	}
	return false;
}

function torrent_selection($name,$selname,$listname,$selectedid = 0)
{
	global $lang_functions;
	$selection = "<b>".$name."</b>&nbsp;<select name=\"".$selname."\">\n<option value=\"0\">".$lang_functions['select_choose_one']."</option>\n";
	$listarray = searchbox_item_list($listname);
	foreach ($listarray as $row)
		$selection .= "<option value=\"" . $row["id"] . "\"". ($row["id"]==$selectedid ? " selected=\"selected\"" : "").">" . htmlspecialchars($row["name"]) . "</option>\n";
	$selection .= "</select>&nbsp;&nbsp;&nbsp;\n";
	return $selection;
}

function get_hl_color($color=0)
{
	switch ($color){
		case 0: return false;
		case 1: return "Black";
		case 2: return "Sienna";
		case 3: return "DarkOliveGreen";
		case 4: return "DarkGreen";
		case 5: return "DarkSlateBlue";
		case 6: return "Navy";
		case 7: return "Indigo";
		case 8: return "DarkSlateGray";
		case 9: return "DarkRed";
		case 10: return "DarkOrange";
		case 11: return "Olive";
		case 12: return "Green";
		case 13: return "Teal";
		case 14: return "Blue";
		case 15: return "SlateGray";
		case 16: return "DimGray";
		case 17: return "Red";
		case 18: return "SandyBrown";
		case 19: return "YellowGreen";
		case 20: return "SeaGreen";
		case 21: return "MediumTurquoise";
		case 22: return "RoyalBlue";
		case 23: return "Purple";
		case 24: return "Gray";
		case 25: return "Magenta";
		case 26: return "Orange";
		case 27: return "Yellow";
		case 28: return "Lime";
		case 29: return "Cyan";
		case 30: return "DeepSkyBlue";
		case 31: return "DarkOrchid";
		case 32: return "Silver";
		case 33: return "Pink";
		case 34: return "Wheat";
		case 35: return "LemonChiffon";
		case 36: return "PaleGreen";
		case 37: return "PaleTurquoise";
		case 38: return "LightBlue";
		case 39: return "Plum";
		case 40: return "White";
		default: return false;
	}
}

function get_forum_moderators($forumid, $plaintext = true)
{
	global $Cache;
	static $moderatorsArray;

	if (!$moderatorsArray && !$moderatorsArray = $Cache->get_value('forum_moderator_array')) {
		$moderatorsArray = array();
		$res = sql_query("SELECT forumid, userid FROM forummods ORDER BY forumid ASC") or sqlerr(__FILE__, __LINE__);
		while ($row = mysql_fetch_array($res)) {
			$moderatorsArray[$row['forumid']][] = $row['userid'];
		}
		$Cache->cache_value('forum_moderator_array', $moderatorsArray, 86200);
	}
	$ret = (array)$moderatorsArray[$forumid];

	$moderators = "";
	foreach($ret as $userid) {
		if ($plaintext)
			$moderators .= get_plain_username($userid).", ";
		else $moderators .= get_username($userid).", ";
	}
	$moderators = rtrim(trim($moderators), ",");
	return $moderators;
}
function key_shortcut($page=1,$pages=1)
{
	$currentpage = "var currentpage=".$page.";";
	$maxpage = "var maxpage=".$pages.";";
	$key_shortcut_block = "\n<script type=\"text/javascript\">\n//<![CDATA[\n".$maxpage."\n".$currentpage."\n//]]>\n</script>\n";
	return $key_shortcut_block;
}
function promotion_selection($selected = 0, $hide = 0)
{
	global $lang_functions;
	$selection = "";
	if ($hide != 1)
		$selection .= "<option value=\"1\"".($selected == 1 ? " selected=\"selected\"" : "").">".$lang_functions['text_normal']."</option>";
	if ($hide != 2)
		$selection .= "<option value=\"2\"".($selected == 2 ? " selected=\"selected\"" : "").">".$lang_functions['text_free']."</option>";
	if ($hide != 3)
		$selection .= "<option value=\"3\"".($selected == 3 ? " selected=\"selected\"" : "").">".$lang_functions['text_two_times_up']."</option>";
	if ($hide != 4)
		$selection .= "<option value=\"4\"".($selected == 4 ? " selected=\"selected\"" : "").">".$lang_functions['text_free_two_times_up']."</option>";
	if ($hide != 5)
		$selection .= "<option value=\"5\"".($selected == 5 ? " selected=\"selected\"" : "").">".$lang_functions['text_half_down']."</option>";
	if ($hide != 6)
		$selection .= "<option value=\"6\"".($selected == 6 ? " selected=\"selected\"" : "").">".$lang_functions['text_half_down_two_up']."</option>";
	if ($hide != 7)
		$selection .= "<option value=\"7\"".($selected == 7 ? " selected=\"selected\"" : "").">".$lang_functions['text_thirty_percent_down']."</option>";
	return $selection;
}

function get_post_row($postid)
{
	global $Cache;
	if (!$row = $Cache->get_value('post_'.$postid.'_content')){
		$res = sql_query("SELECT * FROM posts WHERE id=".sqlesc($postid)." LIMIT 1") or sqlerr(__FILE__,__LINE__);
		$row = mysql_fetch_array($res);
		$Cache->cache_value('post_'.$postid.'_content', $row, 7200);
	}
	if (!$row)
		return false;
	else return $row;
}

function get_country_row($id)
{
	global $Cache;
	if (!$row = $Cache->get_value('country_'.$id.'_content')){
		$res = sql_query("SELECT * FROM countries WHERE id=".sqlesc($id)." LIMIT 1") or sqlerr(__FILE__,__LINE__);
		$row = mysql_fetch_array($res);
		$Cache->cache_value('country_'.$id.'_content', $row, 86400);
	}
	if (!$row)
		return false;
	else return $row;
}

function get_downloadspeed_row($id)
{
	global $Cache;
	if (!$row = $Cache->get_value('downloadspeed_'.$id.'_content')){
		$res = sql_query("SELECT * FROM downloadspeed WHERE id=".sqlesc($id)." LIMIT 1") or sqlerr(__FILE__,__LINE__);
		$row = mysql_fetch_array($res);
		$Cache->cache_value('downloadspeed_'.$id.'_content', $row, 86400);
	}
	if (!$row)
		return false;
	else return $row;
}

function get_uploadspeed_row($id)
{
	global $Cache;
	if (!$row = $Cache->get_value('uploadspeed_'.$id.'_content')){
		$res = sql_query("SELECT * FROM uploadspeed WHERE id=".sqlesc($id)." LIMIT 1") or sqlerr(__FILE__,__LINE__);
		$row = mysql_fetch_array($res);
		$Cache->cache_value('uploadspeed_'.$id.'_content', $row, 86400);
	}
	if (!$row)
		return false;
	else return $row;
}

function get_isp_row($id)
{
	global $Cache;
	if (!$row = $Cache->get_value('isp_'.$id.'_content')){
		$res = sql_query("SELECT * FROM isp WHERE id=".sqlesc($id)." LIMIT 1") or sqlerr(__FILE__,__LINE__);
		$row = mysql_fetch_array($res);
		$Cache->cache_value('isp_'.$id.'_content', $row, 86400);
	}
	if (!$row)
		return false;
	else return $row;
}

function valid_file_name($filename)
{
	$allowedchars = "abcdefghijklmnopqrstuvwxyz0123456789_./";

	$total=strlen($filename);
	for ($i = 0; $i < $total; ++$i)
	if (strpos($allowedchars, $filename[$i]) === false)
		return false;
	return true;
}

function valid_class_name($filename)
{
	$allowedfirstchars = "abcdefghijklmnopqrstuvwxyz";
	$allowedchars = "abcdefghijklmnopqrstuvwxyz0123456789_";

	if(strpos($allowedfirstchars, $filename[0]) === false)
		return false;
	$total=strlen($filename);
	for ($i = 1; $i < $total; ++$i)
	if (strpos($allowedchars, $filename[$i]) === false)
		return false;
	return true;
}

function return_avatar_image($url)
{
	global $CURLANGDIR;
	return "<img src=\"".$url."\" alt=\"avatar\" width=\"150px\" onload=\"check_avatar(this, '".$CURLANGDIR."');\" />";
}
function return_category_image($categoryid, $link="")
{
	static $catImg = array();
	if ($catImg[$categoryid]) {
		$catimg = $catImg[$categoryid];
	} else {
		$categoryrow = get_category_row($categoryid);
		$catimgurl = get_cat_folder($categoryid);
		$catImg[$categoryid] = $catimg = "<img".($categoryrow['class_name'] ? " class=\"".$categoryrow['class_name']."\"" : "")." src=\"pic/cattrans.gif\" alt=\"" . $categoryrow["name"] . "\" title=\"" .$categoryrow["name"]. "\" style=\"background-image: url(pic/" . $catimgurl . $categoryrow["image"].");\" />";
	}
	if ($link) {
		$catimg = "<a href=\"".$link."cat=" . $categoryid . "\">".$catimg."</a>";
	}
	return $catimg;
}
?>

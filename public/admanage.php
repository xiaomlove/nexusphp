<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();

if (get_user_class() < UC_MODERATOR)
    permissiondenied();
$allowxhtmlclass = UC_ADMINISTRATOR;
function get_position_name($position)
{
	global $lang_admanage;
	switch ($position)
	{
		case 'header':
			$name = $lang_admanage['text_header'];
			break;
		case 'footer':
			$name = $lang_admanage['text_footer'];
			break;
		case 'belownav':
			$name = $lang_admanage['text_below_navigation'];
			break;
		case 'belowsearchbox':
			$name = $lang_admanage['text_below_searchbox'];
			break;
		case 'torrentdetail':
			$name = $lang_admanage['text_torrent_detail'];
			break;
		case 'comment':
			$name = $lang_admanage['text_comment_page'];
			break;
		case 'interoverforums':
			$name = $lang_admanage['text_inter_overforums'];
			break;
		case 'forumpost':
			$name = $lang_admanage['text_forum_post_page'];
			break;
		case 'popup':
			$name = $lang_admanage['text_popup'];
			break;
	}
	return $name;
}
function get_type_name($type)
{
	global $lang_admanage;
	switch ($type)
	{
		case 'bbcodes':
			$name = $lang_admanage['text_bbcodes'];
			break;
		case 'xhtml':
			$name = $lang_admanage['text_xhtml'];
			break;
		case 'text':
			$name = $lang_admanage['text_text'];
			break;
		case 'image':
			$name = $lang_admanage['text_image'];
			break;
		case 'flash':
			$name = $lang_admanage['text_flash'];
			break;
	}
	return $name;
}
function print_ad_editor($position, $row = "")
{
	global $lang_admanage;
	global $allowxhtmlclass;
	switch ($position)
	{
		case 'header':
			$note = $lang_admanage['text_header_note'];
			break;
		case 'footer':
			$note = $lang_admanage['text_footer_note'];
			break;
		case 'belownav':
			$note = $lang_admanage['text_below_navigation_note'];
			break;
		case 'belowsearchbox':
			$note = $lang_admanage['text_below_searchbox_note'];
			break;
		case 'torrentdetail':
			$note = $lang_admanage['text_torrent_detail_note'];
			break;
		case 'comment':
			$note = $lang_admanage['text_comment_page_note'];
			break;
		case 'interoverforums':
			$note = $lang_admanage['text_inter_overforums_note'];
			break;
		case 'forumpost':
			$note = $lang_admanage['text_forum_post_page_note'];
			break;
		case 'popup':
			$note = $lang_admanage['text_popup_note'];
			break;
	}
	if ($row)
	{
		$pararow = @unserialize($row['parameters']);
		$name = $row['name'];
		$starttime = $row['starttime'];
		$endtime = $row['endtime'];
		$displayorder = $row['displayorder'];
		$enabled = $row['enabled'];
		$type = $row['type'];
	}
	else
	{
		$name = "";
		$starttime = "";
		$endtime = "";
		$displayorder = 0;
		$enabled = 1;
		$type = 'image';
	}
?>
<div style="width: 940px">
<h1 align="center"><a class="faqlink" href="admanage.php"><?php echo $lang_admanage['text_ad']?></a> - <?php echo get_position_name($position)?></h1>
<div><p align="center"><?php echo $note?></p></div>
<h2 align="left"><?php echo $lang_admanage['text_ad_detail']?></h2>
<table border="1" cellspacing="0" cellpadding="10" width="100%">
<?php
tr($lang_admanage['row_name']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"ad[name]\" value=\"".htmlspecialchars($name)."\" style=\"width: 300px\" /> " . $lang_admanage['text_name_note'], 1);
tr($lang_admanage['row_start_time']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"ad[starttime]\" value=\"".$starttime."\" style=\"width: 300px\" /> " . $lang_admanage['text_start_time_note'], 1);
tr($lang_admanage['row_end_time']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"ad[endtime]\" value=\"".$endtime."\" style=\"width: 300px\" /> ".$lang_admanage['text_end_time_note'], 1);
tr($lang_admanage['row_order'], "<input type=\"text\" name=\"ad[displayorder]\" value=\"".$displayorder."\" style=\"width: 100px\" /> ".$lang_admanage['text_order_note'], 1);
tr($lang_admanage['row_enabled']."<font color=\"red\">*</font>", "<input type=\"radio\" name=\"ad[enabled]\"".($enabled ? " checked=\"checked\"" : "")." value=\"1\" />".$lang_admanage['text_yes']."<input type=\"radio\" name=\"ad[enabled]\"".($enabled ? "" : " checked=\"checked\"")." value=\"0\" />".$lang_admanage['text_no']."<br />".$lang_admanage['text_enabled_note'], 1);
tr($lang_admanage['row_type']."<font color=\"red\">*</font>", "<select name=\"ad[type]\" onchange=\"var key, types; types=new Array('image','text','bbcodes','xhtml','flash'); for(key in types){var obj=$('type_'+types[key]); obj.style.display=types[key]==this.options[this.selectedIndex].value?'':'none';}\"><option value=\"image\"".($type == 'image' ? " selected=\"selected\"" : "").">".$lang_admanage['text_image']."</option><option value=\"text\"".($type == 'text' ? " selected=\"selected\"" : "").">".$lang_admanage['text_text']."</option><option value=\"bbcodes\"".($type == 'bbcodes' ? " selected=\"selected\"" : "").">".$lang_admanage['text_bbcodes']."</option>".(get_user_class() >= $allowxhtmlclass ? "<option value=\"xhtml\"".($type == 'xhtml' ? " selected=\"selected\"" : "").">".$lang_admanage['text_xhtml']."</option>" : "")."<option value=\"flash\"".($type == 'flash' ? " selected=\"selected\"" : "").">".$lang_admanage['text_flash']."</option></select> ".$lang_admanage['text_type_note'], 1);
?>
</table>
<div id="type_image"<?php echo $type == 'image' ? "" : " style=\"display: none;\""?>>
<h2 align="left"><?php echo $lang_admanage['text_image']?></h2>
<table border="1" cellspacing="0" cellpadding="10" width="100%">
<?php
tr($lang_admanage['row_image_url']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"ad[image][url]\"".($type == 'image' ? " value=\"".($pararow['url'] ?? '')."\"" : "")." style=\"width: 300px\" /> ".$lang_admanage['text_image_url_note'], 1);
tr($lang_admanage['row_image_link']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"ad[image][link]\"".($type == 'image' ? " value=\"".($pararow['link'] ?? '')."\"" : "")." style=\"width: 300px\" /> ".$lang_admanage['text_image_link_note'], 1);
tr($lang_admanage['row_image_width'], "<input type=\"text\" name=\"ad[image][width]\"".($type == 'image' ? " value=\"".($pararow['width'] ?? '')."\"" : "")." style=\"width: 100px\" /> ".$lang_admanage['text_image_width_note'], 1);
tr($lang_admanage['row_image_height'], "<input type=\"text\" name=\"ad[image][height]\"".($type == 'image' ? " value=\"".($pararow['height'] ?? '')."\"" : "")." style=\"width: 100px\" /> ".$lang_admanage['text_image_height_note'], 1);
tr($lang_admanage['row_image_tooltip'], "<input type=\"text\" name=\"ad[image][title]\"".($type == 'image' ? " value=\"".($pararow['title'] ?? '')."\"" : "")." style=\"width: 300px\" /> ".$lang_admanage['text_image_tooltip_note'], 1);
?>
</table>
</div>
<div id="type_text"<?php echo $type == 'text' ? "" : " style=\"display: none;\""?>>
<h2 align="left"><?php echo $lang_admanage['text_text']?></h2>
<table border="1" cellspacing="0" cellpadding="10" width="100%">
<?php
tr($lang_admanage['row_text_content']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"ad[text][content]\"".($type == 'text' ? " value=\"".$pararow['content']."\"" : "")." style=\"width: 300px\" /> ".$lang_admanage['text_text_content_note'], 1);
tr($lang_admanage['row_text_link']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"ad[text][link]\"".($type == 'text' ? " value=\"".$pararow['link']."\"" : "")." style=\"width: 300px\" /> ".$lang_admanage['text_text_link_note'], 1);
tr($lang_admanage['row_text_size'], "<input type=\"text\" name=\"ad[text][size]\"".($type == 'text' ? " value=\"".$pararow['size']."\"" : "")." style=\"width: 100px\" /> ".$lang_admanage['text_text_size_note'], 1);
?>
</table>
</div>
<div id="type_bbcodes"<?php echo $type == 'bbcodes' ? "" : " style=\"display: none;\""?>>
<h2 align="left"><?php echo $lang_admanage['text_bbcodes']?></h2>
<table border="1" cellspacing="0" cellpadding="10" width="100%">
<?php
tr($lang_admanage['row_bbcodes_code']."<font color=\"red\">*</font>", "<textarea name=\"ad[bbcodes][code]\" cols=\"50\" rows=\"6\" style=\"width: 300px\">".($type == 'bbcodes' ? $pararow['code'] : "")."</textarea><br />".$lang_admanage['text_bbcodes_code_note']."<a class=\"altlink\" href=\"tags.php\"><b>".$lang_admanage['text_here']."</b></a>", 1);
?>
</table>
</div>
<div id="type_xhtml"<?php echo $type == 'xhtml' ? "" : " style=\"display: none;\""?>>
<h2 align="left"><?php echo $lang_admanage['text_xhtml']?></h2>
<table border="1" cellspacing="0" cellpadding="10" width="100%">
<?php
tr($lang_admanage['row_xhtml_code']."<font color=\"red\">*</font>", "<textarea name=\"ad[xhtml][code]\" cols=\"50\" rows=\"6\" style=\"width: 300px\">".($type == 'xhtml' ? $pararow['code'] : "")."</textarea><br />".$lang_admanage['text_xhmtl_code_note'], 1);
?>
</table>
</div>
<div id="type_flash"<?php echo $type == 'flash' ? "" : " style=\"display: none;\""?>>
<h2 align="left"><?php echo $lang_admanage['text_flash']?></h2>
<table border="1" cellspacing="0" cellpadding="10" width="100%">
<?php
tr($lang_admanage['row_flash_url']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"ad[flash][url]\"".($type == 'flash' ? " value=\"".$pararow['url']."\"" : "")." style=\"width: 300px\" /> ".$lang_admanage['text_flash_url_note'], 1);
tr($lang_admanage['row_flash_width']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"ad[flash][width]\"".($type == 'flash' ? " value=\"".$pararow['width']."\"" : "")." style=\"width: 100px\" /> ".$lang_admanage['text_flash_width_note'], 1);
tr($lang_admanage['row_flash_height']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"ad[flash][height]\"".($type == 'flash' ? " value=\"".$pararow['height']."\"" : "")." style=\"width: 100px\" /> ".$lang_admanage['text_flash_height_note'], 1);
?>
</table>
</div>
<div style="text-align: center; margin-top: 10px;">
<input type="submit" value="<?php echo $lang_admanage['submit_submit']?>" />
</div>
</div>
<?php
}

$action = $_GET['action'] ?? '';
if ($action == 'del')
{
	$id = intval($_GET['id'] ?? 0);
	if (!$id)
	{
		stderr($lang_admanage['std_error'], $lang_admanage['std_invalid_id']);
	}
	$res = sql_query ("SELECT * FROM advertisements WHERE id = ".sqlesc($id)." LIMIT 1");
	if ($row = mysql_fetch_array($res))
		sql_query("DELETE FROM advertisements WHERE id = ".sqlesc($row['id'])) or sqlerr(__FILE__, __LINE__);
	$Cache->delete_value('current_ad_array', false);
	header("Location: ".get_protocol_prefix() . $BASEURL."/admanage.php");
	die();
}
elseif ($action == 'edit')
{
	$id = intval($_GET['id'] ?? 0);
	if (!$id)
	{
		stderr($lang_admanage['std_error'], $lang_admanage['std_invalid_id']);
	}
	else
	{
		$res = sql_query("SELECT * FROM advertisements WHERE id = ".sqlesc($id)." LIMIT 1");
		if (!$row = mysql_fetch_array($res))
			stderr($lang_admanage['std_error'], $lang_admanage['std_invalid_id']);
		else
		{
			$position = $row['position'];
			stdhead($lang_admanage['head_edit_ad']);
			print("<form method=\"post\" action=\"?action=submit&amp;position=".$position."\">");
			print("<input type=\"hidden\" name=\"isedit\" value=\"1\" />");
			print("<input type=\"hidden\" name=\"id\" value=\"".$id."\" />");
			print_ad_editor($position, $row);
			print("</form>");
			stdfoot();
		}
	}
}
elseif ($action == 'add')
{
	$position = $_GET['position'];
	$validpos = array('header', 'footer', 'belownav', 'belowsearchbox', 'torrentdetail', 'comment', 'interoverforums', 'forumpost', 'popup');
	if (!in_array($position, $validpos))
		stderr($lang_admanage['std_error'], $lang_admanage['std_invalid_position']);
	else
	{
		stdhead($lang_admanage['head_add_ad']);
		print("<form method=\"post\" action=\"?action=submit&amp;position=".htmlspecialchars($position)."\">");
		print("<input type=\"hidden\" name=\"isedit\" value=\"0\" />");
		print_ad_editor($position);
		print("</form>");
		stdfoot();
	}
}
elseif ($action == 'submit')
{
	$position = $_GET['position'];
	$validpos = array('header', 'footer', 'belownav', 'belowsearchbox', 'torrentdetail', 'comment', 'interoverforums', 'forumpost', 'popup');
	if (!in_array($position, $validpos))
		stderr($lang_admanage['std_error'], $lang_admanage['std_invalid_position']);
	else
	{
		if ($_POST['isedit']){
			$id = intval($_POST['id'] ?? 0);
			if (!$id)
			{
				stderr($lang_admanage['std_error'], $lang_admanage['std_invalid_id']);
			}
			else
			{
				$adid = $id;
				$res = sql_query("SELECT * FROM advertisements WHERE id = ".sqlesc($id)." LIMIT 1");
				if (!$row = mysql_fetch_array($res))
					stderr($lang_admanage['std_error'], $lang_admanage['std_invalid_id']);
			}
		}
		else
		{
			$res = sql_query("SELECT id FROM advertisements ORDER BY id DESC LIMIT 1");
			$row = mysql_fetch_array($res);
			if (!$row)
				$adid = 1;
			else $adid = $row['id']+1;
		}
		$name = $_POST['ad']['name'];
		$starttime = $_POST['ad']['starttime'];
		$endtime = $_POST['ad']['endtime'];
		$displayorder = intval($_POST['ad']['displayorder'] ?? 0);
		$enabled = intval($_POST['ad']['enabled'] ?? 0);
		$type = $_POST['ad']['type'];
		if (!$name || !$type || !$starttime || !$endtime)
		{
			stderr($lang_admanage['std_error'], $lang_admanage['std_missing_form_data']);
		}
		if (get_user_class() >= $allowxhtmlclass)
			$validtype = array('bbcodes', 'xhtml', 'text', 'image', 'flash');
		else
			$validtype = array('bbcodes', 'text', 'image', 'flash');
		if (!in_array($type, $validtype))
			stderr($lang_admanage['std_error'], $lang_admanage['std_invalid_type']);
		switch ($type)
		{
			case 'bbcodes':
				if (!$_POST['ad']['bbcodes']['code'])
					stderr($lang_admanage['std_error'], $lang_admanage['std_missing_form_data']);
				$parameters = serialize($_POST['ad']['bbcodes']);
				$code = format_comment($_POST['ad']['bbcodes']['code'], true, false, true, true, 700, true, true, -1, 0, $adid);
				break;
			case 'xhtml':
				if (!$_POST['ad']['xhtml']['code'])
					stderr($lang_admanage['std_error'], $lang_admanage['std_missing_form_data']);
				$parameters = serialize($_POST['ad']['xhtml']);
				$code = $_POST['ad']['xhtml']['code'];
				break;
			case 'text':
				if (!$_POST['ad']['text']['content'] || !$_POST['ad']['text']['link'])
					stderr($lang_admanage['std_error'], $lang_admanage['std_missing_form_data']);
				$parameters = serialize($_POST['ad']['text']);
				$content = htmlspecialchars($_POST['ad']['text']['content']);
				if ($_POST['ad']['text']['size'])
					$content = "<span style=\"font-size: ".htmlspecialchars($_POST['ad']['text']['size'])."\">".$content."</span>";
				else
					$content = "<span style=\"font-size: 30pt\">".$content."</span>";
				$code = "<a href=\"adredir.php?id=".$adid."&amp;url=".rawurlencode(htmlspecialchars($_POST['ad']['text']['link']))."\" target=\"_blank\">".$content."</a>";
				break;
			case 'image':
				if (!$_POST['ad']['image']['url'] || !$_POST['ad']['image']['link'])
					stderr($lang_admanage['std_error'], $lang_admanage['std_missing_form_data']);
				$_POST['ad']['image']['width'] = intval($_POST['ad']['image']['width'] ?? 0);
				$_POST['ad']['image']['height'] = intval($_POST['ad']['image']['height'] ?? 0);
				$parameters = serialize($_POST['ad']['image']);
				$imgadd = "";
				if ($_POST['ad']['image']['width'])
					$imgadd .= " width=\"".$_POST['ad']['image']['width']."\"";
				if ($_POST['ad']['image']['height'])
					$imgadd .= " height=\"".$_POST['ad']['image']['height']."\"";
				if ($_POST['ad']['image']['title'])
					$imgadd .= " title=\"".$_POST['ad']['image']['title']."\"";
				$code = "<a href=\"adredir.php?id=".$adid."&amp;url=".rawurlencode(htmlspecialchars($_POST['ad']['image']['link']))."\" target=\"_blank\"><img border=\"0\" src=\"".htmlspecialchars($_POST['ad']['image']['url'])."\"".$imgadd." alt=\"ad\" /></a>";
				break;
			case 'flash':
				$_POST['ad']['flash']['width'] = intval($_POST['ad']['flash']['width'] ?? 0);
				$_POST['ad']['flash']['height'] = intval($_POST['ad']['flash']['height'] ?? 0);
				if (!$_POST['ad']['flash']['url'] || !$_POST['ad']['flash']['width'] || !$_POST['ad']['flash']['height'])
					stderr($lang_admanage['std_error'], $lang_admanage['std_missing_form_data']);
				$parameters = serialize($_POST['ad']['flash']);
				$code = "<object width=\"".$_POST['ad']['flash']['width']."\" height=\"".$_POST['ad']['flash']['height']."\"><param name=\"movie\" value=\"".htmlspecialchars($_POST['ad']['flash']['url'])."\" /><embed src=\"".htmlspecialchars($_POST['ad']['flash']['url'])."\" width=\"".$_POST['ad']['flash']['width']."\" height=\"".$_POST['ad']['flash']['height']."\" type=\"application/x-shockwave-flash\"></embed></object>";
				break;
		}
		if ($_POST['isedit']){
			sql_query("UPDATE advertisements SET enabled=".sqlesc($enabled).", type=".sqlesc($type).", displayorder=".sqlesc($displayorder).", name=".sqlesc($name).", parameters=".sqlesc($parameters).", code=".sqlesc($code).", starttime=".($starttime ? sqlesc($starttime) : "NULL").", endtime=".($endtime ? sqlesc($endtime) : "NULL")." WHERE id=".sqlesc($id)) or sqlerr(__FILE__, __LINE__);
			$Cache->delete_value('current_ad_array', false);
			stderr($lang_admanage['std_success'], $lang_admanage['std_edit_success']."<a href=\"?\"><b>".$lang_admanage['std_go_back']."</b></a>", false);
		}
		else
		{
			sql_query("INSERT INTO advertisements (`enabled`, `type`, `position`, `displayorder`, `name`, `parameters`, `code`, `starttime`, `endtime`) VALUES (".sqlesc($enabled).", ".sqlesc($type).", ".sqlesc($position).", ".sqlesc($displayorder).", ".sqlesc($name).", ".sqlesc($parameters).", ".sqlesc($code).", ".($starttime ? sqlesc($starttime) : "NULL").", ".($endtime ? sqlesc($endtime) : "NULL").")") or sqlerr(__FILE__, __LINE__);
			$Cache->delete_value('current_ad_array', false);
			stderr($lang_admanage['std_success'], $lang_admanage['std_add_success']."<a href=\"?\"><b>".$lang_admanage['std_go_back']."</b></a>", false);
		}
	}
}
else
{
stdhead($lang_admanage['head_ad_management']);
begin_main_frame();
?>
<h1 align="center"><?php echo $lang_admanage['text_ad_management']?></h1>
<div>
<span id="addad" onclick="dropmenu(this);"><span style="cursor: pointer;" class="big"><b><?php echo $lang_admanage['text_add_ad']?></b></span>
<div id="addadlist" class="dropmenu" style="display: none"><ul>
<li><a href="?action=add&amp;position=header"><?php echo $lang_admanage['text_header']?></a></li>
<li><a href="?action=add&amp;position=footer"><?php echo $lang_admanage['text_footer']?></a></li>
<li><a href="?action=add&amp;position=belownav"><?php echo $lang_admanage['text_below_navigation']?></a></li>
<li><a href="?action=add&amp;position=belowsearchbox"><?php echo $lang_admanage['text_below_searchbox']?></a></li>
<li><a href="?action=add&amp;position=torrentdetail"><?php echo $lang_admanage['text_torrent_detail']?></a></li>
<li><a href="?action=add&amp;position=comment"><?php echo $lang_admanage['text_comment_page']?></a></li>
<li><a href="?action=add&amp;position=interoverforums"><?php echo $lang_admanage['text_inter_overforums']?></a></li>
<li><a href="?action=add&amp;position=forumpost"><?php echo $lang_admanage['text_forum_post_page']?></a></li>
</ul>
</div>
</span>
</div>
<div style="margin-top: 8px">
<?php
	$perpage = 20;
	$num = get_row_count("advertisements");
	if (!$num)
		print("<p align=\"center\">".$lang_admanage['text_no_ads_yet']."</p>");
	else{
		list($pagertop, $pagerbottom, $limit) = pager($perpage, $num, "?");
		$res = sql_query("SELECT * FROM advertisements ORDER BY id DESC ".$limit) or sqlerr(__FILE__, __LINE__);
?>
<table border="1" cellspacing="0" cellpadding="5" width="1200">
<tr>
<td class="colhead"><?php echo $lang_admanage['col_enabled']?></td>
<td class="colhead"><?php echo $lang_admanage['col_name']?></td>
<td class="colhead"><?php echo $lang_admanage['col_position']?></td>
<td class="colhead"><?php echo $lang_admanage['col_order']?></td>
<td class="colhead"><?php echo $lang_admanage['col_type']?></td>
<td class="colhead"><?php echo $lang_admanage['col_start_time']?></td>
<td class="colhead"><?php echo $lang_admanage['col_end_time']?></td>
<td class="colhead"><?php echo $lang_admanage['col_clicks']?></td>
<td class="colhead"><?php echo $lang_admanage['col_action']?></td>
</tr>
<?php
while ($row = mysql_fetch_array($res))
{
	$clickcount=get_row_count("adclicks", "WHERE adid=".sqlesc($row['id']));
?>
<tr>
<td class="colfollow"><?php echo $row['enabled'] ? "<font color=\"green\">".$lang_admanage['text_yes']."</font>" : "<font color=\"red\">".$lang_admanage['text_no']."</font>" ?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['name'])?></td>
<td class="colfollow"><?php echo get_position_name($row['position'])?></td>
<td class="colfollow"><?php echo $row['displayorder']?></td>
<td class="colfollow"><?php echo get_type_name($row['type'])?></td>
<td class="colfollow"><?php echo $row['starttime'] ? $row['starttime'] : $lang_admanage['text_unlimited']?></td>
<td class="colfollow"><?php echo $row['endtime'] ? $row['endtime'] : $lang_admanage['text_unlimited']?></td>
<td class="colfollow"><?php echo $clickcount?></td>
<td class="colfollow"><a href="javascript:confirm_delete('<?php echo $row['id']?>', '<?php echo $lang_admanage['js_sure_to_delete_ad']?>', '');"><?php echo $lang_admanage['text_delete']?></a> | <a href="?action=edit&amp;id=<?php echo $row['id']?>"><?php echo $lang_admanage['text_edit']?></a></td>
</tr>
<?php
}
?>
</table>
<?php
print($pagerbottom);
	}
?>
</div>
<?php
end_main_frame();
stdfoot();
}
?>

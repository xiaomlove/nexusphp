<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();

if (get_user_class() < UC_ADMINISTRATOR)
    permissiondenied();

function return_category_db_table_name($type)
{
	switch($type)
	{
		case 'category':
			$dbtablename = 'categories';
			break;
		case 'source':
			$dbtablename = 'sources';
			break;
		case 'medium':
			$dbtablename = 'media';
			break;
		case 'codec':
			$dbtablename = 'codecs';
			break;
		case 'standard':
			$dbtablename = 'standards';
			break;
		case 'processing':
			$dbtablename = 'processings';
			break;
		case 'team':
			$dbtablename = 'teams';
			break;
		case 'audiocodec':
			$dbtablename = 'audiocodecs';
			break;
		case 'searchbox':
			$dbtablename = 'searchbox';
			break;
		case 'secondicon':
			$dbtablename = 'secondicons';
			break;
		case 'caticon':
			$dbtablename = 'caticons';
			break;
		default:
			return false;
	}
	return $dbtablename;
}
function return_category_mode_selection($selname, $selectedid)
{
	$res = sql_query("SELECT * FROM searchbox ORDER BY id ASC");
	$selection = "<select name=\"".$selname."\">";
	while ($row = mysql_fetch_array($res))
		$selection .= "<option value=\"" . $row["id"] . "\"". ($row["id"]==$selectedid ? " selected=\"selected\"" : "").">" . htmlspecialchars($row["name"]) . "</option>\n";
	$selection .= "</select>";
	return $selection;
}

function category_icon_selection($iconId = 0)
{
    $res = sql_query("SELECT * FROM caticons ORDER BY id ASC");
    $selection = "<select name=\"icon_id\">";
    while ($row = mysql_fetch_array($res))
        $selection .= "<option value=\"" . $row["id"] . "\"". ($row["id"]==$iconId ? " selected=\"selected\"" : "").">" . htmlspecialchars($row["name"]) . "</option>\n";
    $selection .= "</select>";
    return $selection;
}

function return_type_name($type)
{
	global $lang_catmanage;
	switch ($type)
	{
		case 'searchbox':
			$name = $lang_catmanage['text_searchbox'];
			break;
		case 'caticon':
			$name = $lang_catmanage['text_category_icons'];
			break;
		case 'secondicon':
			$name = $lang_catmanage['text_second_icons'];
			break;
		case 'category':
			$name = $lang_catmanage['text_categories'];
			break;
		case 'source':
			$name = $lang_catmanage['text_sources'];
			break;
		case 'medium':
			$name = $lang_catmanage['text_media'];
			break;
		case 'codec':
			$name = $lang_catmanage['text_codecs'];
			break;
		case 'standard':
			$name = $lang_catmanage['text_standards'];
			break;
		case 'processing':
			$name = $lang_catmanage['text_processings'];
			break;
		case 'team':
			$name = $lang_catmanage['text_teams'];
			break;
		case 'audiocodec':
			$name = $lang_catmanage['text_audio_codecs'];
			break;
		default:
			return false;
	}
	return $name;
}

function print_type_list($type){
	global $lang_catmanage;
	$typename=return_type_name($type);
	stdhead($lang_catmanage['head_category_management']." - ".$typename);
	begin_main_frame();
?>
<h1 align="center"><?php echo $lang_catmanage['text_category_management']?> - <?php echo $typename?></h1>
<div>
<span id="item" onclick="dropmenu(this);"><span style="cursor: pointer;" class="big"><b><?php echo $lang_catmanage['text_manage']?></b></span>
<div id="itemlist" class="dropmenu" style="display: none"><ul>
<li><a href="?action=view&amp;type=searchbox"><?php echo $lang_catmanage['text_searchbox']?></a></li>
<li><a href="?action=view&amp;type=caticon"><?php echo $lang_catmanage['text_category_icons']?></a></li>
<li><a href="?action=view&amp;type=secondicon"><?php echo $lang_catmanage['text_second_icons']?></a></li>
<li><a href="?action=view&amp;type=category"><?php echo $lang_catmanage['text_categories']?></a></li>
<li><a href="?action=view&amp;type=source"><?php echo $lang_catmanage['text_sources']?></a></li>
<li><a href="?action=view&amp;type=medium"><?php echo $lang_catmanage['text_media']?></a></li>
<li><a href="?action=view&amp;type=codec"><?php echo $lang_catmanage['text_codecs']?></a></li>
<li><a href="?action=view&amp;type=standard"><?php echo $lang_catmanage['text_standards']?></a></li>
<li><a href="?action=view&amp;type=processing"><?php echo $lang_catmanage['text_processings']?></a></li>
<li><a href="?action=view&amp;type=team"><?php echo $lang_catmanage['text_teams']?></a></li>
<li><a href="?action=view&amp;type=audiocodec"><?php echo $lang_catmanage['text_audio_codecs']?></a></li>
</ul>
</div>
</span>
&nbsp;&nbsp;&nbsp;&nbsp;
<span id="add">
<a href="?action=add&amp;type=<?php echo $type?>" class="big"><b><?php echo $lang_catmanage['text_add']?></b></a>
</span>
</div>
<?php
}
function check_valid_type($type)
{
	global $lang_catmanage;
	$validtype=array('searchbox', 'caticon', 'secondicon', 'category', 'source', 'medium', 'codec', 'standard', 'processing', 'team', 'audiocodec');
	if (!in_array($type, $validtype))
		stderr($lang_catmanage['std_error'], $lang_catmanage['std_invalid_type']);
}
function print_sub_category_list($type)
{
	global $lang_catmanage;
	$dbtablename = return_category_db_table_name($type);
	$perpage = 50;
	$num = get_row_count($dbtablename);
	if (!$num)
		print("<p align=\"center\">".$lang_catmanage['text_no_record_yet']."</p>");
	else{
		list($pagertop, $pagerbottom, $limit) = pager($perpage, $num, "?");
		$res = sql_query("SELECT * FROM ".$dbtablename." ORDER BY id DESC ".$limit) or sqlerr(__FILE__, __LINE__);
?>
<table border="1" cellspacing="0" cellpadding="5" width="97%">
<tr>
<td class="colhead"><?php echo $lang_catmanage['col_id']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_name']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_order']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_action']?></td>
</tr>
<?php
		while ($row = mysql_fetch_array($res))
		{
?>
<tr>
<td class="colfollow"><?php echo $row['id']?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['name'])?></td>
<td class="colfollow"><?php echo $row['sort_index']?></td>
<td class="colfollow"><a href="javascript:confirm_delete('<?php echo $row['id']?>', '<?php echo $lang_catmanage['js_sure_to_delete_this']?>', 'type=<?php echo $type?>');"><?php echo $lang_catmanage['text_delete']?></a> | <a href="?action=edit&amp;type=<?php echo $type?>&amp;id=<?php echo $row['id']?>"><?php echo $lang_catmanage['text_edit']?></a></td>
</tr>
<?php
		}
?>
</table>
<?php
print($pagerbottom);
	}
}
function print_category_editor($type, $row='')
{
	global $lang_catmanage;
	global $validsubcattype;
	if (in_array($type, $validsubcattype))
		print_sub_category_editor($type, $row);
	else
	{
		$typename=return_type_name($type);
?>
<div style="width: 940px">
<h1 align="center"><a class="faqlink" href="?action=view&amp;type=<?php echo $type?>"><?php echo $typename?></a></h1>
<div>
<table border="1" cellspacing="0" cellpadding="10" width="100%">
<?php
		if ($type=='searchbox')
		{
			if ($row)
			{
				$name = $row['name'];
				$showsource = $row['showsource'];
				$showmedium = $row['showmedium'];
				$showcodec = $row['showcodec'];
				$showstandard = $row['showstandard'];
				$showprocessing = $row['showprocessing'];
				$showteam = $row['showteam'];
				$showaudiocodec = $row['showaudiocodec'];
				$catsperrow = $row['catsperrow'];
				$catpadding = $row['catpadding'];
			}
			else
			{
				$name = '';
				$showsource = 0;
				$showmedium = 0;
				$showcodec = 0;
				$showstandard = 0;
				$showprocessing = 0;
				$showteam = 0;
				$showaudiocodec = 0;
				$catsperrow = 8;
				$catpadding = 3;
			}
			tr($lang_catmanage['row_searchbox_name']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"name\" value=\"".htmlspecialchars($name)."\" style=\"width: 300px\" /> " . $lang_catmanage['text_searchbox_name_note'], 1);
			tr($lang_catmanage['row_show_sub_category'], "<input type=\"checkbox\" name=\"showsource\" value=\"1\"".($showsource ? " checked=\"checked\"" : "")." /> " . $lang_catmanage['text_sources'] . "<input type=\"checkbox\" name=\"showmedium\" value=\"1\"".($showmedium ? " checked=\"checked\"" : "")." /> " . $lang_catmanage['text_media'] . "<input type=\"checkbox\" name=\"showcodec\" value=\"1\"".($showcodec ? " checked=\"checked\"" : "")." /> " . $lang_catmanage['text_codecs'] . "<input type=\"checkbox\" name=\"showstandard\" value=\"1\"".($showstandard ? " checked=\"checked\"" : "")." /> " . $lang_catmanage['text_standards'] . "<input type=\"checkbox\" name=\"showprocessing\" value=\"1\"".($showprocessing ? " checked=\"checked\"" : "")." /> " . $lang_catmanage['text_processings'] . "<input type=\"checkbox\" name=\"showteam\" value=\"1\"".($showteam ? " checked=\"checked\"" : "")." /> " . $lang_catmanage['text_teams'] . "<input type=\"checkbox\" name=\"showaudiocodec\" value=\"1\"".($showaudiocodec ? " checked=\"checked\"" : "")." /> " . $lang_catmanage['text_audio_codecs']."<br />".$lang_catmanage['text_show_sub_category_note'], 1);
			tr($lang_catmanage['row_items_per_row']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"catsperrow\" value=\"".$catsperrow."\" style=\"width: 100px\" /> " . $lang_catmanage['text_items_per_row_note'], 1);
			tr($lang_catmanage['row_padding_between_items']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"catpadding\" value=\"".$catpadding."\" style=\"width: 100px\" /> " . $lang_catmanage['text_padding_between_items_note'], 1);
            $field = new \Nexus\Field\Field();
            tr($lang_catmanage['row_enable_custom_field'], $field->buildFieldCheckbox('custom_fields[]', $row['custom_fields'] ?? ''), 1);
            tr($lang_catmanage['row_custom_field_display_name'], '<input type="text" name="custom_fields_display_name" style="width: 300px" value="' . ($row['custom_fields_display_name'] ?? '') . '" />', 1);
            $helpText = '<br/>' . $lang_catmanage['row_custom_field_display_help'];
            tr($lang_catmanage['row_custom_field_display'], '<textarea name="custom_fields_display" style="width: 300px" rows="8">' . ($row['custom_fields_display'] ?? '') . '</textarea>' . $helpText, 1);
		}
		elseif ($type=='caticon')
		{
			if ($row)
			{
				$name = $row['name'];
				$folder = $row['folder'];
				$multilang = $row['multilang'];
				$secondicon = $row['secondicon'];
				$cssfile = $row['cssfile'];
				$designer = $row['designer'];
				$comment = $row['comment'];
			}
			else
			{
				$name = '';
				$folder = '';
				$multilang = 'no';
				$secondicon = 'no';
				$cssfile = '';
				$designer = '';
				$comment = '';
			}
?>
<tr><td colspan="2"><?php echo $lang_catmanage['text_icon_directory_note']?></td></tr>
<?php
			tr($lang_catmanage['col_name']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"name\" value=\"".htmlspecialchars($name)."\" style=\"width: 300px\" /> ", 1);
			tr($lang_catmanage['col_folder']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"folder\" value=\"".htmlspecialchars($folder)."\" style=\"width: 300px\" /><br />" . $lang_catmanage['text_folder_note'], 1);
			tr($lang_catmanage['text_multi_language'], "<input type=\"checkbox\" name=\"multilang\" value=\"yes\"".($multilang == 'yes' ? " checked=\"checked\"" : "")." />".$lang_catmanage['text_yes'] ."<br />". $lang_catmanage['text_multi_language_note'], 1);
			tr($lang_catmanage['text_second_icon'], "<input type=\"checkbox\" name=\"secondicon\" value=\"yes\"".($secondicon == 'yes' ? " checked=\"checked\"" : "")." />".$lang_catmanage['text_yes'] ."<br />". $lang_catmanage['text_second_icon_note'], 1);
			tr($lang_catmanage['text_css_file'], "<input type=\"text\" name=\"cssfile\" value=\"".htmlspecialchars($cssfile)."\" style=\"width: 300px\" /> ". $lang_catmanage['text_css_file_note'], 1);
			tr($lang_catmanage['text_designer'], "<input type=\"text\" name=\"designer\" value=\"".htmlspecialchars($designer)."\" style=\"width: 300px\" /> ". $lang_catmanage['text_designer_note'], 1);
			tr($lang_catmanage['text_comment'], "<input type=\"text\" name=\"comment\" value=\"".htmlspecialchars($comment)."\" style=\"width: 300px\" /> ". $lang_catmanage['text_comment_note'], 1);
		}
		elseif ($type=='secondicon')
		{
			if ($row)
			{
				$name = $row['name'];
				$image = $row['image'];
				$class_name = $row['class_name'];
				$source = $row['source'];
				$medium = $row['medium'];
				$codec = $row['codec'];
				$standard = $row['standard'];
				$processing = $row['processing'];
				$team = $row['team'];
				$audiocodec = $row['audiocodec'];
			}
			else
			{
				$name = '';
				$image = '';
				$class_name = '';
				$source = 0;
				$medium = 0;
				$codec = 0;
				$standard = 0;
				$processing = 0;
				$team = 0;
				$audiocodec = 0;
			}
			tr($lang_catmanage['col_name']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"name\" value=\"".htmlspecialchars($name)."\" style=\"width: 300px\" /> " . $lang_catmanage['text_second_icon_name_note'], 1);
			tr($lang_catmanage['col_image']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"image\" value=\"".htmlspecialchars($image)."\" style=\"width: 300px\" /><br />" . $lang_catmanage['text_image_note'], 1);
			tr($lang_catmanage['text_class_name'], "<input type=\"text\" name=\"class_name\" value=\"".htmlspecialchars($class_name)."\" style=\"width: 300px\" /><br />" . $lang_catmanage['text_class_name_note'], 1);
			tr($lang_catmanage['row_selections']."<font color=\"red\">*</font>", torrent_selection(return_type_name('source'), 'source', return_category_db_table_name('source'), $source) . torrent_selection(return_type_name('medium'), 'medium', return_category_db_table_name('medium'), $medium) . torrent_selection(return_type_name('codec'), 'codec', return_category_db_table_name('codec'), $codec) . torrent_selection(return_type_name('standard'), 'standard', return_category_db_table_name('standard'), $standard) . torrent_selection(return_type_name('processing'), 'processing', return_category_db_table_name('processing'), $processing) . torrent_selection(return_type_name('team'), 'team', return_category_db_table_name('team'), $team) . torrent_selection(return_type_name('audiocodec'), 'audiocodec', return_category_db_table_name('audiocodec'), $audiocodec)."<br />".$lang_catmanage['text_selections_note'], 1);
		}
		elseif ($type=='category')
		{
			if ($row)
			{
				$name = $row['name'];
				$mode = $row['mode'];
				$image = $row['image'];
				$class_name = $row['class_name'];
				$sort_index = $row['sort_index'];
			}
			else
			{
				$name = '';
				$mode = 1;
				$image = '';
				$class_name = '';
				$sort_index = 0;
			}
			tr($lang_catmanage['row_category_name']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"name\" value=\"".htmlspecialchars($name)."\" style=\"width: 300px\" /> " . $lang_catmanage['text_category_name_note'], 1);
			tr($lang_catmanage['col_image']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"image\" value=\"".htmlspecialchars($image)."\" style=\"width: 300px\" /><br />" . $lang_catmanage['text_image_note'], 1);
			tr($lang_catmanage['text_class_name'], "<input type=\"text\" name=\"class_name\" value=\"".htmlspecialchars($class_name)."\" style=\"width: 300px\" /><br />" . $lang_catmanage['text_class_name_note'], 1);
			tr($lang_catmanage['row_mode']."<font color=\"red\">*</font>", return_category_mode_selection('mode', $mode), 1);
			tr($lang_catmanage['text_category_icons']."<font color=\"red\">*</font>", category_icon_selection($row['icon_id'] ?? 0), 1);
			tr($lang_catmanage['col_order'], "<input type=\"text\" name=\"sort_index\" value=\"".$sort_index."\" style=\"width: 100px\" /> " . $lang_catmanage['text_order_note'], 1);
		}
?>
</table>
</div>
<div style="text-align: center; margin-top: 10px;">
<input type="submit" value="<?php echo $lang_catmanage['submit_submit']?>" />
</div>
</div>
<?php
	}
}
function print_sub_category_editor($type, $row='')
{
	global $lang_catmanage;
	$typename=return_type_name($type);
	if ($row)
	{
		$name = $row['name'];
		$sort_index = $row['sort_index'];
	}
	else
	{
		$name = '';
		$sort_index = 0;
	}
?>
<div style="width: 940px">
<h1 align="center"><a class="faqlink" href="?action=view&amp;type=<?php echo $type?>"><?php echo $typename?></a></h1>
<table border="1" cellspacing="0" cellpadding="10" width="100%">
<?php
tr($lang_catmanage['col_name']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"name\" value=\"".htmlspecialchars($name)."\" style=\"width: 300px\" /> " . $lang_catmanage['text_subcategory_name_note'], 1);
tr($lang_catmanage['col_order'], "<input type=\"text\" name=\"sort_index\" value=\"".$sort_index."\" style=\"width: 100px\" /> " . $lang_catmanage['text_order_note'], 1);
?>
</table>
<div style="text-align: center; margin-top: 10px;">
<input type="submit" value="<?php echo $lang_catmanage['submit_submit']?>" />
</div>
</div>
<?php
}

$validsubcattype=array('source', 'medium', 'codec', 'standard', 'processing', 'team', 'audiocodec');
$type = $_GET['type'] ?? '';
if ($type == '')
	$type = 'searchbox';
else
	check_valid_type($type);
$action = $_GET['action'] ?? '';
if ($action == '')
	$action = 'view';
if ($action == 'view')
{
	print_type_list($type);
?>
<div style="margin-top: 8px">
<?php
	if (in_array($type, $validsubcattype)){
		print_sub_category_list($type);
	}
	elseif ($type=='searchbox')
	{
	$perpage = 50;
	$dbtablename=return_category_db_table_name($type);
	$num = get_row_count($dbtablename);
	if (!$num)
		print("<p align=\"center\">".$lang_catmanage['text_no_record_yet']."</p>");
	else{
		list($pagertop, $pagerbottom, $limit) = pager($perpage, $num, "?");
		$res = sql_query("SELECT * FROM ".$dbtablename." ORDER BY id ASC ".$limit) or sqlerr(__FILE__, __LINE__);
?>
<table border="1" cellspacing="0" cellpadding="5" width="97%">
<tr>
<td class="colhead"><?php echo $lang_catmanage['col_id']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_name']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_sub_category']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_sources']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_media']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_codecs']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_standards']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_processings']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_teams']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_audio_codecs']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_per_row']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_padding']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_action']?></td>
</tr>
<?php
		while ($row = mysql_fetch_array($res))
		{
?>
<tr>
<td class="colfollow"><?php echo $row['id']?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['name'])?></td>
<td class="colfollow"><?php echo $row['showsubcat'] ? "<font color=\"green\">".$lang_catmanage['text_enabled']."</font>" : "<font color=\"red\">".$lang_catmanage['text_disabled']."</font>"?></td>
<td class="colfollow"><?php echo $row['showsource'] ? "<font color=\"green\">".$lang_catmanage['text_enabled']."</font>" : "<font color=\"red\">".$lang_catmanage['text_disabled']."</font>"?></td>
<td class="colfollow"><?php echo $row['showmedium'] ? "<font color=\"green\">".$lang_catmanage['text_enabled']."</font>" : "<font color=\"red\">".$lang_catmanage['text_disabled']."</font>"?></td>
<td class="colfollow"><?php echo $row['showcodec'] ? "<font color=\"green\">".$lang_catmanage['text_enabled']."</font>" : "<font color=\"red\">".$lang_catmanage['text_disabled']."</font>"?></td>
<td class="colfollow"><?php echo $row['showstandard'] ? "<font color=\"green\">".$lang_catmanage['text_enabled']."</font>" : "<font color=\"red\">".$lang_catmanage['text_disabled']."</font>"?></td>
<td class="colfollow"><?php echo $row['showprocessing'] ? "<font color=\"green\">".$lang_catmanage['text_enabled']."</font>" : "<font color=\"red\">".$lang_catmanage['text_disabled']."</font>"?></td>
<td class="colfollow"><?php echo $row['showteam'] ? "<font color=\"green\">".$lang_catmanage['text_enabled']."</font>" : "<font color=\"red\">".$lang_catmanage['text_disabled']."</font>"?></td>
<td class="colfollow"><?php echo $row['showaudiocodec'] ? "<font color=\"green\">".$lang_catmanage['text_enabled']."</font>" : "<font color=\"red\">".$lang_catmanage['text_disabled']."</font>"?></td>
<td class="colfollow"><?php echo $row['catsperrow']?></td>
<td class="colfollow"><?php echo $row['catpadding']?></td>
<td class="colfollow"><a href="javascript:confirm_delete('<?php echo $row['id']?>', '<?php echo $lang_catmanage['js_sure_to_delete_this']?>', 'type=<?php echo $type?>');"><?php echo $lang_catmanage['text_delete']?></a> | <a href="?action=edit&amp;type=<?php echo $type?>&amp;id=<?php echo $row['id']?>"><?php echo $lang_catmanage['text_edit']?></a></td>
</tr>
<?php
		}
?>
</table>
<?php
print($pagerbottom);
	}
	}
	elseif($type=='caticon')
	{
	$perpage = 50;
	$dbtablename=return_category_db_table_name($type);
	$num = get_row_count($dbtablename);
	if (!$num)
		print("<p align=\"center\">".$lang_catmanage['text_no_record_yet']."</p>");
	else{
		list($pagertop, $pagerbottom, $limit) = pager($perpage, $num, "?");
		$res = sql_query("SELECT * FROM ".$dbtablename." ORDER BY id ASC ".$limit) or sqlerr(__FILE__, __LINE__);
?>
<table border="1" cellspacing="0" cellpadding="5" width="97%">
<tr>
<td class="colhead"><?php echo $lang_catmanage['col_id']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_name']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_folder']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_multi_language']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_second_icon']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_css_file']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_designer']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_comment']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_action']?></td>
</tr>
<?php
		while ($row = mysql_fetch_array($res))
		{
?>
<tr>
<td class="colfollow"><?php echo $row['id']?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['name'])?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['folder'])?></td>
<td class="colfollow"><?php echo $row['multilang']=='yes' ? "<font color=\"green\">".$lang_catmanage['text_yes']."</font>" : "<font color=\"red\">".$lang_catmanage['text_no']."</font>"?></td>
<td class="colfollow"><?php echo $row['secondicon']=='yes' ? "<font color=\"green\">".$lang_catmanage['text_yes']."</font>" : "<font color=\"red\">".$lang_catmanage['text_no']."</font>"?></td>
<td class="colfollow"><?php echo $row['cssfile'] ? htmlspecialchars($row['cssfile']) : $lang_catmanage['text_none']?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['designer'])?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['comment'])?></td>
<td class="colfollow"><a href="javascript:confirm_delete('<?php echo $row['id']?>', '<?php echo $lang_catmanage['js_sure_to_delete_this']?>', 'type=<?php echo $type?>');"><?php echo $lang_catmanage['text_delete']?></a> | <a href="?action=edit&amp;type=<?php echo $type?>&amp;id=<?php echo $row['id']?>"><?php echo $lang_catmanage['text_edit']?></a></td>
</tr>
<?php
		}
?>
</table>
<?php
print($pagerbottom);
	}
	}
	elseif($type=='secondicon')
	{
	    $allSource = \App\Models\Source::query()->get()->keyBy('id');
	    $allMedia = \App\Models\Media::query()->get()->keyBy('id');
	    $allCodec = \App\Models\Codec::query()->get()->keyBy('id');
	    $allStandard = \App\Models\Standard::query()->get()->keyBy('id');
	    $allProcessing = \App\Models\Processing::query()->get()->keyBy('id');
	    $allTeam = \App\Models\Team::query()->get()->keyBy('id');
	    $allAudioCodec = \App\Models\AudioCodec::query()->get()->keyBy('id');
	$perpage = 50;
	$dbtablename=return_category_db_table_name($type);
	$num = get_row_count($dbtablename);
	if (!$num)
		print("<p align=\"center\">".$lang_catmanage['text_no_record_yet']."</p>");
	else{
		list($pagertop, $pagerbottom, $limit) = pager($perpage, $num, "?");
		$res = sql_query("SELECT * FROM ".$dbtablename." ORDER BY id ASC ".$limit) or sqlerr(__FILE__, __LINE__);
?>
<table border="1" cellspacing="0" cellpadding="5" width="97%">
<tr>
<td class="colhead"><?php echo $lang_catmanage['col_id']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_name']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_image']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_class_name']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_sources']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_media']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_codecs']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_standards']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_processings']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_teams']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_audio_codecs']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_action']?></td>
</tr>
<?php
		while ($row = mysql_fetch_array($res))
		{
?>
<tr>
<td class="colfollow"><?php echo $row['id']?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['name'])?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['image'])?></td>
<td class="colfollow"><?php echo $row['class_name'] ? htmlspecialchars($row['class_name']) : $lang_catmanage['text_none']?></td>
<td class="colfollow"><?php echo optional($allSource->get($row['source']))->name?></td>
<td class="colfollow"><?php echo optional($allMedia->get($row['medium']))->name?></td>
<td class="colfollow"><?php echo optional($allCodec->get($row['codec']))->name?></td>
<td class="colfollow"><?php echo optional($allStandard->get($row['standard']))->name?></td>
<td class="colfollow"><?php echo optional($allProcessing->get($row['processing']))->name?></td>
<td class="colfollow"><?php echo optional($allTeam->get($row['team']))->name?></td>
<td class="colfollow"><?php echo optional($allAudioCodec->get($row['audiocodec']))->name?></td>
<td class="colfollow"><a href="javascript:confirm_delete('<?php echo $row['id']?>', '<?php echo $lang_catmanage['js_sure_to_delete_this']?>', 'type=<?php echo $type?>');"><?php echo $lang_catmanage['text_delete']?></a> | <a href="?action=edit&amp;type=<?php echo $type?>&amp;id=<?php echo $row['id']?>"><?php echo $lang_catmanage['text_edit']?></a></td>
</tr>
<?php
		}
?>
</table>
<?php
print($pagerbottom);
	}
	}
	elseif($type=='category')
	{
	$perpage = 50;
	$dbtablename=return_category_db_table_name($type);
	$num = get_row_count($dbtablename);
	if (!$num)
		print("<p align=\"center\">".$lang_catmanage['text_no_record_yet']."</p>");
	else{
		list($pagertop, $pagerbottom, $limit) = pager($perpage, $num, "?");
        $res = sql_query("SELECT ".$dbtablename.".*, searchbox.name AS catmodename, caticons.name as icon_name FROM ".$dbtablename." LEFT JOIN searchbox ON ".$dbtablename.".mode=searchbox.id left join caticons on caticons.id = $dbtablename.icon_id ORDER BY ".$dbtablename.".mode ASC, ".$dbtablename.".id ASC ".$limit) or sqlerr(__FILE__, __LINE__);

?>
<table border="1" cellspacing="0" cellpadding="5" width="97%">
<tr>
<td class="colhead"><?php echo $lang_catmanage['col_id']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_mode']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_category_icons']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_name']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_image']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_class_name']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_order']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_action']?></td>
</tr>
<?php
		while ($row = mysql_fetch_array($res))
		{
?>
<tr>
<td class="colfollow"><?php echo $row['id']?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['catmodename'])?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['icon_name'] ?? '')?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['name'])?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['image'])?></td>
<td class="colfollow"><?php echo $row['class_name'] ? htmlspecialchars($row['class_name']) : $lang_catmanage['text_none']?></td>
<td class="colfollow"><?php echo $row['sort_index']?></td>
<td class="colfollow"><a href="javascript:confirm_delete('<?php echo $row['id']?>', '<?php echo $lang_catmanage['js_sure_to_delete_this']?>', 'type=<?php echo $type?>');"><?php echo $lang_catmanage['text_delete']?></a> | <a href="?action=edit&amp;type=<?php echo $type?>&amp;id=<?php echo $row['id']?>"><?php echo $lang_catmanage['text_edit']?></a></td>
</tr>
<?php
		}
?>
</table>
<?php
print($pagerbottom);
	}
	}
?>
</div>
<?php
	end_main_frame();
	stdfoot();
}
elseif($action == 'del')
{
	$id = intval($_GET['id'] ?? 0);
	if (!$id)
	{
		stderr($lang_catmanage['std_error'], $lang_catmanage['std_invalid_id']);
	}
	$dbtablename=return_category_db_table_name($type);
	$res = sql_query ("SELECT * FROM ".$dbtablename." WHERE id = ".sqlesc($id)." LIMIT 1");
	if ($row = mysql_fetch_array($res)){
		sql_query("DELETE FROM ".$dbtablename." WHERE id = ".sqlesc($row['id'])) or sqlerr(__FILE__, __LINE__);
		if(in_array($type, $validsubcattype))
			$Cache->delete_value($dbtablename.'_list');
		elseif ($type=='searchbox')
			$Cache->delete_value('searchbox_content');
		elseif ($type=='caticon')
			$Cache->delete_value('category_icon_content');
		elseif ($type=='secondicon')
			$Cache->delete_value('secondicon_'.$row['source'].'_'.$row['medium'].'_'.$row['codec'].'_'.$row['standard'].'_'.$row['processing'].'_'.$row['team'].'_'.$row['audiocodec'].'_content');
		elseif ($type=='category'){
			$Cache->delete_value('category_content');
			$Cache->delete_value('category_list_mode_'.$row['mode']);
		}
	}
	header("Location: ".get_protocol_prefix() . $BASEURL."/catmanage.php?action=view&type=".$type);
	die();
}
elseif($action == 'edit')
{
	$id = intval($_GET['id'] ?? 0);
	if (!$id)
	{
		stderr($lang_catmanage['std_error'], $lang_catmanage['std_invalid_id']);
	}
	else
	{
		$dbtablename=return_category_db_table_name($type);
		$res = sql_query ("SELECT * FROM ".$dbtablename." WHERE id = ".sqlesc($id)." LIMIT 1");
		if (!$row = mysql_fetch_array($res))
			stderr($lang_catmanage['std_error'], $lang_catmanage['std_invalid_id']);
		else
		{
			$typename=return_type_name($type);
			stdhead($typename);
			print("<form method=\"post\" action=\"?action=submit&amp;type=".$type."\">");
			print("<input type=\"hidden\" name=\"isedit\" value=\"1\" />");
			print("<input type=\"hidden\" name=\"id\" value=\"".$id."\" />");
			print_category_editor($type, $row);
			print("</form>");
			stdfoot();
		}
	}
}
elseif($action == 'add')
{
	$typename=return_type_name($type);
	stdhead($lang_catmanage['head_add']." - ".$typename);
	print("<form method=\"post\" action=\"?action=submit&amp;type=".$type."\">");
	print("<input type=\"hidden\" name=\"isedit\" value=\"0\" />");
	print_category_editor($type);
	print("</form>");
	stdfoot();
}
elseif($action == 'submit')
{
	$dbtablename=return_category_db_table_name($type);
	if ($_POST['isedit']){
		$id = intval($_POST['id'] ?? 0);
		if (!$id)
		{
			stderr($lang_catmanage['std_error'], $lang_catmanage['std_invalid_id']);
		}
		else
		{
			$res = sql_query("SELECT * FROM ".$dbtablename." WHERE id = ".sqlesc($id)." LIMIT 1");
			if (!$row = mysql_fetch_array($res))
				stderr($lang_catmanage['std_error'], $lang_catmanage['std_invalid_id']);
		}
	}
	$updateset = array();
	if (in_array($type, $validsubcattype)){
		$name = $_POST['name'];
		if (!$name)
			stderr($lang_catmanage['std_error'], $lang_catmanage['std_missing_form_data']);
		$updateset[] = "name=".sqlesc($name);
		$sort_index = intval($_POST['sort_index'] ?? 0);
		$updateset[] = "sort_index=".sqlesc($sort_index);
		$Cache->delete_value($dbtablename.'_list');
	}
	elseif ($type=='searchbox'){
		$name = $_POST['name'];
		$catsperrow = intval($_POST['catsperrow'] ?? 0);
		$catpadding = intval($_POST['catpadding'] ?? 0);
		if (!$name || !$catsperrow || !$catpadding)
			stderr($lang_catmanage['std_error'], $lang_catmanage['std_missing_form_data']);
		$showsource = intval($_POST['showsource'] ?? 0);
		$showmedium = intval($_POST['showmedium'] ?? 0);
		$showcodec = intval($_POST['showcodec'] ?? 0);
		$showstandard = intval($_POST['showstandard'] ?? 0);
		$showprocessing = intval($_POST['showprocessing'] ?? 0);
		$showteam = intval($_POST['showteam'] ?? 0);
		$showaudiocodec = intval($_POST['showaudiocodec'] ?? 0);
		$updateset[] = "catsperrow=".sqlesc($catsperrow);
		$updateset[] = "catpadding=".sqlesc($catpadding);
		$updateset[] = "name=".sqlesc($name);
		$updateset[] = "showsource=".sqlesc($showsource);
		$updateset[] = "showmedium=".sqlesc($showmedium);
		$updateset[] = "showcodec=".sqlesc($showcodec);
		$updateset[] = "showstandard=".sqlesc($showstandard);
		$updateset[] = "showprocessing=".sqlesc($showprocessing);
		$updateset[] = "showteam=".sqlesc($showteam);
		$updateset[] = "showaudiocodec=".sqlesc($showaudiocodec);
		$updateset[] = "custom_fields=" . sqlesc(implode(',', $_POST['custom_fields'] ?? []));
		$updateset[] = "custom_fields_display_name=" . sqlesc($_POST['custom_fields_display_name'] ?? '');
		$updateset[] = "custom_fields_display=" . sqlesc($_POST['custom_fields_display'] ?? '');
		if ($showsource || $showmedium || $showcodec || $showstandard || $showprocessing || $showteam || $showaudiocodec)
			$updateset[] = "showsubcat=1";
		else
			$updateset[] = "showsubcat=0";
		if($_POST['isedit'])
			$Cache->delete_value('searchbox_content');
	}
	elseif ($type=='caticon'){
		$name = $_POST['name'];
		$folder = trim($_POST['folder']);
		$cssfile = trim($_POST['cssfile']);
		$multilang = ($_POST['multilang'] == 'yes' ? 'yes' : 'no');
		$secondicon = ($_POST['secondicon'] == 'yes' ? 'yes' : 'no');
		$designer = $_POST['designer'];
		$comment = $_POST['comment'];
		if (!$name || !$folder)
			stderr($lang_catmanage['std_error'], $lang_catmanage['std_missing_form_data']);
		if (!valid_file_name($folder))
			stderr($lang_catmanage['std_error'], $lang_catmanage['std_invalid_character_in_filename'].htmlspecialchars($folder));
		if ($cssfile && !valid_file_name($cssfile))
			stderr($lang_catmanage['std_error'], $lang_catmanage['std_invalid_character_in_filename'].htmlspecialchars($cssfile));
		$updateset[] = "name=".sqlesc($name);
		$updateset[] = "folder=".sqlesc($folder);
		$updateset[] = "multilang=".sqlesc($multilang);
		$updateset[] = "secondicon=".sqlesc($secondicon);
		$updateset[] = "cssfile=".sqlesc($cssfile);
		$updateset[] = "designer=".sqlesc($designer);
		$updateset[] = "comment=".sqlesc($comment);
		if($_POST['isedit'])
			$Cache->delete_value('category_icon_content');
	}
	elseif ($type=='secondicon'){
		$name = $_POST['name'];
		$image = trim($_POST['image']);
		$class_name = trim($_POST['class_name']);
		$source = intval($_POST['source'] ?? 0);
		$medium = intval($_POST['medium'] ?? 0);
		$codec = intval($_POST['codec'] ?? 0);
		$standard = intval($_POST['standard'] ?? 0);
		$processing = intval($_POST['processing'] ?? 0);
		$team = intval($_POST['team'] ?? 0);
		$audiocodec = intval($_POST['audiocodec'] ?? 0);
		if (!$name || !$image)
			stderr($lang_catmanage['std_error'], $lang_catmanage['std_missing_form_data']);
		if (!valid_file_name($image))
			stderr($lang_catmanage['std_error'], $lang_catmanage['std_invalid_character_in_filename'].htmlspecialchars($image));
		if ($class_name && !valid_class_name($class_name))
			stderr($lang_catmanage['std_error'], $lang_catmanage['std_invalid_character_in_filename'].htmlspecialchars($class_name));
		if (!$source && !$medium && !$codec && !$standard && !$processing && !$team && !$audiocodec)
			stderr($lang_catmanage['std_error'], $lang_catmanage['std_must_define_one_selection']);
		$updateset[] = "name=".sqlesc($name);
		$updateset[] = "image=".sqlesc($image);
		$updateset[] = "class_name=".sqlesc($class_name);
		$updateset[] = "medium=".sqlesc($medium);
		$updateset[] = "codec=".sqlesc($codec);
		$updateset[] = "standard=".sqlesc($standard);
		$updateset[] = "processing=".sqlesc($processing);
		$updateset[] = "team=".sqlesc($team);
		$updateset[] = "audiocodec=".sqlesc($audiocodec);
		if($_POST['isedit']){
			$res2=sql_query("SELECT * FROM secondicons WHERE id=".sqlesc($id)." LIMIT 1");
			if ($row2=mysql_fetch_array($res))
			{
				$Cache->delete_value('secondicon_'.$row2['source'].'_'.$row2['medium'].'_'.$row2['codec'].'_'.$row2['standard'].'_'.$row2['processing'].'_'.$row2['team'].'_'.$row2['audiocodec'].'_content');
			}
		}
		$Cache->delete_value('secondicon_'.$source.'_'.$medium.'_'.$codec.'_'.$standard.'_'.$processing.'_'.$team.'_'.$audiocodec.'_content');
	}
	elseif ($type=='category'){
		$name = $_POST['name'];
		$image = trim($_POST['image']);
		$mode = intval($_POST['mode'] ?? 0);
		$class_name = trim($_POST['class_name']);
		$sort_index = intval($_POST['sort_index'] ?? 0);
		if (!$name || !$image)
			stderr($lang_catmanage['std_error'], $lang_catmanage['std_missing_form_data']);
		if (!valid_file_name($image))
			stderr($lang_catmanage['std_error'], $lang_catmanage['std_invalid_character_in_filename'].htmlspecialchars($image));
		if ($class_name && !valid_class_name($class_name))
			stderr($lang_catmanage['std_error'], $lang_catmanage['std_invalid_character_in_filename'].htmlspecialchars($class_name));
		if (!$mode)
			stderr($lang_catmanage['std_error'], $lang_catmanage['std_invalid_mode_id']);
		$updateset[] = "name=".sqlesc($name);
		$updateset[] = "image=".sqlesc($image);
		$updateset[] = "mode=".sqlesc($mode);
		$updateset[] = "class_name=".sqlesc($class_name);
		$updateset[] = "sort_index=".sqlesc($sort_index);
		$updateset[] = "icon_id=".sqlesc(intval($_POST['icon_id'] ?? 0));
		if($_POST['isedit']){
			$Cache->delete_value('category_content');
		}
		$Cache->delete_value('category_list_mode_'.$mode);
	}
	if ($_POST['isedit'])
	{
		sql_query("UPDATE ".$dbtablename." SET " . join(",", $updateset) . " WHERE id = ".sqlesc($id)) or sqlerr(__FILE__, __LINE__);
	}
	else
	{
		sql_query("INSERT INTO ".$dbtablename." SET " . join(",", $updateset) ) or sqlerr(__FILE__, __LINE__);
	}
	header("Location: ".get_protocol_prefix() . $BASEURL."/catmanage.php?action=view&type=".$type);
}
?>

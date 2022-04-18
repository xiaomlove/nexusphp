<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();

$brsectiontype = $browsecatmode;
$spsectiontype = $specialcatmode;
if ($enablespecial == 'yes' && get_user_class() >= get_setting('authority.view_special_torrent'))
	$allowspecial = true;
else $allowspecial = false;
$showsubcat = (get_searchbox_value($brsectiontype, 'showsubcat') || ($allowspecial && get_searchbox_value($spsectiontype, 'showsubcat')));
$showsource = (get_searchbox_value($brsectiontype, 'showsource') || ($allowspecial && get_searchbox_value($spsectiontype, 'showsource'))); //whether show sources or not
$showmedium = (get_searchbox_value($brsectiontype, 'showmedium') || ($allowspecial && get_searchbox_value($spsectiontype, 'showmedium'))); //whether show media or not
$showcodec = (get_searchbox_value($brsectiontype, 'showcodec') || ($allowspecial && get_searchbox_value($spsectiontype, 'showcodec'))); //whether show codecs or not
$showstandard = (get_searchbox_value($brsectiontype, 'showstandard') || ($allowspecial && get_searchbox_value($spsectiontype, 'showstandard'))); //whether show standards or not
$showprocessing = (get_searchbox_value($brsectiontype, 'showprocessing') || ($allowspecial && get_searchbox_value($spsectiontype, 'showprocessing'))); //whether show processings or not
$showteam = (get_searchbox_value($brsectiontype, 'showteam') || ($allowspecial && get_searchbox_value($spsectiontype, 'showteam'))); //whether show teams or not
$showaudiocodec = (get_searchbox_value($brsectiontype, 'showaudiocodec') || ($allowspecial && get_searchbox_value($spsectiontype, 'showaudiocodec'))); //whether show audio codecs or not
$brcatsperror = get_searchbox_value($brsectiontype, 'catsperrow');
$catsperrow = get_searchbox_value($spsectiontype, 'catsperrow');
$catsperrow = (!$allowspecial ? $brcatsperror : ($catsperrow > $catsperrow ? $catsperrow : $catsperrow)); //show how many cats per line

$brcatpadding = get_searchbox_value($brsectiontype, 'catpadding');
$spcatpadding = get_searchbox_value($spsectiontype, 'catpadding');
$catpadding = (!$allowspecial ? $brcatpadding : ($brcatpadding < $spcatpadding ? $brcatpadding : $spcatpadding)); //padding space between categories in pixel

$brcats = genrelist($brsectiontype);
$spcats = genrelist($spsectiontype);

if ($showsubcat){
if ($showsource) $sources = searchbox_item_list("sources");
if ($showmedium) $media = searchbox_item_list("media");
if ($showcodec) $codecs = searchbox_item_list("codecs");
if ($showstandard) $standards = searchbox_item_list("standards");
if ($showprocessing) $processings = searchbox_item_list("processings");
if ($showteam) $teams = searchbox_item_list("teams");
if ($showaudiocodec) $audiocodecs = searchbox_item_list("audiocodecs");
}
stdhead($lang_getrss['head_rss_feeds']);
$query = [];
$query[] = "passkey=" . $CURUSER['passkey'];
if ($_SERVER['REQUEST_METHOD'] == "POST") {
	$allowed_showrows=array('10','20','30','40','50');
	$link = get_protocol_prefix(). $BASEURL ."/torrentrss.php";
	if (isset($_POST['showrows']) && in_array($_POST['showrows'], $allowed_showrows, 1))
		$query[] = "rows=".(int)$_POST['showrows'];
	else {
		stdmsg($lang_getrss['std_error'],$lang_getrss['std_no_row']);
		stdfoot();
		die();
	}
	foreach ($brcats as $cat)
	{
		if (!empty($_POST["cat{$cat['id']}"]))
		{
			$query[] = "cat{$cat['id']}=1";
		}
	}
	if ($enablespecial == 'yes')
	{
		foreach ($spcats as $cat)
		{
			if (!empty($_POST["cat{$cat['id']}"]))
			{
				$query[] = "cat{$cat['id']}=1";
			}
		}
	}
	if ($showsubcat){
		if ($showsource)
		foreach ($sources as $source)
		{
			if (!empty($_POST["sou{$source['id']}"]))
			{
				$query[] = "sou{$source['id']}=1";
			}
		}
		if ($showmedium)
		foreach ($media as $medium)
		{
			if (!empty($_POST["med{$medium['id']}"]))
			{
				$query[] = "med{$medium['id']}=1";
			}
		}
		if ($showcodec)
		foreach ($codecs as $codec)
		{
			if (!empty($_POST["cod{$codec['id']}"]))
			{
				$query[] = "cod{$codec['id']}=1";
			}
		}
		if ($showstandard)
		foreach ($standards as $standard)
		{
			if (!empty($_POST["sta{$standard['id']}"]))
			{
				$query[] = "sta{$standard['id']}=1";
			}
		}
		if ($showprocessing)
		foreach ($processings as $processing)
		{
			if (!empty($_POST["pro{$processing['id']}"]))
			{
				$query[] = "pro{$processing['id']}=1";
			}
		}
		if ($showteam)
		foreach ($teams as $team)
		{
			if (!empty($_POST["tea{$team['id']}"]))
			{
				$query[] = "tea{$team['id']}=1";
			}
		}
		if ($showaudiocodec)
		foreach ($audiocodecs as $audiocodec)
		{
			if (!empty($_POST["aud{$audiocodec['id']}"]))
			{
				$query[] = "aud{$audiocodec['id']}=1";
			}
		}
	}
	if (!empty($_POST["itemcategory"]))
	{
		$query[] = "icat=1";
	}
	if (!empty($_POST["itemsmalldescr"]))
	{
		$query[] = "ismalldescr=1";
	}
	if (!empty($_POST["itemsize"]))
	{
		$query[] = "isize=1";
	}
	if (!empty($_POST["itemuploader"]))
	{
		$query[] = "iuplder=1";
	}
	$searchstr = mysql_real_escape_string(trim($_POST["search"] ?? ''));
//	if (empty($searchstr))
//		unset($searchstr);
	if ($searchstr)
	{
		$query[] = "search=".rawurlencode($searchstr);
		if ($_POST["search_mode"]){
			$search_mode = intval($_POST["search_mode"] ?? 0);
			if (!in_array($search_mode,array(0,1,2)))
			{
				$search_mode = 0;
			}
			$query[] = "search_mode=".$search_mode;
		}
	}
	$inclbookmarked=intval($_POST['inclbookmarked'] ?? 0);
	if($inclbookmarked)
	{
		if (!in_array($inclbookmarked,array(0,1)))
		{
			$inclbookmarked = 0;
		}
		$addinclbm = "&inclbookmarked=".$inclbookmarked;
	}
	else
	{
		$addinclbm="";
	}
	$queries = implode("&", $query);
	if ($queries)
		$link .= "?".$queries;
	$msg = $lang_getrss['std_use_following_url'] ."\n".$link."\n\n".$lang_getrss['std_utorrent_feed_url']."\n".$link."&linktype=dl".$addinclbm;
	stdmsg($lang_getrss['std_done'],format_comment($msg));
	stdfoot();
	die();
}
?>
<h1 align="center"><?php echo $lang_getrss['text_rss_feeds']?></h1>
<form method="post" action="getrss.php">
<table cellspacing="1" cellpadding="5" width="97%">
<tr>
<td class="rowhead"><?php echo $lang_getrss['row_categories_to_retrieve']?>
</td>
<td class="rowfollow" align="left">
<?php
$categories = "<table><tr><td class=\"embedded\" align=\"left\"><b>".$lang_getrss['text_category']."</b></td></tr><tr>";
$i = 0;
foreach ($brcats as $cat)//print category list of Torrents section
{
	$numinrow = $i % $catsperrow;
	$rownum = (int)($i / $catsperrow);
	if ($i && $numinrow == 0){
		$categories .= "</tr>".($brenablecatrow ? "<tr><td class=\"embedded\" align=\"left\"><b>".$brcatrow[$rownum]."</b></td></tr>" : "")."<tr>";
	}
	$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"cat".$cat['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[cat".$cat['id']."]") !== false ? " checked=\"checked\"" : "")." value='yes' />".return_category_image($cat['id'], "torrents.php?allsec=1&amp;")."</td>\n";
	$i++;
}
$categories .= "</tr>";
if ($allowspecial) //print category list of Special section
{
	$categories .= "<tr>";
	$i = 0;
	foreach ($spcats as $cat)
	{
		$numinrow = $i % $catsperrow;
		$rownum = (int)($i / $catsperrow);
		if ($i && $numinrow == 0){
			$categories .= "</tr>".($spenablecatrow ? "<tr><td class=\"embedded\" align=\"left\"><b>".$spcatrow[$rownum]."</b></td></tr>" : "")."<tr>";
		}
		$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"cat".$cat['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[cat".$cat['id']."]") !== false ? " checked=\"checked\"" : "")." value='yes' />".return_category_image($cat['id'], "torrents.php?allsec=1&amp;")."</td>\n";
		$i++;
	}
	$categories .= "</tr>";
}
			if ($showsubcat)//Show subcategory (i.e. source, codecs) selections
			{
				if ($showsource){
				$categories .= "<tr><td class=\"embedded\" align=\"left\"><b>".$lang_getrss['text_source']."</b></td></tr><tr>";
				$i = 0;
				foreach ($sources as $source)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"sou".$source['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[sou".$source['id']."]") !== false ? " checked=\"checked\"" : "") . " value='yes' />".$source['name']."</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
				if ($showmedium){
				$categories .= "<tr><td class=\"embedded\" align=\"left\"><b>".$lang_getrss['text_medium']."</b></td></tr><tr>";
				$i = 0;
				foreach ($media as $medium)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"med".$medium['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[med".$medium['id']."]") !== false ? " checked=\"checked\"" : "") . " value='yes' />".$medium['name']."</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
				if ($showcodec){
				$categories .= "<tr><td class=\"embedded\" align=\"left\"><b>".$lang_getrss['text_codec']."</b></td></tr><tr>";
				$i = 0;
				foreach ($codecs as $codec)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"cod".$codec['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[cod".$codec['id']."]") !== false ? " checked=\"checked\"" : "") . " value='yes' />".$codec['name']."</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
				if ($showaudiocodec){
				$categories .= "<tr><td class=\"embedded\" align=\"left\"><b>".$lang_getrss['text_audio_codec']."</b></td></tr><tr>";
				$i = 0;
				foreach ($audiocodecs as $audiocodec)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"aud".$audiocodec['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[aud".$audiocodec['id']."]") !== false ? " checked=\"checked\"" : "") . " value='yes' />".$audiocodec['name']."</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
				if ($showstandard){
				$categories .= "<tr><td class=\"embedded\" align=\"left\"><b>".$lang_getrss['text_standard']."</b></td></tr><tr>";
				$i = 0;
				foreach ($standards as $standard)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"sta".$standard['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[sta".$standard['id']."]") !== false ? " checked=\"checked\"" : "") . " value='yes' />".$standard['name']."</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
				if ($showprocessing){
				$categories .= "<tr><td class=\"embedded\" align=\"left\"><b>".$lang_getrss['text_processing']."</b></td></tr><tr>";
				$i = 0;
				foreach ($processings as $processing)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"pro".$processing['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[pro".$processing['id']."]") !== false ? " checked=\"checked\"" : "") . " value='yes' />".$processing['name']."</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
				if ($showteam){
				$categories .= "<tr><td class=\"embedded\" align=\"left\"><b>".$lang_getrss['text_team']."</b></td></tr><tr>";
				$i = 0;
				foreach ($teams as $team)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"tea".$team['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[tea".$team['id']."]") !== false ? " checked=\"checked\"" : "") . " value='yes' />".$team['name']."</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
			}
$categories .= "</table>";
print($categories);
?>
</td>
</tr>
<tr>
<td class="rowhead"><?php echo $lang_getrss['row_show_bookmarked']?>
</td>
<td class="rowfollow" align="left">
<input type="radio" name="inclbookmarked" id="inclbookmarked0" value="0" checked="checked" /><label for="inclbookmarked0"><?php echo $lang_getrss['text_all']?></label>&nbsp;<input type="radio" name="inclbookmarked" id="inclbookmarked1" value="1" /><label for="inclbookmarked1"><?php echo $lang_getrss['text_only_bookmarked']?></label><div><?php echo $lang_getrss['text_show_bookmarked_note']?></div>
</td>
</tr>
<tr>
<td class="rowhead"><?php echo $lang_getrss['row_item_title_type']?>
</td>
<td class="rowfollow" align="left">
<input type="checkbox" name="itemcategory" value="1" /><?php echo $lang_getrss['text_item_category']?>&nbsp;<input type="checkbox" name="itemtitle" checked="checked" disabled="disabled" /><?php echo $lang_getrss['text_item_title']?>&nbsp;<input type="checkbox" name="itemsmalldescr" value="1" /><?php echo $lang_getrss['text_item_small_description']?>&nbsp;<input type="checkbox" name="itemsize" value="1" /><?php echo $lang_getrss['text_item_size']?>&nbsp;<input type="checkbox" name="itemuploader" value="1" /><?php echo $lang_getrss['text_item_uploader']?>
</td>
</tr>
<tr><td class="rowhead"><?php echo $lang_getrss['row_rows_per_page']?></td><td class="rowfollow" align="left"><select name="showrows">
<option value="10">10</option>
<option value="20">20</option>
<option value="30">30</option>
<option value="40">40</option>
<option value="50">50</option>
</select></td></tr>
<tr><td class="rowhead"><?php echo $lang_getrss['row_keyword']?></td>
<td class="rowfollow" align="left">
<input type="text" name="search" style="width: 200px;" /> <?php echo $lang_getrss['text_with']?>
<select name="search_mode" style="width: 60px;">
<option value="0"><?php echo $lang_getrss['select_and'] ?></option>
<option value="1" selected="selected"><?php echo $lang_getrss['select_or'] ?></option>
<option value="2"><?php echo $lang_getrss['select_exact'] ?></option>
</select>
<?php echo $lang_getrss['text_mode']?>
<div><?php echo $lang_getrss['text_keyword_note'] ?></div>
</td>
</tr>
<tr>
<td colspan="2" align="center">
<input type="submit" value="<?php echo $lang_getrss['submit_generatte_rss_link']?>" />
</td>
</tr>
</table>
</form>
<?php
stdfoot();

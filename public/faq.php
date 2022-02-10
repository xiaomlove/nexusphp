<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
//loggedinorreturn();

stdhead($lang_faq['head_faq']);
$Cache->new_page('faq', 900, true);
if (!$Cache->get_page())
{
$Cache->add_whole_row();
//make_folder("cache/" , get_langfolder_cookie());
//cache_check ('faq');
begin_main_frame();

begin_frame($lang_faq['text_welcome_to'].$SITENAME." - ".$SLOGAN);
print($lang_faq['text_welcome_content_one'].$lang_faq['text_welcome_content_two']);
end_frame();

$lang_id = get_guest_lang_id();
$is_rulelang = get_single_value("language","rule_lang","WHERE id = ".sqlesc($lang_id));
if (!$is_rulelang){
	$lang_id = 6; //English
}
$res = sql_query("SELECT `id`, `link_id`, `question`, `flag` FROM `faq` WHERE `type`='categ' AND `lang_id` = ".sqlesc($lang_id)." ORDER BY `order` ASC");
while ($arr = mysql_fetch_array($res)) {
	$faq_categ[$arr['link_id']]['title'] = $arr['question'];
	$faq_categ[$arr['link_id']]['flag'] = $arr['flag'];
	$faq_categ[$arr['link_id']]['link_id'] = $arr['link_id'];
}

$res = sql_query("SELECT `id`, `link_id`, `question`, `answer`, `flag`, `categ` FROM `faq` WHERE `type`='item' AND `lang_id` = ".sqlesc($lang_id)." ORDER BY `order` ASC");
while ($arr = mysql_fetch_array($res)) {
	$faq_categ[$arr['categ']]['items'][$arr['id']]['question'] = $arr['question'];
	$faq_categ[$arr['categ']]['items'][$arr['id']]['answer'] = $arr['answer'];
	$faq_categ[$arr['categ']]['items'][$arr['id']]['flag'] = $arr['flag'];
	$faq_categ[$arr['categ']]['items'][$arr['id']]['link_id'] = $arr['link_id'];
}

if (isset($faq_categ)) {
	// gather orphaned items
	/*
	foreach ($faq_categ as $id => $temp)
	{
		if (!array_key_exists("title", $faq_categ[$id]))
		{
			foreach ($faq_categ[$id]['items'] as $id2 => $temp)
			{
				$faq_orphaned[$id2]['question'] = $faq_categ[$id]['items'][$id2]['question'];
				$faq_orphaned[$id2][answer] = $faq_categ[$id]['items'][$id2][answer];
				$faq_orphaned[$id2]['flag'] = $faq_categ[$id]['items'][$id2]['flag'];
				unset($faq_categ[$id]);
			}
		}
	}
	*/

	begin_frame("<span id=\"top\">".$lang_faq['text_contents'] . "</span>");
	foreach ($faq_categ as $id => $temp)
	{
		if ($faq_categ[$id]['flag'] == "1")
		{
			print("<ul><li><a href=\"#id". $faq_categ[$id]['link_id'] ."\"><b>". $faq_categ[$id]['title'] ."</b></a><ul>\n");
   			if (array_key_exists("items", $faq_categ[$id]))
			{
    				foreach ($faq_categ[$id]['items'] as $id2 => $temp)
				{
	 				if ($faq_categ[$id]['items'][$id2]['flag'] == "1") print("<li><a href=\"#id". $faq_categ[$id]['items'][$id2]['link_id'] ."\" class=\"faqlink\">". $faq_categ[$id]['items'][$id2]['question'] ."</a></li>\n");
	 				elseif ($faq_categ[$id]['items'][$id2]['flag'] == "2") print("<li><a href=\"#id". $faq_categ[$id]['items'][$id2]['link_id'] ."\" class=\"faqlink\">". $faq_categ[$id]['items'][$id2]['question'] ."</a> <img class=\"faq_updated\" src=\"pic/trans.gif\" alt=\"Updated\" /></li>\n");
	 				elseif ($faq_categ[$id]['items'][$id2]['flag'] == "3") print("<li><a href=\"#id". $faq_categ[$id]['items'][$id2]['link_id'] ."\" class=\"faqlink\">". $faq_categ[$id]['items'][$id2]['question'] ."</a> <img class=\"faq_new\" src=\"pic/trans.gif\" alt=\"New\" /></li>\n");
    				}
			}
			print("</ul></li></ul><br />");
		}
	}
	end_frame();

	foreach ($faq_categ as $id => $temp) {
		if ($faq_categ[$id]['flag'] == "1")
		{
			$frame = $faq_categ[$id]['title'] ." - <a href=\"#top\"><img class=\"top\" src=\"pic/trans.gif\" alt=\"Top\" title=\"Top\" /></a>";
			begin_frame($frame);
			print("<span id=\"id". $faq_categ[$id]['link_id'] ."\"></span>");
			if (array_key_exists("items", $faq_categ[$id]))
			{
				foreach ($faq_categ[$id]['items'] as $id2 => $temp)
				{
					if ($faq_categ[$id]['items'][$id2]['flag'] != "0")
					{
						print("<br /><span id=\"id".$faq_categ[$id]['items'][$id2]['link_id']."\"><b>". $faq_categ[$id]['items'][$id2]['question'] ."</b></span><br />\n");
						print("<br />". $faq_categ[$id]['items'][$id2]['answer'] ."\n<br /><br />\n");
					}
				}
			}
			end_frame();
		}
	}
}
end_main_frame();
	$Cache->end_whole_row();
	$Cache->cache_page();
}
echo $Cache->next_row();
//cache_save ('faq');
stdfoot();
?>

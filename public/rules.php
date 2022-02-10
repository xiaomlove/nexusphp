<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
//loggedinorreturn();
stdhead($lang_rules['head_rules']);
$Cache->new_page('rules', 900, true);
if (!$Cache->get_page())
{
$Cache->add_whole_row();
//make_folder("cache/" , get_langfolder_cookie());
//cache_check ('rules');
begin_main_frame();

$lang_id = get_guest_lang_id();
$is_rulelang = get_single_value("language","rule_lang","WHERE id = ".sqlesc($lang_id));
if (!$is_rulelang){
	$lang_id = 6; //English
}
$res = sql_query("SELECT * FROM rules WHERE lang_id = ".sqlesc($lang_id)." ORDER BY id");
while ($arr=mysql_fetch_assoc($res)){
	begin_frame($arr['title'], false);
	print(format_comment($arr["text"]));
	end_frame();
}
end_main_frame();
}
//cache_save ('rules');
stdfoot();
?>

<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());

function insert_tag($name, $description, $syntax, $example, $remarks)
{
	global $lang_tags;
	$result = format_comment($example);
	print("<p class=sub><b>$name</b></p>\n");
	print("<table class=main width=100% border=1 cellspacing=0 cellpadding=5>\n");
	print("<tr valign=top><td width=25%>".$lang_tags['text_description']."</td><td>$description\n");
	print("<tr valign=top><td>".$lang_tags['text_syntax']."</td><td><tt>$syntax</tt>\n");
	print("<tr valign=top><td>".$lang_tags['text_example']."</td><td><tt>$example</tt>\n");
	print("<tr valign=top><td>".$lang_tags['text_result']."</td><td>$result\n");
	if ($remarks != "")
		print("<tr><td>".$lang_tags['text_remarks']."</td><td>$remarks\n");
	print("</table>\n");
}

stdhead($lang_tags['head_tags']);
begin_main_frame();
begin_frame($lang_tags['text_tags']);
$test = $_POST["test"] ?? '';
?>
<p><?php echo $lang_tags['text_bb_tags_note'] ?></p>

<form method=post action=?>
<textarea name=test cols=60 rows=3><?php print($test ? htmlspecialchars($test) : "")?></textarea>
<input type=submit style='height: 23px; margin-left: 5px' value=<?php echo $lang_tags['submit_test_this_code'] ?>>
</form>
<?php

if ($test != "")
  print("<p><hr>" . format_comment($test) . "</hr></p>\n");

insert_tag(
	$lang_tags['text_bold'],
	$lang_tags['text_bold_description'],
	$lang_tags['text_bold_syntax'],
	$lang_tags['text_bold_example'],
	""
);

insert_tag(
	$lang_tags['text_italic'],
	$lang_tags['text_italic_description'],
	$lang_tags['text_italic_syntax'],
	$lang_tags['text_italic_example'],
	""
);

insert_tag(
	$lang_tags['text_underline'],
	$lang_tags['text_underline_description'],
	$lang_tags['text_underline_syntax'],
	$lang_tags['text_underline_example'],
	""
);

insert_tag(
	$lang_tags['text_color_one'],
	$lang_tags['text_color_one_description'],
	$lang_tags['text_color_one_syntax'],
	$lang_tags['text_color_one_example'],
	$lang_tags['text_color_one_remarks']
);

insert_tag(
	$lang_tags['text_color_two'],
	$lang_tags['text_color_two_description'],
	$lang_tags['text_color_two_syntax'],
	$lang_tags['text_color_two_example'],
	$lang_tags['text_color_two_remarks']
);

insert_tag(
	$lang_tags['text_size'],
	$lang_tags['text_size_description'],
	$lang_tags['text_size_syntax'],
	$lang_tags['text_size_example'],
	$lang_tags['text_size_remarks']
);

insert_tag(
	$lang_tags['text_font'],
	$lang_tags['text_font_description'],
	$lang_tags['text_font_syntax'],
	$lang_tags['text_font_example'],
	$lang_tags['text_font_remarks']
);

insert_tag(
	$lang_tags['text_hyperlink_one'],
	$lang_tags['text_hyperlink_one_description'],
	$lang_tags['text_hyperlink_one_syntax'],
	$lang_tags['text_hyperlink_one_example'],
	$lang_tags['text_hyperlink_one_remarks']
);

insert_tag(
	$lang_tags['text_hyperlink_two'],
	$lang_tags['text_hyperlink_two_description'],
	$lang_tags['text_hyperlink_two_syntax'],
	$lang_tags['text_hyperlink_two_example'],
	$lang_tags['text_hyperlink_two_remarks']
);

insert_tag(
	$lang_tags['text_image_one'],
	$lang_tags['text_image_one_description'],
	$lang_tags['text_image_one_syntax'],
	$lang_tags['text_image_one_example'],
	$lang_tags['text_image_one_remarks']
);

insert_tag(
	$lang_tags['text_image_two'],
	$lang_tags['text_image_two_description'],
	$lang_tags['text_image_two_syntax'],
	$lang_tags['text_image_two_example'],
	$lang_tags['text_image_two_remarks']
);

insert_tag(
	$lang_tags['text_quote_one'],
	$lang_tags['text_quote_one_description'],
	$lang_tags['text_quote_one_syntax'],
	$lang_tags['text_quote_one_example'],
	""
);

insert_tag(
	$lang_tags['text_quote_two'],
	$lang_tags['text_quote_two_description'],
	$lang_tags['text_quote_two_syntax'],
	$lang_tags['text_quote_two_example'],
	""
);

insert_tag(
	$lang_tags['text_list'],
	$lang_tags['text_description'],
	$lang_tags['text_list_syntax'],
	$lang_tags['text_list_example'],
	""
);

insert_tag(
	$lang_tags['text_preformat'],
	$lang_tags['text_preformat_description'],
	$lang_tags['text_preformat_syntax'],
	$lang_tags['text_preformat_example'],
	""
);

insert_tag(
	$lang_tags['text_code'],
	$lang_tags['text_code_description'],
	$lang_tags['text_code_syntax'],
	$lang_tags['text_code_example'],
	""
);
/*
insert_tag(
	$lang_tags['text_you'],
	$lang_tags['text_you_description'],
	$lang_tags['text_you_syntax'],
	$lang_tags['text_you_example'],
	$lang_tags['text_you_remarks']
);
*/

insert_tag(
	$lang_tags['text_site'],
	$lang_tags['text_site_description'],
	$lang_tags['text_site_syntax'],
	$lang_tags['text_site_example'],
	""
);

insert_tag(
	$lang_tags['text_siteurl'],
	$lang_tags['text_siteurl_description'],
	$lang_tags['text_siteurl_syntax'],
	$lang_tags['text_siteurl_example'],
	""
);

insert_tag(
    $lang_tags['text_left'],
    $lang_tags['text_left_description'],
    $lang_tags['text_left_syntax'],
    $lang_tags['text_left_example'],
    ""
);

insert_tag(
    $lang_tags['text_center'],
    $lang_tags['text_center_description'],
    $lang_tags['text_center_syntax'],
    $lang_tags['text_center_example'],
    ""
);

insert_tag(
    $lang_tags['text_right'],
    $lang_tags['text_right_description'],
    $lang_tags['text_right_syntax'],
    $lang_tags['text_right_example'],
    ""
);

insert_tag(
	$lang_tags['text_flash'],
	$lang_tags['text_flash_description'],
	$lang_tags['text_flash_syntax'],
	$lang_tags['text_flash_example'],
	""
);

insert_tag(
	$lang_tags['text_flash_two'],
	$lang_tags['text_flash_two_description'],
	$lang_tags['text_flash_two_syntax'],
	$lang_tags['text_flash_two_example'],
	""
);

insert_tag(
	$lang_tags['text_flv_one'],
	$lang_tags['text_flv_one_description'],
	$lang_tags['text_flv_one_syntax'],
	$lang_tags['text_flv_one_example'],
	""
);

insert_tag(
	$lang_tags['text_flv_two'],
	$lang_tags['text_flv_two_description'],
	$lang_tags['text_flv_two_syntax'],
	$lang_tags['text_flv_two_example'],
	""
);


insert_tag(
	$lang_tags['text_youtube'],
	$lang_tags['text_youtube_description'],
	$lang_tags['text_youtube_syntax'],
	$lang_tags['text_youtube_example'],
	""
);
/*
insert_tag(
	$lang_tags['text_youku'],
	$lang_tags['text_youku_description'],
	$lang_tags['text_youku_syntax'],
	$lang_tags['text_youku_example'],
	""
);

insert_tag(
	$lang_tags['text_tudou'],
	$lang_tags['text_tudou_description'],
	$lang_tags['text_tudou_syntax'],
	$lang_tags['text_tudou_example'],
	""
);
if ($cc98holder == 'yes')
insert_tag(
	$lang_tags['text_ninety_eight_image'],
	$lang_tags['text_ninety_eight_image_description'],
	$lang_tags['text_ninety_eight_image_syntax'],
	$lang_tags['text_ninety_eight_image_example'],
	$lang_tags['text_ninety_eight_image_remarks']
);*/


insert_tag(
    $lang_tags['text_spoiler'],
    $lang_tags['text_spoiler_description'],
    $lang_tags['text_spoiler_syntax'],
    $lang_tags['text_spoiler_example'],
    ""
);

end_frame();
end_main_frame();
stdfoot();
?>

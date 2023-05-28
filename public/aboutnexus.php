<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());

stdhead(PROJECTNAME);
print ("<h1>".PROJECTNAME."</h1>");
begin_main_frame();
begin_frame("<span id=\"version\">".$lang_aboutnexus['text_version']."</span>");
print ($lang_aboutnexus['text_version_note']);
print ("<br /><br /><table class=\"main\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\" align=\"center\">");
tr($lang_aboutnexus['text_main_version'],PROJECTNAME,1);
tr($lang_aboutnexus['text_sub_version'],VERSION_NUMBER,1);
tr($lang_aboutnexus['text_release_date'],RELEASE_DATE,1);
print ("</table>");
print ("<br /><br />");
end_frame();
begin_frame("<span id=\"nexus\">".$lang_aboutnexus['text_nexus'].PROJECTNAME."</span>");
print (PROJECTNAME.$lang_aboutnexus['text_nexus_note']);
print ("<br /><br />");
end_frame();
begin_frame("<span id=\"authorization\">".$lang_aboutnexus['text_authorization']."</span>");
print ($lang_aboutnexus['text_authorization_note']);
print ("<br /><br />");
end_frame();
$ppl = '';
$res = sql_query("SELECT * FROM language ORDER BY trans_state") or sqlerr();
while ($arr = mysql_fetch_assoc($res))
{
	$ppl .= "<tr><td class=\"rowfollow\"><img width=\"24\" height=\"15\" src=\"pic/flag/".$arr['flagpic']."\" alt=\"".$arr['lang_name']."\" title=\"".$arr['lang_name']."\" style=\"padding-bottom:1px;\" /></td>
 <td class=\"rowfollow\">".$arr['lang_name']."</td>".
 "<td class=\"rowfollow\">".$arr['trans_state']."</td></tr>\n";
}
begin_frame("<span id=\"translation\">".$lang_aboutnexus['text_translation']."</span>");
print (PROJECTNAME.$lang_aboutnexus['text_translation_note']);
print ("<br /><br /><table class=\"main\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\" align=\"center\"><tr><td class=\"colhead\">".$lang_aboutnexus['text_flag']."</td><td class=\"colhead\">".$lang_aboutnexus['text_language']."</td><td class=\"colhead\">".$lang_aboutnexus['text_state']."</td></tr>");
print ($ppl);
print ("</table>");
print ("<br /><br />");
end_frame();
$ppl = '';
$res = sql_query("SELECT * FROM stylesheets ORDER BY id") or sqlerr();
while ($arr = mysql_fetch_assoc($res))
{
	$ppl .= "<tr><td class=\"rowfollow\">".$arr['name']."</td>
 <td class=\"rowfollow\">".$arr['designer']."</td>".
 "<td class=\"rowfollow\">".$arr['comment']."</td></tr>\n";
}
begin_frame("<span id=\"stylesheet\">".$lang_aboutnexus['text_stylesheet']."</span>");
print ($lang_aboutnexus['text_stylesheet_note']);
print ("<br /><br /><table class=\"main\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\" align=\"center\"><tr><td class=\"colhead\">".$lang_aboutnexus['text_name']."</td><td class=\"colhead\">".$lang_aboutnexus['text_designer']."</td><td class=\"colhead\">".$lang_aboutnexus['text_comment']."</td></tr>");
print ($ppl);
print ("</table>");
print ("<br /><br />");
end_frame();
begin_frame("<span id=\"contact\">".$lang_aboutnexus['text_contact'].PROJECTNAME."</span>");
print ($lang_aboutnexus['text_contact_note']);
print ("<br /><br /><table class=\"main\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\" align=\"center\">");
tr($lang_aboutnexus['text_web_site'],'<a href="' . NEXUSPHPURL . '" target="_blank">' . NEXUSPHPURL . '</a>',1);
print ("</table>");
print ("<br /><br />");
end_frame();
end_main_frame();
stdfoot();
?>

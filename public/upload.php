<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
parked();

if ($CURUSER["uploadpos"] == 'no')
	stderr($lang_upload['std_sorry'], $lang_upload['std_unauthorized_to_upload'],false);

if ($enableoffer == 'yes')
	$has_allowed_offer = get_row_count("offers","WHERE allowed='allowed' AND userid = ". sqlesc($CURUSER["id"]));
else $has_allowed_offer = 0;
$uploadfreely = user_can_upload("torrents");
$allowtorrents = ($has_allowed_offer || $uploadfreely);
$allowspecial = user_can_upload("music");

if (!$allowtorrents && !$allowspecial)
	stderr($lang_upload['std_sorry'],$lang_upload['std_please_offer'],false);
$allowtwosec = ($allowtorrents && $allowspecial);

$brsectiontype = $browsecatmode;
$spsectiontype = $specialcatmode;
$showsource = (($allowtorrents && get_searchbox_value($brsectiontype, 'showsource')) || ($allowspecial && get_searchbox_value($spsectiontype, 'showsource'))); //whether show sources or not
$showmedium = (($allowtorrents && get_searchbox_value($brsectiontype, 'showmedium')) || ($allowspecial && get_searchbox_value($spsectiontype, 'showmedium'))); //whether show media or not
$showcodec = (($allowtorrents && get_searchbox_value($brsectiontype, 'showcodec')) || ($allowspecial && get_searchbox_value($spsectiontype, 'showcodec'))); //whether show codecs or not
$showstandard = (($allowtorrents && get_searchbox_value($brsectiontype, 'showstandard')) || ($allowspecial && get_searchbox_value($spsectiontype, 'showstandard'))); //whether show standards or not
$showprocessing = (($allowtorrents && get_searchbox_value($brsectiontype, 'showprocessing')) || ($allowspecial && get_searchbox_value($spsectiontype, 'showprocessing'))); //whether show processings or not
$showteam = (($allowtorrents && get_searchbox_value($brsectiontype, 'showteam')) || ($allowspecial && get_searchbox_value($spsectiontype, 'showteam'))); //whether show teams or not
$showaudiocodec = (($allowtorrents && get_searchbox_value($brsectiontype, 'showaudiocodec')) || ($allowspecial && get_searchbox_value($spsectiontype, 'showaudiocodec'))); //whether show languages or not

$settingMain = get_setting('main');

stdhead($lang_upload['head_upload']);
?>
	<form id="compose" enctype="multipart/form-data" action="takeupload.php" method="post" name="upload">
			<?php
			print("<p align=\"center\">".$lang_upload['text_red_star_required']."</p>");
			?>
			<table border="1" cellspacing="0" cellpadding="5" width="97%">
				<tr>
					<td class='colhead' colspan='2' align='center'>
						<?php echo $lang_upload['text_tracker_url'] ?>: &nbsp;&nbsp;&nbsp;&nbsp;<b><?php echo  get_tracker_schema_and_host(true)?></b>
						<?php
						if(!is_writable(getFullDirectory($torrent_dir)))
						print("<br /><br /><b>ATTENTION</b>: Torrent directory isn't writable. Please contact the administrator about this problem!");
						if(!$max_torrent_size)
						print("<br /><br /><b>ATTENTION</b>: Max. Torrent Size not set. Please contact the administrator about this problem!");
						?>
					</td>
				</tr>
				<?php
				tr($lang_upload['row_torrent_file']."<font color=\"red\">*</font>", "<input type=\"file\" class=\"file\" id=\"torrent\" name=\"file\" onchange=\"getname()\" />\n", 1);
				if ($altname_main == 'yes'){
					tr($lang_upload['row_torrent_name'], "<b>".$lang_upload['text_english_title']."</b>&nbsp;<input type=\"text\" style=\"width: 250px;\" name=\"name\" />&nbsp;&nbsp;&nbsp;
<b>".$lang_upload['text_chinese_title']."</b>&nbsp;<input type=\"text\" style=\"width: 250px\" name=\"cnname\"><br /><font class=\"medium\">".$lang_upload['text_titles_note']."</font>", 1);
				}
				else
					tr($lang_upload['row_torrent_name'], "<input type=\"text\" style=\"width: 99%;\" id=\"name\" name=\"name\" /><br /><font class=\"medium\">".$lang_upload['text_torrent_name_note']."</font>", 1);
				if ($smalldescription_main == 'yes')
				tr($lang_upload['row_small_description'], "<input type=\"text\" style=\"width: 99%;\" name=\"small_descr\" /><br /><font class=\"medium\">".$lang_upload['text_small_description_note']."</font>", 1);
				get_external_tr();
				if ($settingMain['enable_pt_gen_system'] == 'yes') {
                    $ptGen = new \Nexus\PTGen\PTGen();
                    echo $ptGen->renderUploadPageFormInput("");
                }
                $field = new \Nexus\Field\Field();
                $field->renderOnUploadPage();
				if ($enablenfo_main=='yes') {
                    tr($lang_upload['row_nfo_file'], "<input type=\"file\" class=\"file\" name=\"nfo\" /><br /><font class=\"medium\">".$lang_upload['text_only_viewed_by'].get_user_class_name($viewnfo_class,false,true,true).$lang_upload['text_or_above']."</font>", 1);
                }
				print("<tr><td class=\"rowhead\" style='padding: 3px' valign=\"top\">".$lang_upload['row_description']."<font color=\"red\">*</font></td><td class=\"rowfollow\">");
				textbbcode("upload","descr", "", false, 130, true);
				print("</td></tr>\n");

                if ($settingMain['enable_technical_info'] == 'yes') {
                    tr($lang_functions['text_technical_info'], '<textarea name="technical_info" rows="8" style="width: 99%;"></textarea><br/>' . $lang_functions['text_technical_info_help_text'], 1);
                }

				if ($allowtorrents){
					$disablespecial = " onchange=\"disableother('browsecat','specialcat')\"";
					$s = "<select name=\"type\" id=\"browsecat\" ".($allowtwosec ? $disablespecial : "").">\n<option value=\"0\">".$lang_upload['select_choose_one']."</option>\n";
					$cats = genrelist($browsecatmode);
					foreach ($cats as $row)
						$s .= "<option value=\"" . $row["id"] . "\">" . htmlspecialchars($row["name"]) . "</option>\n";
					$s .= "</select>\n";
				}
				else $s = "";
				if ($allowspecial){
					$disablebrowse = " onchange=\"disableother('specialcat','browsecat')\"";
					$s2 = "<select name=\"type\" id=\"specialcat\" ".$disablebrowse.">\n<option value=\"0\">".$lang_upload['select_choose_one']."</option>\n";
					$cats2 = genrelist($specialcatmode);
					foreach ($cats2 as $row)
						$s2 .= "<option value=\"" . $row["id"] . "\">" . htmlspecialchars($row["name"]) . "</option>\n";
					$s2 .= "</select>\n";
				}
				else $s2 = "";
				tr($lang_upload['row_type']."<font color=\"red\">*</font>", ($allowtwosec ? $lang_upload['text_to_browse_section'] : "").$s.($allowtwosec ? $lang_upload['text_to_special_section'] : "").$s2.($allowtwosec ? $lang_upload['text_type_note'] : ""),1);

				if ($showsource || $showmedium || $showcodec || $showaudiocodec || $showstandard || $showprocessing){
					if ($showsource){
						$source_select = torrent_selection($lang_upload['text_source'],"source_sel","sources");
					}
					else $source_select = "";

					if ($showmedium){
						$medium_select = torrent_selection($lang_upload['text_medium'],"medium_sel","media");
					}
					else $medium_select = "";

					if ($showcodec){
						$codec_select = torrent_selection($lang_upload['text_codec'],"codec_sel","codecs");
					}
					else $codec_select = "";

					if ($showaudiocodec){
						$audiocodec_select = torrent_selection($lang_upload['text_audio_codec'],"audiocodec_sel","audiocodecs");
					}
					else $audiocodec_select = "";

					if ($showstandard){
						$standard_select = torrent_selection($lang_upload['text_standard'],"standard_sel","standards");
					}
					else $standard_select = "";

					if ($showprocessing){
						$processing_select = torrent_selection($lang_upload['text_processing'],"processing_sel","processings");
					}
					else $processing_select = "";

					tr($lang_upload['row_quality'], $source_select . $medium_select. $codec_select . $audiocodec_select. $standard_select . $processing_select, 1 );
				}

				if ($showteam){
					if ($showteam){
						$team_select = torrent_selection($lang_upload['text_team'],"team_sel","teams");
					}
					else $showteam = "";

					tr($lang_upload['row_content'],$team_select,1);
				}

				//==== offer dropdown for offer mod  from code by S4NE
				$offerres = sql_query("SELECT id, name FROM offers WHERE userid = ".sqlesc($CURUSER['id'])." AND allowed = 'allowed' ORDER BY name ASC") or sqlerr(__FILE__, __LINE__);
				if (mysql_num_rows($offerres) > 0)
				{
					$offer = "<select name=\"offer\"><option value=\"0\">".$lang_upload['select_choose_one']."</option>";
					while($offerrow = mysql_fetch_array($offerres))
						$offer .= "<option value=\"" . $offerrow["id"] . "\">" . htmlspecialchars($offerrow["name"]) . "</option>";
					$offer .= "</select>";
					tr($lang_upload['row_your_offer']. (!$uploadfreely && !$allowspecial ? "<font color=red>*</font>" : ""), $offer.$lang_upload['text_please_select_offer'] , 1);
					$getOfferJs = <<<JS
jQuery('select[name="offer"]').on("change", function () {
    let id = this.value
    if (id == 0) {
        return
    }
    let params = {action: "getOffer", params: {id: id}}
    jQuery.post("ajax.php", params, function (response) {
        console.log(response)
        if (response.ret != 0) {
            alert(response.msg)
            return
        }
        jQuery("#name").val(response.data.name)
        clearContent()
        doInsert(response.data.descr, '', false)
        jQuery("#specialcat").prop('disabled', false).val(0)
        jQuery("#browsecat").prop('disabled', false).val(response.data.category)
    }, 'json')
})
JS;
					\Nexus\Nexus::js($getOfferJs, 'footer', false);

				}
				//===end

				if(get_user_class()>=$beanonymous_class)
				{
					tr($lang_upload['row_show_uploader'], "<input type=\"checkbox\" name=\"uplver\" value=\"yes\" />".$lang_upload['checkbox_hide_uploader_note'], 1);
				}
                tr($lang_functions['text_tags'], (new \App\Repositories\TagRepository())->renderCheckbox(), 1);
				?>
				<tr><td class="toolbox" align="center" colspan="2"><b><?php echo $lang_upload['text_read_rules']?></b> <input id="qr" type="submit" class="btn" value="<?php echo $lang_upload['submit_upload']?>" /></td></tr>
		</table>
	</form>
<?php
\Nexus\Nexus::js('vendor/jquery-loading/jquery.loading.min.js', 'footer', true);
\Nexus\Nexus::js('js/ptgen.js', 'footer', true);
stdfoot();

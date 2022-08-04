<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
parked();

if (get_user_class() < UC_SYSOP)
permissiondenied();

function go_back()
{
	global $lang_settings;
	\Nexus\Database\NexusDB::cache_del('nexus_settings_in_laravel');
	\Nexus\Database\NexusDB::cache_del('nexus_settings_in_nexus');
	stdmsg($lang_settings['std_message'], $lang_settings['std_click']."<a class=\"altlink\" href=\"settings.php\">".$lang_settings['std_here']."</a>".$lang_settings['std_to_go_back']);
}

function yesorno($title, $name, $value, $note="")
{
	global $lang_settings;
	tr($title, "<input type='radio' id='".$name."yes' name='".$name."'".($value == "yes" ? " checked=\"checked\"" : "")." value='yes' /> <label for='".$name."yes'>".$lang_settings['text_yes']."</label> <input type='radio' id='".$name."no' name='".$name."'".($value == "no" ? " checked=\"checked\"" : "")." value='no' /> <label for='".$name."no'>".$lang_settings['text_no']."</label><br />".$note, 1);
}

$action = isset($_POST['action']) ? $_POST['action'] : 'showmenu';
$allowed_actions = array('basicsettings','mainsettings','smtpsettings','securitysettings','authoritysettings','tweaksettings', 'botsettings','codesettings','bonussettings','accountsettings','torrentsettings', 'attachmentsettings', 'advertisementsettings', 'savesettings_basic', 'savesettings_main','savesettings_smtp','savesettings_security','savesettings_authority','savesettings_tweak','savesettings_bot','savesettings_code','savesettings_bonus', 'savesettings_account','savesettings_torrent', 'savesettings_attachment', 'savesettings_advertisement', 'showmenu', 'miscsettings', 'savesettings_misc');
if (!in_array($action, $allowed_actions))
$action = 'showmenu';
$notice = "<h1 align=\"center\"><a class=\"faqlink\" href=\"settings.php\">".$lang_settings['text_website_settings']."</a></h1><table cellspacing=\"0\" cellpadding=\"10\" width=\"97%\"><tr><td colspan=\"2\" style='padding: 10px; background: black' align=\"center\">
<font color=\"white\">".$lang_settings['text_configuration_file_saving_note']."
</font></td></tr>";

if ($action == 'savesettings_main')	// save main
{
	do_log(json_encode($_REQUEST));
	stdhead($lang_settings['head_save_main_settings']);
	$validConfig = array(
		'site_online','max_torrent_size','announce_interval', 'annintertwoage', 'annintertwo', 'anninterthreeage', 'anninterthree', 'signup_timeout',
		'minoffervotes','offervotetimeout','offeruptimeout','maxsubsize','postsperpage', 'topicsperpage', 'torrentsperpage', 'maxnewsnum',
		'max_dead_torrent_time','maxusers','torrent_dir', 'iniupload','SITEEMAIL', 'ACCOUNTANTID', 'ALIPAYACCOUNT', 'PAYPALACCOUNT', 'SLOGAN',
		'icplicense', 'autoclean_interval_one', 'autoclean_interval_two', 'autoclean_interval_three','autoclean_interval_four', 'autoclean_interval_five',
		'reportemail','invitesystem','registration','showhotmovies','showclassicmovies','showimdbinfo', 'enable_pt_gen_system', 'pt_gen_api_point', 'enablenfo', 'enableschool','restrictemail',
		'showpolls','showstats','showlastxtorrents', 'showtrackerload','showshoutbox','showfunbox','showoffer','sptime','showhelpbox','enablebitbucket',
		'smalldescription','altname','extforum','extforumurl','defaultlang','defstylesheet', 'donation','spsct','browsecat','specialcat','waitsystem',
		'maxdlsystem','bitbucket','torrentnameprefix', 'showforumstats','verification','invite_count','invite_timeout', 'seeding_leeching_time_calc_start',
		'startsubid', 'logo', 'showlastxforumposts', 'enable_technical_info', 'site_language_enabled', 'show_top_uploader', 'imdb_language',
	);
	GetVar($validConfig);
	$MAIN = [];
	foreach($validConfig as $config) {
		$MAIN[$config] = $$config ?? null;
	}

	saveSetting('main', $MAIN);
	$Cache->delete_value('recent_news', true);
	$Cache->delete_value('stats_users', true);
	$Cache->delete_value('stats_torrents', true);
	$Cache->delete_value('peers_count', true);
    $Cache->delete_value('site_lang_lang_list', true);
	$actiontime = date("F j, Y, g:i a");
	write_log("Tracker MAIN settings updated by {$CURUSER['username']}. $actiontime",'mod');
	go_back();
}
elseif ($action == 'savesettings_basic') 	// save basic
{
	stdhead($lang_settings['head_save_basic_settings']);
	$validConfig = array(
		'SITENAME', 'BASEURL', 'announce_url'
	);
	GetVar($validConfig);
	$BASIC = [];
	foreach($validConfig as $config) {
		$BASIC[$config] = $$config ?? null;
	}
	saveSetting('basic', $BASIC);
	$actiontime = date("F j, Y, g:i a");
	write_log("Tracker basic settings updated by {$CURUSER['username']}. $actiontime",'mod');
	go_back();
}
elseif ($action == 'savesettings_code') 	// save database
{
	stdhead($lang_settings['head_save_code_settings']);
	$validConfig = array('mainversion','subversion','releasedate','website');
	GetVar($validConfig);
	$CODE = [];
	foreach($validConfig as $config) {
		$CODE[$config] = $$config ?? null;
	}
	saveSetting('code', $CODE);
	$actiontime = date("F j, Y, g:i a");
	write_log("Tracker code settings updated by {$CURUSER['username']}. $actiontime",'mod');
	go_back();
}
elseif ($action == 'savesettings_bonus') 	// save bonus
{
	stdhead($lang_settings['head_save_bonus_settings']);
	$validConfig = array(
	    'donortimes','perseeding','maxseeding','tzero','nzero','bzero','l', 'uploadtorrent','uploadsubtitle','starttopic','makepost',
        'addcomment','pollvote','offervote', 'funboxvote','saythanks','receivethanks','funboxreward','onegbupload','fivegbupload',
        'tengbupload', 'ratiolimit','dlamountlimit','oneinvite','customtitle','vipstatus','bonusgift', 'basictax', 'taxpercentage',
        'prolinkpoint', 'prolinktime', 'attendance_initial', 'attendance_step', 'attendance_max', 'cancel_hr', 'attendance_card',
        'harem_addition'
    );
	GetVar($validConfig);
	$BONUS = [];
	foreach($validConfig as $config) {
		$BONUS[$config] = $$config ?? null;
	}
	$BONUS['attendance_continuous'] = array();
	if(count($_POST['attendance_continuous_day']) == count($_POST['attendance_continuous_value'])){
		foreach($_POST['attendance_continuous_day'] as $k => $day){
			$value = (int) $_POST['attendance_continuous_value'][$k];
			if($day > 0 && $value > 0) $BONUS['attendance_continuous'][$day] = $value;
		}
	}
	ksort($BONUS['attendance_continuous']);
	saveSetting('bonus', $BONUS);
	$actiontime = date("F j, Y, g:i a");
	write_log("Tracker bonus settings updated by {$CURUSER['username']}. $actiontime",'mod');
	go_back();
}
elseif ($action == 'savesettings_account') 	// save account
{
	stdhead($lang_settings['head_save_account_settings']);

	$validConfig = array(
	    'neverdelete', 'neverdeletepacked', 'deletepacked', 'deleteunpacked', 'deletenotransfer', 'deletenotransfertwo', 'deletepeasant',
        'psdlone', 'psratioone', 'psdltwo', 'psratiotwo', 'psdlthree', 'psratiothree', 'psdlfour', 'psratiofour', 'psdlfive', 'psratiofive', \App\Models\User::CLASS_PEASANT . '_alias',
        \App\Models\User::CLASS_USER . '_alias',
        'putime', 'pudl', \App\Models\User::CLASS_POWER_USER . '_min_seed_points', 'puprratio', 'puderatio', \App\Models\User::CLASS_POWER_USER . '_alias',
        'eutime', 'eudl', \App\Models\User::CLASS_ELITE_USER . '_min_seed_points', 'euprratio', 'euderatio', \App\Models\User::CLASS_ELITE_USER . '_alias',
        'cutime', 'cudl', \App\Models\User::CLASS_CRAZY_USER . '_min_seed_points', 'cuprratio', 'cuderatio', \App\Models\User::CLASS_CRAZY_USER . '_alias',
        'iutime', 'iudl', \App\Models\User::CLASS_INSANE_USER . '_min_seed_points', 'iuprratio', 'iuderatio', \App\Models\User::CLASS_INSANE_USER . '_alias',
        'vutime', 'vudl', \App\Models\User::CLASS_VETERAN_USER . '_min_seed_points', 'vuprratio', 'vuderatio', \App\Models\User::CLASS_VETERAN_USER . '_alias',
        'exutime', 'exudl', \App\Models\User::CLASS_EXTREME_USER . '_min_seed_points', 'exuprratio', 'exuderatio', \App\Models\User::CLASS_EXTREME_USER . '_alias',
        'uutime', 'uudl', \App\Models\User::CLASS_ULTIMATE_USER . '_min_seed_points', 'uuprratio', 'uuderatio', \App\Models\User::CLASS_ULTIMATE_USER . '_alias',
        'nmtime', 'nmdl', \App\Models\User::CLASS_NEXUS_MASTER . '_min_seed_points', 'nmprratio', 'nmderatio', \App\Models\User::CLASS_NEXUS_MASTER . '_alias',
        'getInvitesByPromotion'
    );
	GetVar($validConfig);
	$ACCOUNT = [];
	foreach($validConfig as $config) {
		$ACCOUNT[$config] = $$config ?? null;
	}
	saveSetting('account', $ACCOUNT);
	$actiontime = date("F j, Y, g:i a");
	write_log("Tracker account settings updated by {$CURUSER['username']}. $actiontime",'mod');
	\Nexus\Database\NexusDB::cache_del('stats_classes');
	go_back();
}
elseif($action == 'savesettings_torrent') 	// save account
{
	stdhead($lang_settings['head_save_torrent_settings']);
	$validConfig = array(
	    'prorules', 'randomhalfleech','randomfree','randomtwoup','randomtwoupfree','randomtwouphalfdown','largesize', 'largepro','expirehalfleech',
        'expirefree','expiretwoup','expiretwoupfree','expiretwouphalfleech', 'expirenormal','hotdays','hotseeder','halfleechbecome','freebecome',
        'twoupbecome','twoupfreebecome', 'twouphalfleechbecome','normalbecome','uploaderdouble','deldeadtorrent', 'randomthirtypercentdown',
        'thirtypercentleechbecome', 'expirethirtypercentleech', 'sticky_first_level_background_color', 'sticky_second_level_background_color',
        'download_support_passkey', 'claim_enabled', 'claim_torrent_ttl', 'claim_torrent_user_counts_up_limit', 'claim_user_torrent_counts_up_limit', 'claim_remove_deduct_user_bonus',
        'claim_give_up_deduct_user_bonus', 'claim_bonus_multiplier', 'claim_reach_standard_seed_time', 'claim_reach_standard_uploaded', 'approval_status_icon_enabled', 'approval_status_none_visible'
    );
	$validConfig = apply_filter('setting_valid_config', $validConfig);
	GetVar($validConfig);
	$TORRENT = [];
	foreach($validConfig as $config) {
		$TORRENT[$config] = $$config ?? null;
	}

	saveSetting('torrent', $TORRENT);
	$actiontime = date("F j, Y, g:i a");
	write_log("Tracker torrent settings updated by {$CURUSER['username']}. $actiontime",'mod');
	go_back();
}
elseif ($action == 'savesettings_smtp') 	// save smtp
{
	stdhead($lang_settings['head_save_smtp_settings']);
	$validConfig = array('smtptype', 'emailnotify');
	GetVar($validConfig);
	if (isset($smtptype) && $smtptype == 'advanced') {
		$validConfig = array_merge($validConfig, array('smtp_host','smtp_port','smtp_from'));
	} elseif ($smtptype == 'external') {
		$validConfig = array_merge($validConfig, array('smtpaddress','smtpport', 'encryption', 'accountname','accountpassword'));
	}

	GetVar($validConfig);
	$SMTP = [];
	foreach($validConfig as $config) {
		$SMTP[$config] = $$config ?? null;
	}
	saveSetting('smtp', $SMTP);
	$actiontime = date("F j, Y, g:i a");
	write_log("Tracker SMTP settings updated by {$CURUSER['username']}. $actiontime",'mod');
	go_back();
}
elseif ($action == 'savesettings_security') 	// save security
{
	stdhead($lang_settings['head_save_security_settings']);
	$validConfig = array(
		'securelogin', 'securetracker', 'https_announce_url','iv','maxip','maxloginattempts','changeemail','cheaterdet','nodetect',
		'guest_visit_type', 'guest_visit_value_static_page', 'guest_visit_value_custom_content', 'guest_visit_value_redirect',
		'login_type', 'login_secret_lifetime',
	);
	GetVar($validConfig);
	$SECURITY = [];
	foreach($validConfig as $config) {
		$SECURITY[$config] = $$config ?? null;
	}
	if ($_POST['login_secret_regenerate'] == 'yes') {
		$minute = intval($_POST['login_secret_lifetime']);
		$SECURITY['login_secret_deadline'] = date('Y-m-d H:i:s', strtotime("+ $minute minutes"));
		$SECURITY['login_secret'] = md5(microtime(true));
	}
	saveSetting('security', $SECURITY);
	$actiontime = date("F j, Y, g:i a");
	write_log("Tracker SECURITY settings updated by {$CURUSER['username']}. $actiontime",'mod');
	go_back();
}
elseif ($action == 'savesettings_authority') 	// save user authority
{
	stdhead($lang_settings['head_save_authority_settings']);
	$validConfig = array('defaultclass','staffmem','newsmanage','newfunitem','funmanage','sbmanage','pollmanage','applylink', 'linkmanage', 'postmanage','commanage','forummanage','viewuserlist','torrentmanage','torrentsticky', 'torrentonpromotion', 'torrent_hr', 'askreseed', 'viewnfo', 'torrentstructure','sendinvite','viewhistory','topten','log','confilog','userprofile', 'torrenthistory','prfmanage', 'cruprfmanage','uploadsub','delownsub','submanage','updateextinfo', 'viewanonymous','beanonymous','addoffer','offermanage', 'upload','uploadspecial','view_special_torrent','movetorrent','chrmanage','viewinvite', 'buyinvite','seebanned','againstoffer','userbar');
	GetVar($validConfig);
	$AUTHORITY = [];
	foreach($validConfig as $config) {
		$AUTHORITY[$config] = $$config ?? null;
	}

	saveSetting('authority', $AUTHORITY);
	$actiontime = date("F j, Y, g:i a");
	write_log("Tracker USER AUTHORITY settings updated by {$CURUSER['username']}. $actiontime",'mod');
	go_back();
}
elseif ($action == 'savesettings_tweak')	// save tweak
{
	stdhead($lang_settings['head_save_tweak_settings']);
	$validConfig = array('where','iplog1','bonus','datefounded', 'enablelocation', 'titlekeywords', 'metakeywords', 'metadescription', 'enablesqldebug', 'sqldebug', 'cssdate', 'enabletooltip', 'prolinkimg', 'analyticscode');
	GetVar($validConfig);
	$TWEAK = [];
	foreach($validConfig as $config) {
		$TWEAK[$config] = $$config ?? null;
	}
	saveSetting('tweak', $TWEAK);
	$actiontime = date("F j, Y, g:i a");
	write_log("Tracker TWEAK settings updated by {$CURUSER['username']}. $actiontime",'mod');
	go_back();
}
elseif ($action == 'savesettings_attachment')	// save attachment
{
	stdhead($lang_settings['head_save_attachment_settings']);
	$validConfig = array('enableattach','classone','countone','sizeone', 'extone', 'classtwo','counttwo','sizetwo', 'exttwo', 'classthree','countthree','sizethree', 'extthree', 'classfour','countfour','sizefour', 'extfour', 'savedirectory', 'httpdirectory', 'savedirectorytype', 'thumbnailtype', 'thumbquality', 'thumbwidth', 'thumbheight', 'watermarkpos', 'watermarkwidth', 'watermarkheight', 'watermarkquality', 'altthumbwidth', 'altthumbheight');
	GetVar($validConfig);
	$ATTACHMENT = [];
	foreach($validConfig as $config) {
		$ATTACHMENT[$config] = $$config ?? null;
	}

	saveSetting('attachment', $ATTACHMENT);
	$actiontime = date("F j, Y, g:i a");
	write_log("Tracker ATTACHMENT settings updated by {$CURUSER['username']}. $actiontime",'mod');
	go_back();
}
elseif ($action == 'savesettings_advertisement')	// save advertisement
{
	stdhead($lang_settings['head_save_advertisement_settings']);
	$validConfig = array('enablead', 'enablenoad', 'noad', 'enablebonusnoad', 'bonusnoad', 'bonusnoadpoint', 'bonusnoadtime', 'adclickbonus');
	GetVar($validConfig);
	$ADVERTISEMENT = [];
	foreach($validConfig as $config) {
		$ADVERTISEMENT[$config] = $$config ?? null;
	}

	saveSetting('advertisement', $ADVERTISEMENT);
	$actiontime = date("F j, Y, g:i a");
	write_log("Tracker ADVERTISEMENT settings updated by {$CURUSER['username']}. $actiontime",'mod');
	go_back();
}
elseif ($action == 'savesettings_misc')
{
    stdhead($lang_settings['row_misc_settings']);
    $validConfig = array('donation_custom', );
    GetVar($validConfig);
    $data = [];
    foreach($validConfig as $config) {
        $data[$config] = $$config ?? null;
    }
    saveSetting('misc', $data, 'no');
    $actiontime = date("F j, Y, g:i a");
    write_log("Misc settings updated by {$CURUSER['username']}. $actiontime",'mod');
    go_back();
}
elseif ($action == 'tweaksettings')		// tweak settings
{
	$TWEAK = get_setting_from_db('tweak');
	stdhead($lang_settings['head_tweak_settings']);
	print ($notice);
	print ("<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='savesettings_tweak' />");
	yesorno($lang_settings['row_save_user_location'], 'where', $TWEAK["where"], $lang_settings['text_save_user_location_note']);
	yesorno($lang_settings['row_log_user_ips'], 'iplog1', $TWEAK["iplog1"], $lang_settings['text_store_user_ips_note']);
	tr($lang_settings['row_kps_enabled'],"<input type='radio' id='bonusenable' name='bonus'" . ($TWEAK["bonus"] == "enable" ? " checked='checked'" : "") . " value='enable' /> <label for='bonusenable'>".$lang_settings['text_enabled']."</label> <input type='radio' id='bonusdisablesave' name='bonus'" . ($TWEAK["bonus"] == "disablesave" ? " checked='checked'" : "") . " value='disablesave' /> <label for='bonusdisablesave'>".$lang_settings['text_disabled_but_save']."</label> <input type='radio' id='bonusdisable' name='bonus'" . ($TWEAK["bonus"] == "disable" ? " checked='checked'" : "") . " value='disable' /> <label for='bonusdisable'>".$lang_settings['text_disabled_no_save']."</label> <br />".$lang_settings['text_kps_note'], 1);
	yesorno($lang_settings['row_enable_location'], 'enablelocation', $TWEAK["enablelocation"], $lang_settings['text_enable_location_note']);
	yesorno($lang_settings['row_enable_tooltip'], 'enabletooltip', $TWEAK["enabletooltip"], $lang_settings['text_enable_tooltip_note']);
	tr($lang_settings['row_title_keywords'],"<input type='text' style=\"width: 300px\" name='titlekeywords' value='".($TWEAK["titlekeywords"] ? $TWEAK["titlekeywords"] : '')."' /> <br />".$lang_settings['text_title_keywords_note'], 1);
	tr($lang_settings['row_promotion_link_example_image'],"<input type='text' style=\"width: 300px\" name='prolinkimg' value='".($TWEAK["prolinkimg"] ? $TWEAK["prolinkimg"] : 'pic/prolink.png')."' /> <br />".$lang_settings['text_promotion_link_example_note'], 1);
	tr($lang_settings['row_meta_keywords'],"<input type='text' style=\"width: 300px\" name='metakeywords' value='".($TWEAK["metakeywords"] ? $TWEAK["metakeywords"] : '')."' /> <br />".$lang_settings['text_meta_keywords_note'], 1);
	tr($lang_settings['row_meta_description'],"<textarea cols=\"100\" style=\"width: 450px;\" rows=\"5\" name='metadescription'>".($TWEAK["metadescription"] ? $TWEAK["metadescription"] : '')."</textarea> <br />".$lang_settings['text_meta_description_note'], 1);
	tr($lang_settings['row_web_analytics_code'],"<textarea cols=\"100\" style=\"width: 450px;\" rows=\"5\" name='analyticscode'>".($TWEAK["analyticscode"] ? $TWEAK["analyticscode"] : '')."</textarea> <br />".$lang_settings['text_web_analytics_code_note'], 1);
	tr($lang_settings['row_see_sql_debug'], "<input type='checkbox' name='enablesqldebug' value='yes'".($TWEAK['enablesqldebug'] == 'yes' ? " checked='checked'" : "")." />".$lang_settings['text_allow'].classlist('sqldebug',UC_STAFFLEADER,$TWEAK['sqldebug'], UC_MODERATOR).$lang_settings['text_see_sql_list'].get_user_class_name(UC_SYSOP,false,true,true),1);
	tr($lang_settings['row_tracker_founded_date'],"<input type='text' style=\"width: 300px\" name=datefounded value='".($TWEAK["datefounded"] ? $TWEAK["datefounded"] : '2007-12-24')."'> <br />".$lang_settings['text_tracker_founded_date_note'], 1);
	tr($lang_settings['row_css_date'],"<input type='text' style=\"width: 300px\" name=cssdate value='".($TWEAK["cssdate"] ? $TWEAK["cssdate"] : '')."'> <br />".$lang_settings['text_css_date'], 1);

	tr($lang_settings['row_save_settings'],"<input type='submit' name='save' value='".$lang_settings['submit_save_settings']."'>", 1);
	print ("</form>");
}
elseif ($action == 'smtpsettings')	// stmp settings
{
	$SMTP = get_setting_from_db('smtp');
	stdhead($lang_settings['head_smtp_settings']);
	print ($notice);
	print("<tbody>");
	print ("<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='savesettings_smtp'>");
	yesorno($lang_settings['row_enable_email_notification'], 'emailnotify', $SMTP["emailnotify"], $lang_settings['text_email_notification_note']);
	$smtp_select = "<input type=\"radio\" name=\"smtptype\" value=\"default\" onclick=\"document.getElementById('smtp_advanced').style.display='none'; document.getElementById('smtp_external').style.display='none';\"".($SMTP['smtptype'] == "default" ? " checked" : "")."> ". $lang_settings['text_smtp_default'] . "<br /><input type=\"radio\" name=\"smtptype\" value=\"advanced\" onclick=\"document.getElementById('smtp_advanced').style.display=''; document.getElementById('smtp_external').style.display='none';\"".($SMTP['smtptype'] == "advanced" ? " checked" : "")."> " . $lang_settings['text_smtp_advanced']."<br /><input type=\"radio\" name=\"smtptype\" value=\"external\" onclick=\"document.getElementById('smtp_advanced').style.display='none'; document.getElementById('smtp_external').style.display='';\"".($SMTP['smtptype'] == "external" ? " checked" : "")."> " . $lang_settings['text_smtp_external']."<br /><input type=\"radio\" name=\"smtptype\" value=\"none\" onclick=\"document.getElementById('smtp_advanced').style.display='none'; document.getElementById('smtp_external').style.display='none';\"".($SMTP['smtptype'] == "none" ? " checked" : "")."> " . $lang_settings['text_smtp_none'];
	tr($lang_settings['row_mail_function_type'], $smtp_select, 1);
	print("</tbody><tbody id=\"smtp_advanced\"".($SMTP['smtptype'] == "advanced" ? "" : " style=\"display: none;\"").">");
	print("<tr><td colspan=2 align=center><b>".$lang_settings['text_setting_for_advanced_type']."</b></td></tr>");
	tr($lang_settings['row_smtp_host'],"<input type='text' style=\"width: 300px\" name=smtp_host value='".($SMTP['smtp_host'] ? $SMTP['smtp_host'] : "localhost")."'> ".$lang_settings['text_smtp_host_note'], 1);
	tr($lang_settings['row_smtp_port'],"<input type='text' style=\"width: 300px\" name=smtp_port value='".($SMTP['smtp_port'] ? $SMTP['smtp_port'] : "25")."'> ".$lang_settings['text_smtp_port_note'], 1);
	if (strtoupper(substr(PHP_OS,0,3)=='WIN'))
		tr($lang_settings['row_smtp_sendmail_from'], "<input type='text' style=\"width: 300px\" name=smtp_from value='".($SMTP['smtp_from'] ? $SMTP['smtp_from'] : $MAIN["SITEEMAIL"])."'> ".$lang_settings['text_smtp_sendmail_from_note'].$MAIN["SITEEMAIL"], 1);
	else
		tr($lang_settings['row_smtp_sendmail_path'], $lang_settings['text_smtp_sendmail_path_note'], 1);
	print("</tbody><tbody id=\"smtp_external\"".($SMTP['smtptype'] == "external" ? "" : " style=\"display: none;\"").">");
	print("<tr><td colspan=2 align=center><b>".$lang_settings['text_setting_for_external_type']."</b></td></tr>");
	tr($lang_settings['row_outgoing_mail_address'], "<input type=text name=smtpaddress style=\"width: 300px\" ".(!empty($SMTP['smtpaddress']) ? "value=\"".$SMTP['smtpaddress']."\"" : "")."> ".$lang_settings['text_outgoing_mail_address_note'], 1);
	tr($lang_settings['row_outgoing_mail_port'], "<input type=text name=smtpport style=\"width: 300px\" ".(!empty($SMTP['smtpport']) ? "value=\"".$SMTP['smtpport']."\"" : "")."> ".$lang_settings['text_outgoing_mail_port_note'], 1);
	$encryptionRadio = '<label><input type="radio" name="encryption" value="" ' . (empty($SMTP['encryption']) ? " checked" : "") . ' />none</label>';
	$encryptionRadio .= '<label><input type="radio" name="encryption" value="tls"' . ($SMTP['encryption'] == "tls" ? " checked" : "") . '/>tls</label>';
	$encryptionRadio .= '<label><input type="radio" name="encryption" value="ssl"' . ($SMTP['encryption'] == "ssl" ? " checked" : "") . '/>ssl</label>';
	tr($lang_settings['row_outgoing_mail_encryption'], $encryptionRadio, 1);
	tr($lang_settings['row_smtp_account_name'], "<input type=text name=accountname style=\"width: 300px\" ".(!empty($SMTP['accountname']) ? "value=\"".$SMTP['accountname']."\"" : "")."> ".$lang_settings['text_smtp_account_name_note'], 1);
	tr($lang_settings['row_smtp_account_password'], "<input type=password name=accountpassword style=\"width: 300px\" ".(!empty($SMTP['accountpassword']) ? "value=\"".$SMTP['accountpassword']."\"" : "")."> ".$lang_settings['text_smtp_account_password_note'], 1);
	print("</tbody><tbody>");
	tr($lang_settings['row_save_settings'],"<input type='submit' name='save' value='".$lang_settings['submit_save_settings']."'>", 1);
print ("<tr><td colspan=2 align=center>".$lang_settings['text_mail_test_note']."<a href=\"mailtest.php\" target=\"_blank\"><b>".$lang_settings['text_here']."</b></a></td></tr>");
print ("</form>");
print("</tbody>");
}
elseif ($action == 'securitysettings')	//security settings
{
	$SECURITY = get_setting_from_db('security');
	stdhead($lang_settings['head_security_settings']);
	print ($notice);
	print("<tbody>");
	print ("<form method='post' action='".$_SERVER["SCRIPT_NAME"]."' name='securitysettings_form'><input type='hidden' name='action' value='savesettings_security'>");
	tr($lang_settings['row_enable_ssl'],"<input type='radio' name='securelogin'" . ($SECURITY["securelogin"] == "yes" ? " checked" : "") . " value='yes'> ".$lang_settings['text_yes']. " <input type='radio' name='securelogin'" . ($SECURITY["securelogin"] == "no" ? " checked" : "") . " value='no'> ".$lang_settings['text_no']. " <input type='radio' name='securelogin'" . ($SECURITY["securelogin"] == "op" ? " checked" : "") . " value='op'> ".$lang_settings['text_optional']."<br />".$lang_settings['text_ssl_note'], 1);
	tr($lang_settings['row_enable_ssl_tracker'],"<input type='radio' name='securetracker'" . ($SECURITY["securetracker"] == "yes" ? " checked" : "") . " value='yes'> ".$lang_settings['text_yes']. " <input type='radio' name='securetracker'" . ($SECURITY["securetracker"] == "no" ? " checked" : "") . " value='no'> ".$lang_settings['text_no']. " <input type='radio' name='securetracker'" . ($SECURITY["securetracker"] == "op" ? " checked" : "") . " value='op'> ".$lang_settings['text_optional']."<br />".$lang_settings['text_ssl_note'], 1);
	tr($lang_settings['row_https_announce_url'],"<input type='text' style=\"width: 300px\" name=https_announce_url value='".($SECURITY["https_announce_url"] ? $SECURITY["https_announce_url"] : "")."'> ".$lang_settings['text_https_announce_url_note'] . $_SERVER["HTTP_HOST"]."/announce.php", 1);
	yesorno($lang_settings['row_enable_image_verification'], 'iv', $SECURITY["iv"], $lang_settings['text_image_verification_note']);
	yesorno($lang_settings['row_allow_email_change'], 'changeemail', $SECURITY["changeemail"], $lang_settings['text_email_change_note']);
	tr($lang_settings['row_cheater_detection_level'],"<select name='cheaterdet'><option value=0 " . ($SECURITY["cheaterdet"] == 0 ? " selected" : "") . "> ".$lang_settings['select_none']." </option><option value=1 " . ($SECURITY["cheaterdet"] == 1 ? " selected" : "") . "> ".$lang_settings['select_conservative']." </option><option value=2 " . ($SECURITY["cheaterdet"] == 2 ? " selected" : "") . "> ".$lang_settings['select_normal']." </option><option value=3 " . ($SECURITY["cheaterdet"] == 3 ? " selected" : "") . "> ".$lang_settings['select_strict']." </option><option value=4 " . ($SECURITY["cheaterdet"] == 4 ? " selected" : "") . "> ".$lang_settings['select_paranoid']." </option></select> ".$lang_settings['text_cheater_detection_level_note']."<br />".$lang_settings['text_never_suspect'].classlist('nodetect',$AUTHORITY['staffmem'],$SECURITY['nodetect']).$lang_settings['text_or_above'].get_user_class_name(UC_UPLOADER,false,true,true).".", 1);
	tr($lang_settings['row_max_ips'],"<input type='text' style=\"width: 300px\" name=maxip value='" . ($SECURITY["maxip"] ? $SECURITY["maxip"] : "1")."'> ".$lang_settings['text_max_ips_note'], 1);
	tr($lang_settings['row_max_login_attemps'],"<input type='text' style=\"width: 300px\" name=maxloginattempts value='" . ($SECURITY["maxloginattempts"] ? $SECURITY["maxloginattempts"] : "7")."'> ".$lang_settings['text_max_login_attemps_note'], 1);

	$guestVisitTypeRadio = '<label><input type="radio" name="guest_visit_type" value="normal"' . (empty($SECURITY['guest_visit_type']) || $SECURITY['guest_visit_type'] == 'normal' ? ' checked' : '') . ' onclick="document.getElementById(\'tbody_static_page\').style.display=\'none\';document.getElementById(\'tbody_custom_content\').style.display=\'none\';document.getElementById(\'tbody_redirect\').style.display=\'none\';">' . $lang_settings['text_guest_visit_type_normal'] . '</label>';
	$guestVisitTypeRadio .= '<br/><label><input type="radio" name="guest_visit_type" value="static_page"' . ($SECURITY['guest_visit_type'] == 'static_page' ? ' checked' : '') . ' onclick="document.getElementById(\'tbody_static_page\').style.display=\'table-row-group\';document.getElementById(\'tbody_custom_content\').style.display=\'none\';document.getElementById(\'tbody_redirect\').style.display=\'none\';">' . $lang_settings['text_guest_visit_type_static_page'] . '</label>';
	$guestVisitTypeRadio .= '<br/><label><input type="radio" name="guest_visit_type" value="custom_content"' . ($SECURITY['guest_visit_type'] == 'custom_content' ? ' checked' : '') . ' onclick="document.getElementById(\'tbody_static_page\').style.display=\'none\';document.getElementById(\'tbody_custom_content\').style.display=\'table-row-group\';document.getElementById(\'tbody_redirect\').style.display=\'none\';">' . $lang_settings['text_guest_visit_type_custom_content'] . '</label>';
	$guestVisitTypeRadio .= '<br/><label><input type="radio" name="guest_visit_type" value="redirect"' . ($SECURITY['guest_visit_type'] == 'redirect' ? ' checked' : '') . ' onclick="document.getElementById(\'tbody_static_page\').style.display=\'none\';document.getElementById(\'tbody_custom_content\').style.display=\'none\';document.getElementById(\'tbody_redirect\').style.display=\'table-row-group\';">' . $lang_settings['text_guest_visit_type_redirect'] . '</label>';
	tr($lang_settings['row_guest_visit_type'], $guestVisitTypeRadio, 1);
	print '</tbody><tbody id="tbody_static_page" style="display: ' . ($SECURITY['guest_visit_type'] == 'static_page' ? 'table-row-group' : 'none') . '">';
	$guestVisitStaticPageSelect = '<select name="guest_visit_value_static_page">';
	foreach (glob(ROOT_PATH . 'resources/static-pages/*') as $page) {
		$pageName = basename($page);
		$guestVisitStaticPageSelect.= sprintf('<option value="%s"%s>%s</option>', $pageName, $SECURITY['guest_visit_value_static_page'] == $pageName ? ' selected' : '', $pageName);
	}
	$guestVisitStaticPageSelect .= '</select> ' . $lang_settings['text_guest_visit_value_static_page'];
	tr($lang_settings['row_guest_visit_value_static_page'], $guestVisitStaticPageSelect, 1);

	print '</tbody><tbody id="tbody_custom_content" style="display: ' . ($SECURITY['guest_visit_type'] == 'custom_content' ? 'table-row-group' : 'none') . '">';
	print '<tr><td class="rowhead nowrap" valign="top" align="right">' . $lang_settings['row_guest_visit_value_custom_content'] . '</td><td>';
	textbbcode('securitysettings_form', 'guest_visit_value_custom_content', $SECURITY['guest_visit_value_custom_content'] ?? '');
	print '</td></tr>';

	print '</tbody><tbody id="tbody_redirect" style="display: ' . ($SECURITY['guest_visit_type'] == 'redirect' ? 'table-row-group' : 'none') . '">';
	$input = sprintf('<input type="text" name="guest_visit_value_redirect" value="%s" style="width: 300px;" />', $SECURITY['guest_visit_value_redirect'] ?? '');
	tr($lang_settings['row_guest_visit_value_redirect'], $input, 1);
	print '</tbody><tbody>';

	$loginTypeRadio = '<label><input type="radio" name="login_type" value="normal"' . (empty($SECURITY['login_type']) || $SECURITY['login_type'] == 'normal' ? ' checked' : '') . ' onclick="document.getElementById(\'tbody_login_secret\').style.display=\'none\';">' . $lang_settings['text_login_type_normal'] . '</label>';
	$loginTypeRadio .= '<label><input type="radio" name="login_type" value="secret"' . ($SECURITY['login_type'] == 'secret' ? ' checked' : '') . ' onclick="document.getElementById(\'tbody_login_secret\').style.display=\'table-row-group\';">' . $lang_settings['text_login_type_secret'] . '</label>';
	$loginTypeRadio .= '<label><input type="radio" name="login_type" value="passkey"' . ($SECURITY['login_type'] == 'passkey' ? ' checked' : '') . ' onclick="document.getElementById(\'tbody_login_secret\').style.display=\'table-row-group\';">' . $lang_settings['text_login_type_passkey'] . '</label>';
	$loginTypeRadio .= sprintf('<b style="color: #DC143C; margin-left: 20px">%s</b>', $lang_settings['text_login_type_warning']);
	tr($lang_settings['row_login_type'], $loginTypeRadio, 1);

	print '</tbody><tbody id="tbody_login_secret" style="display: ' . (in_array($SECURITY['login_type'], ['secret', 'passkey']) ? 'table-row-group' : 'none') . '">';
	$loginSecret = sprintf('%s：%s', $lang_settings['text_login_secret_current'], $SECURITY['login_secret'] ?? '');
	if (!empty($SECURITY['login_secret'])) {
		$loginSecret .= sprintf('<br/>%s: %s/login.php?secret=%s', $lang_settings['text_login_url_with_secret'], getSchemeAndHttpHost(), $SECURITY['login_secret']);
		$loginSecret .= sprintf('<br/>%s: %s/%s/{passkey}', $lang_settings['text_login_url_with_passkey'], getSchemeAndHttpHost(), $SECURITY['login_secret']);
	}
	$loginSecret .= sprintf('<br/><label><input type="radio" name="login_secret_regenerate" value="no"%s />%s</label>', !empty($SECURITY['login_secret']) ? ' checked' : '', $lang_settings['text_login_secret_regenerate_no']);
	$loginSecret .= sprintf('<br/><label><input type="radio" name="login_secret_regenerate" value="yes"%s />%s</label>', empty($SECURITY['login_secret']) ? ' checked' : '', $lang_settings['text_login_secret_regenerate_yes']);
	tr($lang_settings['row_login_secret'], $loginSecret, 1);

	$loginSecretLifetime = sprintf('<input type="text" name="login_secret_lifetime" value="%s" />%s', $SECURITY['login_secret_lifetime'], $lang_settings['text_login_secret_lifetime_unit']);
	if (!empty($SECURITY['login_secret_lifetime'])) {
		$loginSecretLifetime .= sprintf('<span style="margin-left: 20px">%s: %s</span>', $lang_settings['text_login_secret_lifetime_deadline'], $SECURITY['login_secret_deadline']);
	}
	tr($lang_settings['row_login_secret_lifetime'], $loginSecretLifetime, 1);
	print '</tbody>';

	tr($lang_settings['row_save_settings'],"<input type='submit' name='save' value='".$lang_settings['submit_save_settings']."'>", 1);
	print ("</form>");
}
elseif ($action == 'authoritysettings')	//Authority settings
{
	$AUTHORITY = get_setting_from_db('authority');
	stdhead($lang_settings['head_authority_settings']);
	print ($notice);
	$maxclass = UC_SYSOP;
	print ("<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='savesettings_authority'>");
	tr($lang_settings['row_default_class'], $lang_settings['text_default_user_class'].classlist('defaultclass',UC_STAFFLEADER,$AUTHORITY['defaultclass']).$lang_settings['text_default'].get_user_class_name(UC_USER,false,true,true).$lang_settings['text_default_class_note'], 1);
	tr($lang_settings['row_staff_member'], $lang_settings['text_minimum_class'].classlist('staffmem',UC_STAFFLEADER,$AUTHORITY['staffmem']).$lang_settings['text_default'].get_user_class_name(UC_MODERATOR,false,true,true).$lang_settings['text_staff_member_note'], 1);
	tr($lang_settings['row_news_management'], $lang_settings['text_minimum_class'].classlist('newsmanage',$maxclass,$AUTHORITY['newsmanage']).$lang_settings['text_default'].get_user_class_name(UC_ADMINISTRATOR,false,true,true).$lang_settings['text_news_management_note'],1);
	tr($lang_settings['row_post_funbox_item'], $lang_settings['text_minimum_class'].classlist('newfunitem',$maxclass,$AUTHORITY['newfunitem']).$lang_settings['text_default'].get_user_class_name(UC_USER,false,true,true).$lang_settings['text_post_funbox_item_note'],1);
	tr($lang_settings['row_funbox_management'], $lang_settings['text_minimum_class']. classlist('funmanage',$maxclass,$AUTHORITY['funmanage']).$lang_settings['text_default'].get_user_class_name(UC_MODERATOR,false,true,true).$lang_settings['text_funbox_management_note'],1);
	tr($lang_settings['row_shoutbox_management'], $lang_settings['text_minimum_class']. classlist('sbmanage',$maxclass,$AUTHORITY['sbmanage']).$lang_settings['text_default'].get_user_class_name(UC_MODERATOR,false,true,true).$lang_settings['text_shoutbox_management_note'],1);
	tr($lang_settings['row_poll_management'], $lang_settings['text_minimum_class'].classlist('pollmanage',$maxclass,$AUTHORITY['pollmanage']).$lang_settings['text_default'].get_user_class_name(UC_ADMINISTRATOR,false,true,true).$lang_settings['text_poll_management_note'],1);
	tr($lang_settings['row_apply_for_links'], $lang_settings['text_minimum_class'].classlist('applylink',$maxclass,$AUTHORITY['applylink']).$lang_settings['text_default'].get_user_class_name(UC_USER,false,true,true).$lang_settings['text_apply_for_links_note'],1);
	tr($lang_settings['row_link_management'], $lang_settings['text_minimum_class'].classlist('linkmanage',$maxclass,$AUTHORITY['linkmanage']).$lang_settings['text_default'].get_user_class_name(UC_ADMINISTRATOR,false,true,true).$lang_settings['text_link_management_note'],1);
	tr($lang_settings['row_forum_post_management'], $lang_settings['text_minimum_class'].classlist('postmanage',$maxclass,$AUTHORITY['postmanage']).$lang_settings['text_default'].get_user_class_name(UC_MODERATOR,false,true,true).$lang_settings['text_forum_post_management_note'],1);
	tr($lang_settings['row_comment_management'], $lang_settings['text_minimum_class'].classlist('commanage',$maxclass,$AUTHORITY['commanage']).$lang_settings['text_default'].get_user_class_name(UC_MODERATOR,false,true,true).$lang_settings['text_comment_management_note'],1);
	tr($lang_settings['row_forum_management'], $lang_settings['text_minimum_class'].classlist('forummanage',$maxclass,$AUTHORITY['forummanage']).$lang_settings['text_default'].get_user_class_name(UC_ADMINISTRATOR,false,true,true).$lang_settings['text_forum_management_note'],1);
	tr($lang_settings['row_view_userlist'], $lang_settings['text_minimum_class'].classlist('viewuserlist',$maxclass,$AUTHORITY['viewuserlist']).$lang_settings['text_default'].get_user_class_name(UC_POWER_USER,false,true,true).$lang_settings['text_view_userlist_note'],1);
	tr($lang_settings['row_torrent_management'], $lang_settings['text_minimum_class'].classlist('torrentmanage',$maxclass,$AUTHORITY['torrentmanage']).$lang_settings['text_default'].get_user_class_name(UC_MODERATOR,false,true,true).$lang_settings['text_torrent_management_note'], 1);
	tr($lang_settings['row_torrent_sticky'], $lang_settings['text_minimum_class'].classlist('torrentsticky',$maxclass,$AUTHORITY['torrentsticky']).$lang_settings['text_default'].get_user_class_name(UC_ADMINISTRATOR,false,true,true).$lang_settings['text_torrent_sticky_note'],1);
	tr($lang_settings['row_torrent_on_promotion'], $lang_settings['text_minimum_class'].classlist('torrentonpromotion',$maxclass,$AUTHORITY['torrentonpromotion'] ?? '').$lang_settings['text_default'].get_user_class_name(UC_ADMINISTRATOR,false,true,true).$lang_settings['text_torrent_promotion_note'],1);
	tr($lang_settings['row_torrent_hr'], $lang_settings['text_minimum_class'].classlist('torrent_hr',$maxclass,$AUTHORITY['torrent_hr'] ?? '').$lang_settings['text_default'].get_user_class_name(UC_ADMINISTRATOR,false,true,true).$lang_settings['text_torrent_hr_note'],1);
	tr($lang_settings['row_ask_for_reseed'],  $lang_settings['text_minimum_class'].classlist('askreseed',$maxclass,$AUTHORITY['askreseed']).$lang_settings['text_default'].get_user_class_name(UC_POWER_USER,false,true,true).$lang_settings['text_ask_for_reseed_note'],1);
	tr($lang_settings['row_view_nfo'], $lang_settings['text_minimum_class'].classlist('viewnfo',$maxclass,$AUTHORITY['viewnfo']).$lang_settings['text_default'].get_user_class_name(UC_POWER_USER,false,true,true).$lang_settings['text_view_nfo_note'],1);
	tr($lang_settings['row_view_torrent_structure'], $lang_settings['text_minimum_class'].classlist('torrentstructure',$maxclass,$AUTHORITY['torrentstructure']).$lang_settings['text_default'].get_user_class_name(UC_ULTIMATE_USER,false,true,true).$lang_settings['text_view_torrent_structure_note'],1);
	tr($lang_settings['row_send_invite'], $lang_settings['text_minimum_class'].classlist('sendinvite',$maxclass,$AUTHORITY['sendinvite']).$lang_settings['text_default'].get_user_class_name(UC_POWER_USER,false,true,true).$lang_settings['text_send_invite_note'],1);
	tr($lang_settings['row_view_history'], $lang_settings['text_minimum_class'].classlist('viewhistory',$maxclass,$AUTHORITY['viewhistory']).$lang_settings['text_default'].get_user_class_name(UC_VETERAN_USER,false,true,true).$lang_settings['text_view_history_note'],1);
	tr($lang_settings['row_view_topten'], $lang_settings['text_minimum_class'].classlist('topten',$maxclass,$AUTHORITY['topten']).$lang_settings['text_default'].get_user_class_name(UC_POWER_USER,false,true,true).$lang_settings['text_view_topten_note'],1);
	tr($lang_settings['row_view_general_log'], $lang_settings['text_minimum_class'].classlist('log',$maxclass,$AUTHORITY['log']).$lang_settings['text_default'].get_user_class_name(UC_INSANE_USER,false,true,true).$lang_settings['text_view_general_log_note'],1);
	tr($lang_settings['row_view_confidential_log'], $lang_settings['text_minimum_class'].classlist('confilog',$maxclass,$AUTHORITY['confilog']).$lang_settings['text_default'].get_user_class_name(UC_MODERATOR,false,true,true).$lang_settings['text_view_confidential_log_note'],1);
	tr($lang_settings['row_view_user_confidential'], $lang_settings['text_minimum_class'].classlist('userprofile',$maxclass,$AUTHORITY['userprofile']).$lang_settings['text_default'].get_user_class_name(UC_ADMINISTRATOR,false,true,true).$lang_settings['text_view_user_confidential_note'],1);
	tr($lang_settings['row_view_user_torrent'], $lang_settings['text_minimum_class'].classlist('torrenthistory',$maxclass,$AUTHORITY['torrenthistory']).$lang_settings['text_default'].get_user_class_name(UC_POWER_USER,false,true,true).$lang_settings['text_view_user_torrent_note'],1);
	tr($lang_settings['row_general_profile_management'],  $lang_settings['text_minimum_class'].classlist('prfmanage',$maxclass,$AUTHORITY['prfmanage']).$lang_settings['text_default'].get_user_class_name(UC_MODERATOR,false,true,true).$lang_settings['text_general_profile_management_note'],1);
	tr($lang_settings['row_crucial_profile_management'], $lang_settings['text_minimum_class'].classlist('cruprfmanage',$maxclass,$AUTHORITY['cruprfmanage']).$lang_settings['text_default'].get_user_class_name(UC_ADMINISTRATOR,false,true,true).$lang_settings['text_crucial_profile_management_note'].get_user_class_name(UC_STAFFLEADER,false,true,true).$lang_settings['text_can_manage_donation'],1);
	tr($lang_settings['row_upload_subtitle'], $lang_settings['text_minimum_class'].classlist('uploadsub',$maxclass,$AUTHORITY['uploadsub']).$lang_settings['text_default'].get_user_class_name(UC_USER,false,true,true).$lang_settings['text_upload_subtitle_note'],1);
	tr($lang_settings['row_delete_own_subtitle'], $lang_settings['text_minimum_class'].classlist('delownsub',$maxclass,$AUTHORITY['delownsub']).$lang_settings['text_default'].get_user_class_name(UC_POWER_USER,false,true,true).$lang_settings['text_delete_own_subtitle_note'],1);
	tr($lang_settings['row_subtitle_management'], $lang_settings['text_minimum_class'].classlist('submanage',$maxclass,$AUTHORITY['submanage']).$lang_settings['text_default'].get_user_class_name(UC_MODERATOR,false,true,true).$lang_settings['text_subtitle_management'],1);
	tr($lang_settings['row_update_external_info'], $lang_settings['text_minimum_class'].classlist('updateextinfo',$maxclass,$AUTHORITY['updateextinfo']).$lang_settings['text_default'].get_user_class_name(UC_EXTREME_USER,false,true,true).$lang_settings['text_update_external_info_note'],1);
	tr($lang_settings['row_view_anonymous'], $lang_settings['text_minimum_class'].classlist('viewanonymous',$maxclass,$AUTHORITY['viewanonymous']).$lang_settings['text_default'].get_user_class_name(UC_UPLOADER,false,true,true).$lang_settings['text_view_anonymous_note'],1);
	tr($lang_settings['row_be_anonymous'], $lang_settings['text_minimum_class'].classlist('beanonymous',$maxclass,$AUTHORITY['beanonymous']).$lang_settings['text_default'].get_user_class_name(UC_CRAZY_USER,false,true,true).$lang_settings['text_be_anonymous_note'],1);
	tr($lang_settings['row_add_offer'], $lang_settings['text_minimum_class'].classlist('addoffer',$maxclass,$AUTHORITY['addoffer']).$lang_settings['text_default'].get_user_class_name(UC_PEASANT,false,true,true).$lang_settings['text_add_offer_note'], 1);
	tr($lang_settings['row_offer_management'], $lang_settings['text_minimum_class'].classlist('offermanage',$maxclass,$AUTHORITY['offermanage']).$lang_settings['text_default'].get_user_class_name(UC_MODERATOR,false,true,true).$lang_settings['text_offer_management_note'],1);
	tr($lang_settings['row_upload_torrent'], $lang_settings['text_minimum_class'].classlist('upload',$maxclass,$AUTHORITY['upload']).$lang_settings['text_default'].get_user_class_name(UC_POWER_USER,false,true,true).$lang_settings['text_upload_torrent_note'], 1);
	tr($lang_settings['row_upload_special_torrent'], $lang_settings['text_minimum_class'].classlist('uploadspecial',$maxclass,$AUTHORITY['uploadspecial']).$lang_settings['text_default'].get_user_class_name(UC_UPLOADER,false,true,true).$lang_settings['text_upload_special_torrent_note'],1);
	tr($lang_settings['row_view_special_torrent'], $lang_settings['text_minimum_class'].classlist('view_special_torrent',$maxclass,$AUTHORITY['view_special_torrent']).$lang_settings['text_default'].get_user_class_name(UC_CRAZY_USER,false,true,true).$lang_settings['text_view_special_torrent_note'],1);
	tr($lang_settings['row_move_torrent'], $lang_settings['text_minimum_class'].classlist('movetorrent',$maxclass,$AUTHORITY['movetorrent']).$lang_settings['text_default'].get_user_class_name(UC_MODERATOR,false,true,true).$lang_settings['text_move_torrent_note'],1);
	tr($lang_settings['row_chronicle_management'], $lang_settings['text_minimum_class'].classlist('chrmanage',$maxclass,$AUTHORITY['chrmanage']).$lang_settings['text_default'].get_user_class_name(UC_MODERATOR,false,true,true).$lang_settings['text_chronicle_management_note'],1);
	tr($lang_settings['row_view_invite'], $lang_settings['text_minimum_class'].classlist('viewinvite',$maxclass,$AUTHORITY['viewinvite']).$lang_settings['text_default'].get_user_class_name(UC_MODERATOR,false,true,true).$lang_settings['text_view_invite_note'],1);
	tr($lang_settings['row_buy_invites'], $lang_settings['text_minimum_class'].classlist('buyinvite',$maxclass,$AUTHORITY['buyinvite']).$lang_settings['text_default'].get_user_class_name(UC_INSANE_USER,false,true,true).$lang_settings['text_buy_invites_note'],1);
	tr($lang_settings['row_see_banned_torrents'], $lang_settings['text_minimum_class'].classlist('seebanned',$maxclass,$AUTHORITY['seebanned']).$lang_settings['text_default'].get_user_class_name(UC_UPLOADER,false,true,true).$lang_settings['text_see_banned_torrents_note'],1);
	tr($lang_settings['row_vote_against_offers'], $lang_settings['text_minimum_class'].classlist('againstoffer',$maxclass,$AUTHORITY['againstoffer']).$lang_settings['text_default'].get_user_class_name(UC_USER,false,true,true).$lang_settings['text_vote_against_offers_note'],1);
	tr($lang_settings['row_allow_userbar'], $lang_settings['text_minimum_class'].classlist('userbar',$maxclass,$AUTHORITY['userbar']).$lang_settings['text_default'].get_user_class_name(UC_POWER_USER,false,true,true).$lang_settings['text_allow_userbar_note'],1);
	tr($lang_settings['row_save_settings'],"<input type='submit' name='save' value='".$lang_settings['submit_save_settings']."'>", 1);
	print ("</form>");
}
elseif ($action == 'basicsettings')	// basic settings
{
	stdhead($lang_settings['head_basic_settings']);
	print ($notice);
	$config = get_setting_from_db('basic');
	print ("<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='savesettings_basic'>");
	tr($lang_settings['row_site_name'],"<input type='text' style=\"width: 300px\" name=SITENAME value='".($config["SITENAME"] ? $config["SITENAME"]: "Nexus")."'> ".$lang_settings['text_site_name_note'], 1);
	tr($lang_settings['row_base_url'],"<input type='text' style=\"width: 300px\" name=BASEURL value='".($config["BASEURL"] ? $config["BASEURL"] : $_SERVER["HTTP_HOST"])."'> ".$lang_settings['text_it_should_be'] . $_SERVER["HTTP_HOST"] . $lang_settings['text_base_url_note'], 1);
	tr($lang_settings['row_announce_url'],"<input type='text' style=\"width: 300px\" name=announce_url value='".($config["announce_url"] ? $config["announce_url"] : $_SERVER["HTTP_HOST"].DEFAULT_TRACKER_URI)."'> ".$lang_settings['text_it_should_be'] . $_SERVER["HTTP_HOST"].DEFAULT_TRACKER_URI, 1);
//	tr($lang_settings['row_mysql_host'],"<input type='text' style=\"width: 300px\" name=mysql_host value='".($config["mysql_host"] ? $config["mysql_host"] : "localhost")."'> ".$lang_settings['text_mysql_host_note'], 1);
//	tr($lang_settings['row_mysql_user'],"<input type='text' style=\"width: 300px\" name=mysql_user value='".($config["mysql_user"] ? $config["mysql_user"] : "root")."'> ".$lang_settings['text_mysql_user_note'], 1);
//	tr($lang_settings['row_mysql_password'],"<input type='password' style=\"width: 300px\" name=mysql_pass value=''> ".$lang_settings['text_mysql_password_note'], 1);
//	tr($lang_settings['row_mysql_database_name'],"<input type='text' style=\"width: 300px\" name=mysql_db value='".($config["mysql_db"] ? $config["mysql_db"] : "nexus")."'> ".$lang_settings['text_mysql_database_name_note'], 1);
//	tr($lang_settings['row_mysql_database_port'],"<input type='text' style=\"width: 300px\" name=mysql_port value='".($config["mysql_port"] ? $config["mysql_port"] : "3306")."'> ".$lang_settings['text_mysql_database_port_note'], 1);
//	tr($lang_settings['row_redis_host'],"<input type='text' style=\"width: 300px\" name=redis_host value='".($config["redis_host"] ? $config["redis_host"] : "127.0.0.1")."'> ".$lang_settings['text_row_redis_host_note'], 1);
//	tr($lang_settings['row_redis_port'],"<input type='text' style=\"width: 300px\" name=redis_port value='".($config["redis_port"] ? $config["redis_port"] : "6379")."'> ".$lang_settings['text_row_redis_port_note'], 1);
//	tr($lang_settings['row_redis_database'],"<input type='text' style=\"width: 300px\" name=redis_database value='".($config["redis_database"] ? $config["redis_database"] : "0")."'> ".$lang_settings['text_row_redis_database'], 1);

	tr($lang_settings['row_save_settings'],"<input type='submit' name='save' value='".$lang_settings['submit_save_settings']."'>", 1);
	print ("</form>");
}
elseif ($action == 'attachmentsettings')	// basic settings
{
	$ATTACHMENT = get_setting_from_db('attachment');
	stdhead($lang_settings['head_attachment_settings']);
	print ($notice);
	print ("<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='savesettings_attachment'>");
	yesorno($lang_settings['row_enable_attachment'], 'enableattach', $ATTACHMENT["enableattach"], $lang_settings['text_enable_attachment_note']);
	tr($lang_settings['row_attachment_authority'], $lang_settings['text_attachment_authority_note_one']."<ul><li>".classlist('classone', UC_STAFFLEADER, $ATTACHMENT['classone']) . $lang_settings['text_can_upload_at_most'] . "<input type='text' style=\"width: 50px\" name=\"countone\" value='".($ATTACHMENT['countone'] ? $ATTACHMENT['countone']: '')."'> ".$lang_settings['text_file_size_below']."<input type='text' style=\"width: 50px\" name=\"sizeone\" value='".($ATTACHMENT['sizeone'] ? $ATTACHMENT['sizeone']: '')."'>".$lang_settings['text_with_extension_name']."<input type='text' style=\"width: 200px\" name=\"extone\" value='".($ATTACHMENT['extone'] ? $ATTACHMENT['extone']: '')."'>".$lang_settings['text_authority_default_one_one'].get_user_class_name(UC_USER,false,true,true).$lang_settings['text_authority_default_one_two']."</li><li>".classlist('classtwo', UC_STAFFLEADER, $ATTACHMENT['classtwo']) . $lang_settings['text_can_upload_at_most'] . "<input type='text' style=\"width: 50px\" name=\"counttwo\" value='".($ATTACHMENT['counttwo'] ? $ATTACHMENT['counttwo']: '')."'> ".$lang_settings['text_file_size_below']."<input type='text' style=\"width: 50px\" name=\"sizetwo\" value='".($ATTACHMENT['sizetwo'] ? $ATTACHMENT['sizetwo']: '')."'>".$lang_settings['text_with_extension_name']."<input type='text' style=\"width: 200px\" name=\"exttwo\" value='".($ATTACHMENT['exttwo'] ? $ATTACHMENT['exttwo']: '')."'>".$lang_settings['text_authority_default_two_one'].get_user_class_name(UC_POWER_USER,false,true,true).$lang_settings['text_authority_default_two_two']."</li><li>".classlist('classthree', UC_STAFFLEADER, $ATTACHMENT['classthree']) . $lang_settings['text_can_upload_at_most'] . "<input type='text' style=\"width: 50px\" name=\"countthree\" value='".($ATTACHMENT['countthree'] ? $ATTACHMENT['countthree']: '')."'> ".$lang_settings['text_file_size_below']."<input type='text' style=\"width: 50px\" name=\"sizethree\" value='".($ATTACHMENT['sizethree'] ? $ATTACHMENT['sizethree']: '')."'>".$lang_settings['text_with_extension_name']."<input type='text' style=\"width: 200px\" name=\"extthree\" value='".($ATTACHMENT['extthree'] ? $ATTACHMENT['extthree']: '')."'>".$lang_settings['text_authority_default_three_one'].get_user_class_name(UC_INSANE_USER,false,true,true).$lang_settings['text_authority_default_three_two']."</li><li>".classlist('classfour', UC_STAFFLEADER, $ATTACHMENT['classfour']) . $lang_settings['text_can_upload_at_most'] . "<input type='text' style=\"width: 50px\" name=\"countfour\" value='".($ATTACHMENT['countfour'] ? $ATTACHMENT['countfour']: '')."'> ".$lang_settings['text_file_size_below']."<input type='text' style=\"width: 50px\" name=\"sizefour\" value='".($ATTACHMENT['sizefour'] ? $ATTACHMENT['sizefour']: '')."'>".$lang_settings['text_with_extension_name']."<input type='text' style=\"width: 200px\" name=\"extfour\" value='".($ATTACHMENT['extfour'] ? $ATTACHMENT['extfour']: '')."'>".$lang_settings['text_authority_default_four_one'].get_user_class_name(UC_MODERATOR,false,true,true).$lang_settings['text_authority_default_four_two']."</li></ul>".$lang_settings['text_attachment_authority_note_two'], 1);
	tr($lang_settings['row_save_directory'],"<input type='text' style=\"width: 300px\" name=\"savedirectory\" value='".($ATTACHMENT['savedirectory'] ? $ATTACHMENT['savedirectory']: "./attachments")."'> ".$lang_settings['text_save_directory_note'], 1);
	tr($lang_settings['row_http_directory'],"<input type='text' style=\"width: 300px\" name=\"httpdirectory\" value='".($ATTACHMENT['httpdirectory'] ? $ATTACHMENT['httpdirectory']: "attachments")."'> ".$lang_settings['text_http_directory_note'], 1);
	tr($lang_settings['row_save_directory_type'],"<input type='radio' name='savedirectorytype' value='onedir'".($ATTACHMENT['savedirectorytype'] == "onedir" ? " checked=\"checked\"" : "").">".$lang_settings['text_one_directory']."<br /><input type='radio' name='savedirectorytype' value='monthdir'".($ATTACHMENT['savedirectorytype'] == "monthdir" ? " checked=\"checked\"" : "").">". $lang_settings['text_directories_by_monthes'] . "<br /><input type='radio' name='savedirectorytype' value='daydir'".($ATTACHMENT['savedirectorytype'] == "daydir" ? " checked=\"checked\"" : "").">".$lang_settings['text_directories_by_days'] . "<br />" . $lang_settings['text_save_directory_type_note'], 1);
	tr($lang_settings['row_image_thumbnails'],"<input type='radio' name='thumbnailtype' value='no' ".($ATTACHMENT["thumbnailtype"] == 'no' ? " checked=\"checked\"" : "")."> ".$lang_settings['text_no_thumbnail']."<br><input type='radio' name='thumbnailtype' value='createthumb' ".($ATTACHMENT["thumbnailtype"] == 'createthumb' ? " checked=\"checked\"" : "")."> ".$lang_settings['text_create_thumbnail']."<br><input type='radio' name='thumbnailtype' value='resizebigimg' ".($ATTACHMENT["thumbnailtype"] == 'resizebigimg' ? " checked=\"checked\"" : "")."> ". $lang_settings['text_resize_big_image']."<br>" . $lang_settings['text_image_thumbnail_note'], 1);
	tr($lang_settings['row_thumbnail_quality'],"<input type='text' style=\"width: 100px\" name=\"thumbquality\" value='".($ATTACHMENT['thumbquality'] ? $ATTACHMENT['thumbquality']: '80')."'> ".$lang_settings['text_thumbnail_quality_note'], 1);
	tr($lang_settings['row_thumbnail_size'],"<input type='text' style=\"width: 100px\" name=\"thumbwidth\" value='".($ATTACHMENT['thumbwidth'] ? $ATTACHMENT['thumbwidth']: '500')."'> * <input type='text' style=\"width: 100px\" name=\"thumbheight\" value='".($ATTACHMENT['thumbheight'] ? $ATTACHMENT['thumbheight']: '500')."'> ".$lang_settings['text_thumbnail_size_note'], 1);
	tr($lang_settings['row_alternative_thumbnail_size'],"<input type='text' style=\"width: 100px\" name=\"altthumbwidth\" value='".($ATTACHMENT['altthumbwidth'] ? $ATTACHMENT['altthumbwidth']: '180')."'> * <input type='text' style=\"width: 100px\" name=\"altthumbheight\" value='".($ATTACHMENT['altthumbheight'] ? $ATTACHMENT['altthumbheight']: '135')."'> ".$lang_settings['text_alternative_thumbnail_size_note'], 1);
	tr($lang_settings['row_watermark'], "<input type='radio' name='watermarkpos' value='no' ".($ATTACHMENT["watermarkpos"] == 'no' ? " checked=\"checked\"" : "")."> ".$lang_settings['text_no_watermark']."<br><input type='radio' name='watermarkpos' value='1' ".($ATTACHMENT["watermarkpos"] == '1' ? " checked=\"checked\"" : "")."> ".$lang_settings['text_left_top']."<input type='radio' name='watermarkpos' value='2' ".($ATTACHMENT["watermarkpos"] == '2' ? " checked=\"checked\"" : "")."> ".$lang_settings['text_top']."<input type='radio' name='watermarkpos' value='3' ".($ATTACHMENT["watermarkpos"] == '3' ? " checked=\"checked\"" : "")."> ".$lang_settings['text_right_top']."<br><input type='radio' name='watermarkpos' value='4' ".($ATTACHMENT["watermarkpos"] == '4' ? " checked=\"checked\"" : "")."> ".$lang_settings['text_left']."<input type='radio' name='watermarkpos' value='5' ".($ATTACHMENT["watermarkpos"] == '5' ? " checked=\"checked\"" : "")."> ".$lang_settings['text_center']."<input type='radio' name='watermarkpos' value='6' ".($ATTACHMENT["watermarkpos"] == '6' ? " checked=\"checked\"" : "")."> ".$lang_settings['text_right']."<br><input type='radio' name='watermarkpos' value='7' ".($ATTACHMENT["watermarkpos"] == '7' ? " checked=\"checked\"" : "")."> ".$lang_settings['text_left_bottom']."<input type='radio' name='watermarkpos' value='8' ".($ATTACHMENT["watermarkpos"] == '8' ? " checked=\"checked\"" : "")."> ".$lang_settings['text_bottom']."<input type='radio' name='watermarkpos' value='9' ".($ATTACHMENT["watermarkpos"] == '9' ? " checked=\"checked\"" : "")."> ".$lang_settings['text_right_bottom']."<br><input type='radio' name='watermarkpos' value='random' ".($ATTACHMENT["watermarkpos"] == 'random' ? " checked=\"checked\"" : "")."> ".$lang_settings['text_random_position']."<br>".$lang_settings['text_watermark_note'], 1);
	tr($lang_settings['row_image_size_for_watermark'],"<input type='text' style=\"width: 100px\" name=\"watermarkwidth\" value='".($ATTACHMENT['watermarkwidth'] ? $ATTACHMENT['watermarkwidth']: '300')."'> * <input type='text' style=\"width: 100px\" name=\"watermarkheight\" value='".($ATTACHMENT['watermarkheight'] ? $ATTACHMENT['watermarkheight']: '300')."'> ".$lang_settings['text_watermark_size_note'], 1);
	//yesorno($lang_settings['row_add_watermark_to_thumbnail'], 'wmthumb', $ATTACHMENT["wmthumb"], $lang_settings['text_watermark_to_thumbnail_note']);
	tr($lang_settings['row_jpeg_quality_with_watermark'],"<input type='text' style=\"width: 100px\" name=\"watermarkquality\" value='".($ATTACHMENT['watermarkquality'] ? $ATTACHMENT['watermarkquality']: '85')."'> ".$lang_settings['text_jpeg_watermark_quality_note'], 1);
	tr($lang_settings['row_save_settings'],"<input type='submit' name='save' value='".$lang_settings['submit_save_settings']."'>", 1);
	print ("</form>");
}
elseif ($action == 'advertisementsettings')
{
	$ADVERTISEMENT = get_setting_from_db('advertisement');
	stdhead($lang_settings['head_advertisement_settings']);
	print ($notice);
	print ("<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='savesettings_advertisement'>");
	yesorno($lang_settings['row_enable_advertisement'], 'enablead', $ADVERTISEMENT['enablead'], $lang_settings['text_enable_advertisement_note']);
	tr($lang_settings['row_no_advertisement'], "<input type='checkbox' name='enablenoad' value='yes'".($ADVERTISEMENT['enablenoad'] == 'yes' ? " checked='checked'" : "")." />".classlist('noad', UC_STAFFLEADER, $ADVERTISEMENT['noad']).$lang_settings['text_can_choose_no_advertisement'].get_user_class_name(UC_UPLOADER,false,true,true), 1);
	tr($lang_settings['row_bonus_no_advertisement'], "<input type='checkbox' name='enablebonusnoad' value='yes'".($ADVERTISEMENT['enablebonusnoad'] == 'yes' ? " checked='checked'" : "")." />".classlist('bonusnoad', UC_STAFFLEADER, $ADVERTISEMENT['bonusnoad']).$lang_settings['text_no_advertisement_with_bonus'].get_user_class_name(UC_POWER_USER,false,true,true), 1);
	tr($lang_settings['row_no_advertisement_bonus_price'], $lang_settings['text_it_costs_user']."<input type='text' style=\"width: 50px\" name='bonusnoadpoint' value='".(isset($ADVERTISEMENT["bonusnoadpoint"]) ? $ADVERTISEMENT["bonusnoadpoint"] : 10000 )."'>".$lang_settings['text_bonus_points_to_buy']."<input type='text' style=\"width: 50px\" name='bonusnoadtime' value='".(isset($ADVERTISEMENT["bonusnoadtime"]) ? $ADVERTISEMENT["bonusnoadtime"] : 15 )."'>".$lang_settings['text_days_without_advertisements'], 1);
	tr($lang_settings['row_click_advertisement_bonus'], $lang_settings['text_user_would_get']."<input type='text' style=\"width: 50px\" name='adclickbonus' value='".(isset($ADVERTISEMENT["adclickbonus"]) ? $ADVERTISEMENT["adclickbonus"] : 0 )."'>".$lang_settings['text_points_clicking_on_advertisements'], 1);
	tr($lang_settings['row_save_settings'],"<input type='submit' name='save' value='".$lang_settings['submit_save_settings']."'>", 1);
	print ("</form>");
}
elseif ($action == 'codesettings')	// code settings
{
	$CODE = get_setting_from_db('code');
	stdhead($lang_settings['head_code_settings']);
	print ($notice);
	print ("<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='savesettings_code'>");
	tr($lang_settings['row_main_version'],"<input type='text' style=\"width: 300px\" name=mainversion value='".($CODE["mainversion"] ? $CODE["mainversion"] : PROJECTNAME." PHP")."'> ".$lang_settings['text_main_version_note'], 1);
	tr($lang_settings['row_sub_version'],"<input type='text' style=\"width: 300px\" name=subversion value='".($CODE["subversion"] ? $CODE["subversion"] : "1.0")."'> ".$lang_settings['text_sub_version_note'], 1);
	tr($lang_settings['row_release_date'],"<input type='text' style=\"width: 300px\" name=releasedate value='".($CODE["releasedate"] ? $CODE["releasedate"] : "2008-12-10")."'> ".$lang_settings['text_release_date_note'], 1);
	tr($lang_settings['row_web_site'],"<input type='text' style=\"width: 300px\" name=website value='".($CODE["website"] ? $CODE["website"] : "")."'> ".($lang_settings['text_web_site_note_one'] ?? '').PROJECTNAME.$lang_settings['text_web_site_note_two'], 1);
	tr($lang_settings['row_save_settings'],"<input type='submit' name='save' value='".$lang_settings['submit_save_settings']."'>", 1);
	print ("</form>");
}
elseif ($action == 'bonussettings'){
	$BONUS = get_setting_from_db('bonus');
	stdhead($lang_settings['head_bonus_settings']);
	print ($notice);
	print ("<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='savesettings_bonus'>");
	print("<tr><td colspan=2 align=center><b>".$lang_settings['text_bonus_by_seeding']."</b></td></tr>");
	tr($lang_settings['row_donor_gets_double'], $lang_settings['text_donor_gets']."<input type='text' style=\"width: 50px\" name=donortimes value='".(isset($BONUS["donortimes"]) ? $BONUS["donortimes"] : 2 )."'>".$lang_settings['text_times_as_many'],1);
	tr($lang_settings['row_basic_seeding_bonus'], $lang_settings['text_user_would_get']."<input type='text' style=\"width: 50px\" name=perseeding value='".(isset($BONUS["perseeding"]) ? $BONUS["perseeding"] : 1 )."'>".$lang_settings['text_bonus_points']."<input type='text' style=\"width: 50px\" name=maxseeding value='".(isset($BONUS["maxseeding"]) ? $BONUS["maxseeding"] : 7 )."'>".$lang_settings['text_torrents_default'], 1);
	tr($lang_settings['row_seeding_formula'], $lang_settings['text_bonus_formula_one']."<br />&nbsp;&nbsp;&nbsp;&nbsp;<img src=pic/bonusformulaa.png alt=\"A = sigma( ( 1 - 10 ^ ( - Ti / T0 ) ) * Si * ( 1 + sqrt( 2 ) * 10 ^ ( - ( Ni - 1 ) / ( N0 - 1 ) ) )\" title=\"A = sigma( ( 1 - 10 ^ ( - Ti / T0 ) ) * Si * ( 1 + sqrt( 2 ) * 10 ^ ( - ( Ni - 1 ) / ( N0 - 1 ) ) )\"><br />&nbsp;&nbsp;&nbsp;&nbsp;<img src=pic/bonusformulab.png alt=\"B = B0 * 2 / pi * arctan( A / L )\" title=\"B = B0 * 2 / pi * arctan( A / L )\"><br />".$lang_settings['text_where']."<ul><li>".$lang_settings['text_bonus_formula_two']."</li><li>".$lang_settings['text_bonus_formula_three']."<input type='text' style=\"width: 50px\" name=tzero value='".(isset($BONUS["tzero"]) ? $BONUS["tzero"] : 4 )."'>".$lang_settings['text_bonus_formula_four']."</li><li>".$lang_settings['text_bonus_formula_five']."</li><li>".$lang_settings['text_bonus_formula_six']."<input type='text' style=\"width: 50px\" name=nzero value='".(isset($BONUS["nzero"]) ? $BONUS["nzero"] : 7 )."'>".$lang_settings['text_bonus_formula_seven']."</li><li>".$lang_settings['text_bonus_formula_eight']."</li><li>".$lang_settings['text_bonus_formula_nine']."<input type='text' style=\"width: 50px\" name=bzero value='".(isset($BONUS["bzero"]) ? $BONUS["bzero"] : 100 )."'>".$lang_settings['text_bonus_formula_ten']."</li><li>".$lang_settings['text_bonus_formula_eleven']."<input type='text' style=\"width: 50px\" name=l value='".(isset($BONUS["l"]) ? $BONUS["l"] : 300 )."'>".$lang_settings['text_bonus_formula_twelve']."</li></ul>\n", 1);
	print("<tr><td colspan=2 align=center><b>".$lang_settings['text_misc_ways_get_bonus']."</b></td></tr>");
	tr($lang_settings['row_uploading_torrent'],$lang_settings['text_user_would_get']."<input type='text' style=\"width: 50px\" name=uploadtorrent value='".(isset($BONUS["uploadtorrent"]) ? $BONUS["uploadtorrent"] : 15 )."'>".$lang_settings['text_uploading_torrent_note'], 1);
	tr($lang_settings['row_uploading_subtitle'],$lang_settings['text_user_would_get']."<input type='text' style=\"width: 50px\" name=uploadsubtitle value='".(isset($BONUS["uploadsubtitle"]) ? $BONUS["uploadsubtitle"] : 5 )."'>".$lang_settings['text_uploading_subtitle_note'], 1);
	tr($lang_settings['row_starting_topic'],$lang_settings['text_user_would_get']."<input type='text' style=\"width: 50px\" name=starttopic value='".(isset($BONUS["starttopic"]) ? $BONUS["starttopic"] : 2 )."'>".$lang_settings['text_starting_topic_note'], 1);
	tr($lang_settings['row_making_post'],$lang_settings['text_user_would_get']."<input type='text' style=\"width: 50px\" name=makepost value='".(isset($BONUS["makepost"]) ? $BONUS["makepost"] : 1 )."'>".$lang_settings['text_making_post_note'], 1);
	tr($lang_settings['row_adding_comment'],$lang_settings['text_user_would_get']."<input type='text' style=\"width: 50px\" name=addcomment value='".(isset($BONUS["addcomment"]) ? $BONUS["addcomment"] : 1 )."'>".$lang_settings['text_adding_comment_note'], 1);
	tr($lang_settings['row_voting_on_poll'],$lang_settings['text_user_would_get']."<input type='text' style=\"width: 50px\" name=pollvote value='".(isset($BONUS["pollvote"]) ? $BONUS["pollvote"] : 1 )."'>".$lang_settings['text_voting_on_poll_note'], 1);
	tr($lang_settings['row_voting_on_offer'],$lang_settings['text_user_would_get']."<input type='text' style=\"width: 50px\" name=offervote value='".(isset($BONUS["offervote"]) ? $BONUS["offervote"] : 1 )."'>".$lang_settings['text_voting_on_offer_note'], 1);
	tr($lang_settings['row_voting_on_funbox'],$lang_settings['text_user_would_get']."<input type='text' style=\"width: 50px\" name=funboxvote value='".(isset($BONUS["funboxvote"]) ? $BONUS["funboxvote"] : 1 )."'>".$lang_settings['text_voting_on_funbox_note'], 1);
	tr($lang_settings['row_saying_thanks'], $lang_settings['text_giver_and_receiver_get']."<input type='text' style=\"width: 50px\" name=saythanks value='".(isset($BONUS["saythanks"]) ? $BONUS["saythanks"] : 0.5 )."'>".$lang_settings['text_saying_thanks_and']."<input type='text' style=\"width: 50px\" name=receivethanks value='".(isset($BONUS["receivethanks"]) ? $BONUS["receivethanks"] : 0 )."'>".$lang_settings['text_saying_thanks_default'], 1);
	tr($lang_settings['row_funbox_stuff_reward'],$lang_settings['text_user_would_get']."<input type='text' style=\"width: 50px\" name=funboxreward value='".(isset($BONUS["funboxreward"]) ? $BONUS["funboxreward"] : 5 )."'>".$lang_settings['text_funbox_stuff_reward_note'], 1);
	tr($lang_settings['row_promotion_link_click'],$lang_settings['text_user_would_get']."<input type='text' style=\"width: 50px\" name=prolinkpoint value='".(isset($BONUS["prolinkpoint"]) ? $BONUS["prolinkpoint"] : 0 )."'>".$lang_settings['text_promotion_link_note_one']."<input type='text' style=\"width: 50px\" name=prolinktime value='".(isset($BONUS["prolinktime"]) ? $BONUS["prolinktime"] : 600 )."'>".$lang_settings['text_promotion_link_note_two'], 1);
	tr($lang_settings['row_harem_addition'],$lang_settings['text_user_would_get_by_harem']."<input type='text' style=\"width: 50px\" name=harem_addition value='".(isset($BONUS["harem_addition"]) ? $BONUS["harem_addition"] : 0 )."'>".$lang_settings['text_harem_addition_note'], 1);


	print("<tr><td colspan=2 align=center><b>".$lang_settings['text_things_cost_bonus']."</b></td></tr>");
	tr($lang_settings['row_one_gb_credit'],$lang_settings['text_it_costs_user']."<input type='text' style=\"width: 50px\" name=onegbupload value='".(isset($BONUS["onegbupload"]) ? $BONUS["onegbupload"] : 300 )."'>".$lang_settings['text_one_gb_credit_note'], 1);
	tr($lang_settings['row_five_gb_credit'],$lang_settings['text_it_costs_user']."<input type='text' style=\"width: 50px\" name=fivegbupload value='".(isset($BONUS["fivegbupload"]) ? $BONUS["fivegbupload"] : 800 )."'>".$lang_settings['text_five_gb_credit_note'], 1);
	tr($lang_settings['row_ten_gb_credit'],$lang_settings['text_it_costs_user']."<input type='text' style=\"width: 50px\" name=tengbupload value='".(isset($BONUS["tengbupload"]) ? $BONUS["tengbupload"] : 1200 )."'>".$lang_settings['text_ten_gb_credit_note'], 1);
	tr($lang_settings['row_ratio_limit'],$lang_settings['text_user_with_ratio']."<input type='text' style=\"width: 50px\" name=ratiolimit value='".(isset($BONUS["ratiolimit"]) ? $BONUS["ratiolimit"] : 6 )."'>".$lang_settings['text_uploaded_amount_above']."<input type='text' style=\"width: 50px\" name=dlamountlimit value='".(isset($BONUS["dlamountlimit"]) ? $BONUS["dlamountlimit"] : 50 )."'>".$lang_settings['text_ratio_limit_default'], 1);
	tr($lang_settings['row_buy_an_invite'],$lang_settings['text_it_costs_user']."<input type='text' style=\"width: 50px\" name=oneinvite value='".(isset($BONUS["oneinvite"]) ? $BONUS["oneinvite"] : 1000 )."'>".$lang_settings['text_buy_an_invite_note'], 1);
	tr($lang_settings['row_custom_title'],$lang_settings['text_it_costs_user']."<input type='text' style=\"width: 50px\" name=customtitle value='".(isset($BONUS["customtitle"]) ? $BONUS["customtitle"] : 5000 )."'>".$lang_settings['text_custom_title_note'], 1);
	tr($lang_settings['row_vip_status'],$lang_settings['text_it_costs_user']."<input type='text' style=\"width: 50px\" name=vipstatus value='".(isset($BONUS["vipstatus"]) ? $BONUS["vipstatus"] : 8000 )."'>".$lang_settings['text_vip_status_note'], 1);
	yesorno($lang_settings['row_allow_giving_bonus_gift'], 'bonusgift', $BONUS["bonusgift"], $lang_settings['text_giving_bonus_gift_note']);
	tr($lang_settings['row_bonus_gift_tax'], $lang_settings['text_system_charges']."<input type='text' style=\"width: 50px\" name='basictax' value='".(isset($BONUS["basictax"]) ? $BONUS["basictax"] : 5 )."'>".$lang_settings['text_bonus_points_plus']."<input type='text' style=\"width: 50px\" name='taxpercentage' value='".(isset($BONUS["taxpercentage"]) ? $BONUS["taxpercentage"] : 10 )."'>".$lang_settings['text_bonus_gift_tax_note'], 1);
    tr($lang_settings['row_cancel_hr'],$lang_settings['text_it_costs_user']."<input type='text' style=\"width: 50px\" name=cancel_hr value='".(isset($BONUS["cancel_hr"]) ? $BONUS["cancel_hr"] : \App\Models\BonusLogs::DEFAULT_BONUS_CANCEL_ONE_HIT_AND_RUN )."'>".$lang_settings['text_cancel_hr_note'], 1);
    tr($lang_settings['row_attendance_card'],$lang_settings['text_it_costs_user']."<input type='text' style=\"width: 50px\" name=attendance_card value='".(isset($BONUS["attendance_card"]) ? $BONUS["attendance_card"] : \App\Models\BonusLogs::DEFAULT_BONUS_BUY_ATTENDANCE_CARD )."'>".$lang_settings['text_attendance_card_note'], 1);


    echo '<tr><td colspan="2" align="center"><b>' . $lang_settings['text_attendance_get_bonus'] . '</b></td></tr>';
	tr($lang_settings['text_attendance_initial_reward'],sprintf($lang_settings['text_attendance_initial_reward_input_label'].' <input type="number" style="width: 30px" name="attendance_initial" value="%u" min="0" /> ' . $lang_settings['text_attendance_input_suffix'], $attendance_initial_bonus),true);
	tr($lang_settings['text_attendance_continuous_increment'],sprintf($lang_settings['text_attendance_continuous_increment_input_label'].' <input type="number" style="width: 30px" name="attendance_step" value="%u" min="0" /> ' . $lang_settings['text_attendance_input_suffix'], $attendance_step_bonus),true);
	tr($lang_settings['text_attendance_reward_limit'],sprintf($lang_settings['text_attendance_reward_limit_input_label'].' <input type="number" style="width: 50px" name="attendance_max" value="%u" min="0" /> ' . $lang_settings['text_attendance_input_suffix'], $attendance_max_bonus),true);
	$row = '<table><tr><td class="colhead">'.$lang_settings['text_attendance_continuous_days'].'</td><td class="colhead">'.$lang_settings['text_attendance_continuous_days_additional_reward'].'</td><td class="colhead">'.$lang_settings['text_attendance_continuous_days_action'].'</td></tr>'.PHP_EOL;
	if(is_array($attendance_continuous_bonus)){
		foreach($attendance_continuous_bonus as $days => $value){
			$row .= sprintf('<tr>
			<td><input type="number" min="0" style="width: 40px" name="attendance_continuous_day[]" value="%u" /> %s</td>
			<td><input type="number" min="0" style="width: 50px;" name="attendance_continuous_value[]" value="%u" /> %s</td>
			<td><a href="javascript:;" onclick="DelRow(this);">%s</a></td></tr>',
                $days, $lang_settings['text_attendance_continuous_unit'],$value, $lang_settings['text_attendance_input_suffix'], $lang_settings['text_attendance_continuous_item_action_remove']);
		}
	}
	$row .= '<tr><td colspan="3">'.$lang_settings['text_attendance_continuous_add_rules'].'</td></tr><tr>
	<td><input type="number" min="0" style="width: 40px" name="attendance_continuous_day[]" value="" /> '.$lang_settings['text_attendance_continuous_unit'].'</td>
	<td><input type="number" min="0" style="width: 50px;" name="attendance_continuous_value[]" value="" /> '.$lang_settings['text_attendance_input_suffix'].'</td>
	<td><a href="javascript:;" onclick="NewRow(this,false);">'.$lang_settings['text_attendance_continuous_item_action_add'].'</a></td></tr></table>';
	tr($lang_settings['text_attendance_continuous'],$row,true);
	tr($lang_settings['row_save_settings'], "<input type='submit' name='save' value='".$lang_settings['submit_save_settings']."'>", 1);
	print ("</form>");
}
elseif ($action == 'accountsettings'){
	$ACCOUNT = get_setting_from_db('account');
	stdhead($lang_settings['head_account_settings']);
	print ($notice);
	$maxclass = UC_VIP;
	print ("<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='savesettings_account'>");
	print("<tr><td colspan=2 align=center><b>".$lang_settings['text_delete_inactive_accounts']."</b></td></tr>");
	tr($lang_settings['row_never_delete'],classlist('neverdelete',$maxclass,$ACCOUNT['neverdelete']).$lang_settings['text_never_delete'].get_user_class_name(UC_VETERAN_USER,false,true,true), 1);
	tr($lang_settings['row_never_delete_if_packed'],classlist('neverdeletepacked',$maxclass,$ACCOUNT['neverdeletepacked']).$lang_settings['text_never_delete_if_packed'].get_user_class_name(UC_ELITE_USER,false,true,true), 1);
	tr($lang_settings['row_delete_packed'],$lang_settings['text_delete_packed_note_one']."<input type='text' style=\"width: 50px\" name=deletepacked value='".(isset($ACCOUNT["deletepacked"]) ? $ACCOUNT["deletepacked"] : 400 )."'>".$lang_settings['text_delete_packed_note_two'], 1);
	tr($lang_settings['row_delete_unpacked'],$lang_settings['text_delete_unpacked_note_one']."<input type='text' style=\"width: 50px\" name=deleteunpacked value='".(isset($ACCOUNT["deleteunpacked"]) ? $ACCOUNT["deleteunpacked"] : 150 )."'>".$lang_settings['text_delete_unpacked_note_two'], 1);
	tr($lang_settings['row_delete_no_transfer'],$lang_settings['text_delete_transfer_note_one']."<input type='text' style=\"width: 50px\" name=deletenotransfer value='".(isset($ACCOUNT["deletenotransfer"]) ? $ACCOUNT["deletenotransfer"] : 60 )."'>".$lang_settings['text_delete_transfer_note_two']."<input type='text' style=\"width: 50px\" name=deletenotransfertwo value='".(isset($ACCOUNT["deletenotransfertwo"]) ? $ACCOUNT["deletenotransfertwo"] : 0 )."'>".$lang_settings['text_delete_transfer_note_three'], 1);
	print("<tr><td colspan=2 align=center><b>".$lang_settings['text_user_promotion_demotion']."</b></td></tr>");
	tr($lang_settings['row_ban_peasant_one'].get_user_class_name(UC_PEASANT,false,false,true).$lang_settings['row_ban_peasant_two'],get_user_class_name(UC_PEASANT,false,true,true).$lang_settings['text_ban_peasant_note_one']."<input type='text' style=\"width: 50px\" name=deletepeasant value='".(isset($ACCOUNT["deletepeasant"]) ? $ACCOUNT["deletepeasant"] : 30 )."'>".$lang_settings['text_ban_peasant_note_two'], 1);
    $inputAlias = "0_alias";
	tr($lang_settings['row_demoted_to_peasant_one'].get_user_class_name(UC_PEASANT,false,false,true).$lang_settings['row_demoted_to_peasant_two'],
        $lang_settings['text_alias'] . "<input type='text' style=\"width: 60px\" name='".$inputAlias."' value='".(isset($ACCOUNT[$inputAlias]) ? $ACCOUNT[$inputAlias] : '' )."'><br/>"
        .$lang_settings['text_demoted_peasant_note_one'].get_user_class_name(UC_PEASANT,false,true,true).$lang_settings['text_demoted_peasant_note_two']."<br /><ul>
		<li>".$lang_settings['text_downloaded_amount_larger_than']."<input type='text' style=\"width: 50px\" name=psdlone value='".(isset($ACCOUNT["psdlone"]) ? $ACCOUNT["psdlone"] : 50 )."'>".$lang_settings['text_and_ratio_below']."<input type='text' style=\"width: 50px\" name=psratioone value='".(isset($ACCOUNT["psratioone"]) ? $ACCOUNT["psratioone"] : 0.4 )."'>".$lang_settings['text_demote_peasant_default_one']."</li>
		<li>".$lang_settings['text_downloaded_amount_larger_than']."<input type='text' style=\"width: 50px\" name=psdltwo value='".(isset($ACCOUNT["psdltwo"]) ? $ACCOUNT["psdltwo"] : 100 )."'>".$lang_settings['text_and_ratio_below']."<input type='text' style=\"width: 50px\" name=psratiotwo value='".(isset($ACCOUNT["psratiotwo"]) ? $ACCOUNT["psratiotwo"] : 0.5 )."'>".$lang_settings['text_demote_peasant_default_two']."</li>
		<li>".$lang_settings['text_downloaded_amount_larger_than']."<input type='text' style=\"width: 50px\" name=psdlthree value='".(isset($ACCOUNT["psdlthree"]) ? $ACCOUNT["psdlthree"] : 200 )."'>".$lang_settings['text_and_ratio_below']."<input type='text' style=\"width: 50px\" name=psratiothree value='".(isset($ACCOUNT["psratiothree"]) ? $ACCOUNT["psratiothree"] : 0.6 )."'>".$lang_settings['text_demote_peasant_default_three']."</li>
		<li>".$lang_settings['text_downloaded_amount_larger_than']."<input type='text' style=\"width: 50px\" name=psdlfour value='".(isset($ACCOUNT["psdlfour"]) ? $ACCOUNT["psdlfour"] : 400 )."'>".$lang_settings['text_and_ratio_below']."<input type='text' style=\"width: 50px\" name=psratiofour value='".(isset($ACCOUNT["psratiofour"]) ? $ACCOUNT["psratiofour"] : 0.7 )."'>".$lang_settings['text_demote_peasant_default_four']."</li>
		<li>".$lang_settings['text_downloaded_amount_larger_than']."<input type='text' style=\"width: 50px\" name=psdlfive value='".(isset($ACCOUNT["psdlfive"]) ? $ACCOUNT["psdlfive"] : 800 )."'>".$lang_settings['text_and_ratio_below']."<input type='text' style=\"width: 50px\" name=psratiofive value='".(isset($ACCOUNT["psratiofive"]) ? $ACCOUNT["psratiofive"] : 0.8 )."'>".$lang_settings['text_demote_peasant_default_five']."</li>
		</ul><br />".$lang_settings['text_demote_peasant_note'], 1);

    $inputAlias = "1_alias";
    tr($lang_settings['row_default_user_one'].get_user_class_name(UC_USER,false,false,true).$lang_settings['row_default_user_two'],
        $lang_settings['text_alias'] . "<input type='text' style=\"width: 60px\" name='".$inputAlias."' value='".(isset($ACCOUNT[$inputAlias]) ? $ACCOUNT[$inputAlias] : '' )."'>", 1);

	function promotion_criteria($class, $input, $time, $dl, $prratio, $deratio, $defaultInvites=0, $defaultSeedPoints = 0, $defaultAlias = ''){
		global $lang_settings;
		global $ACCOUNT;
		$inputtime = $input."time";
		$inputdl = $input."dl";
		$inputprratio = $input."prratio";
		$inputderatio = $input."deratio";
		$inputSeedPoints = $class . "_min_seed_points";
		$inputAlias = $class . "_alias";
		if (!isset($class))
			return;
		$x = $lang_settings['row_promote_to_one'].get_user_class_name($class,false,false,true).$lang_settings['row_promote_to_two'];
		$y = $lang_settings['text_alias'] . "<input type='text' style=\"width: 60px\" name='".$inputAlias."' value='".(isset($ACCOUNT[$inputAlias]) ? $ACCOUNT[$inputAlias] : $defaultAlias )."'><br/>"
		    .$lang_settings['text_member_longer_than']."<input type='text' style=\"width: 50px\" name='".$inputtime."' value='".(isset($ACCOUNT[$inputtime]) ? $ACCOUNT[$inputtime] : $time )."'>"
            .$lang_settings['text_seed_points_more_than']."<input type='text' style=\"width: 60px\" name='".$inputSeedPoints."' value='".(isset($ACCOUNT[$inputSeedPoints]) ? $ACCOUNT[$inputSeedPoints] : $defaultSeedPoints )."'>"
            .$lang_settings['text_downloaded_more_than']."<input type='text' style=\"width: 50px\" name='".$inputdl."' value='".(isset($ACCOUNT[$inputdl]) ? $ACCOUNT[$inputdl] : $dl )."'>"
            .$lang_settings['text_with_ratio_above']."<input type='text' style=\"width: 50px\" name='".$inputprratio."' value='".(isset($ACCOUNT[$inputprratio]) ? $ACCOUNT[$inputprratio] : $prratio )."'>"
            .$lang_settings['text_be_promoted_to'].get_user_class_name($class,false,true,true).$lang_settings['text_promote_to_default_one']."'".$time."', '".$dl."', '".$defaultSeedPoints."', '".$prratio."'.<br />"
            .$lang_settings['text_demote_with_ratio_below']."<input type='text' style=\"width: 50px\" name='".$inputderatio."' value='".(isset($ACCOUNT[$inputderatio]) ? $ACCOUNT[$inputderatio] : $deratio )."'>".$lang_settings['text_promote_to_default_two']."'".$deratio."'.<br />"
            .$lang_settings['text_users_get']."<input type='text' style=\"width: 50px\" name='getInvitesByPromotion[".$class."]' value='".(isset($ACCOUNT['getInvitesByPromotion'][$class]) ? $ACCOUNT['getInvitesByPromotion'][$class] : $defaultInvites )."'>".$lang_settings['text_invitations_default']."'".$defaultInvites."'.";

		tr($x, $y, 1);
	}
	promotion_criteria(UC_POWER_USER, "pu", 4, 50, 1.05, 0.95, 1, \App\Models\User::$classes[UC_POWER_USER]['min_seed_points']);
	promotion_criteria(UC_ELITE_USER, "eu", 8, 120, 1.55, 1.45, 0, \App\Models\User::$classes[UC_ELITE_USER]['min_seed_points']);
	promotion_criteria(UC_CRAZY_USER, "cu", 15, 300, 2.05, 1.95, 2, \App\Models\User::$classes[UC_CRAZY_USER]['min_seed_points']);
	promotion_criteria(UC_INSANE_USER, "iu", 25, 500, 2.55, 2.45, 0, \App\Models\User::$classes[UC_INSANE_USER]['min_seed_points']);
	promotion_criteria(UC_VETERAN_USER, "vu", 40, 750, 3.05, 2.95, 3, \App\Models\User::$classes[UC_VETERAN_USER]['min_seed_points']);
	promotion_criteria(UC_EXTREME_USER, "exu", 60, 1024, 3.55, 3.45, 0, \App\Models\User::$classes[UC_EXTREME_USER]['min_seed_points']);
	promotion_criteria(UC_ULTIMATE_USER, "uu", 80, 1536, 4.05, 3.95, 5, \App\Models\User::$classes[UC_ULTIMATE_USER]['min_seed_points']);
	promotion_criteria(UC_NEXUS_MASTER, "nm", 100, 3072, 4.55, 4.45, 10, \App\Models\User::$classes[UC_NEXUS_MASTER]['min_seed_points']);
	tr($lang_settings['row_save_settings'],"<input type='submit' name='save' value='".$lang_settings['submit_save_settings']."'>", 1);
	print ("</form>");
}
elseif ($action == 'torrentsettings')
{
	$TORRENT = get_setting_from_db('torrent');
	stdhead($lang_settings['head_torrent_settings']);
	print ($notice);
	print ("<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='savesettings_torrent'>");

    tr($lang_settings['row_sticky_first_level_background_color'],"<input type='text' name=sticky_first_level_background_color style=\"width: 100px\" value={$TORRENT['sticky_first_level_background_color']}> ".$lang_settings['text_sticky_first_level_background_color_note'], 1);
    tr($lang_settings['row_sticky_second_level_background_color'],"<input type='text' name=sticky_second_level_background_color style=\"width: 100px\" value={$TORRENT['sticky_second_level_background_color']}> ".$lang_settings['text_sticky_second_level_background_color_note'], 1);
    yesorno($lang_settings['row_download_support_passkey'], 'download_support_passkey', $TORRENT["download_support_passkey"], $lang_settings['text_download_support_passkey_note']);
    yesorno($lang_settings['row_approval_status_icon_enabled'], 'approval_status_icon_enabled', $TORRENT["approval_status_icon_enabled"], $lang_settings['text_approval_status_icon_enabled_note']);
    yesorno($lang_settings['row_approval_status_none_visible'], 'approval_status_none_visible', $TORRENT["approval_status_none_visible"], $lang_settings['text_approval_status_none_visible_note']);
    yesorno($lang_settings['row_promotion_rules'], 'prorules', $TORRENT["prorules"], $lang_settings['text_promotion_rules_note']);
	tr($lang_settings['row_random_promotion'], $lang_settings['text_random_promotion_note_one']."<ul><li><input type='text' style=\"width: 50px\" name=randomhalfleech value='".(isset($TORRENT["randomhalfleech"]) ? $TORRENT["randomhalfleech"] : 5 )."'>".$lang_settings['text_halfleech_chance_becoming']."</li><li><input type='text' style=\"width: 50px\" name=randomfree value='".(isset($TORRENT["randomfree"]) ? $TORRENT["randomfree"] : 2 )."'>".$lang_settings['text_free_chance_becoming']."</li><li><input type='text' style=\"width: 50px\" name=randomtwoup value='".(isset($TORRENT["randomtwoup"]) ? $TORRENT["randomtwoup"] : 2 )."'>".$lang_settings['text_twoup_chance_becoming']."</li><li><input type='text' style=\"width: 50px\" name=randomtwoupfree value='".(isset($TORRENT["randomtwoupfree"]) ? $TORRENT["randomtwoupfree"] : 1 )."'>".$lang_settings['text_freetwoup_chance_becoming']."</li><li><input type='text' style=\"width: 50px\" name=randomtwouphalfdown value='".(isset($TORRENT["randomtwouphalfdown"]) ? $TORRENT["randomtwouphalfdown"] : 0 )."'>".$lang_settings['text_twouphalfleech_chance_becoming']."</li><li><input type='text' style=\"width: 50px\" name=randomthirtypercentdown value='".(isset($TORRENT["randomthirtypercentdown"]) ? $TORRENT["randomthirtypercentdown"] : 0 )."'>".$lang_settings['text_thirtypercentleech_chance_becoming']."</li></ul>".$lang_settings['text_random_promotion_note_two'], 1);
	tr($lang_settings['row_large_torrent_promotion'], $lang_settings['text_torrent_larger_than']."<input type='text' style=\"width: 50px\" name=largesize value='".(isset($TORRENT["largesize"]) ? $TORRENT["largesize"] : 20 )."'>".$lang_settings['text_gb_promoted_to']."<select name=largepro>".promotion_selection((isset($TORRENT['largepro']) ? $TORRENT['largepro'] : 2), 1)."</select>".$lang_settings['text_by_system_upon_uploading']."<br />".$lang_settings['text_large_torrent_promotion_note'], 1);
	tr($lang_settings['row_promotion_timeout'], $lang_settings['text_promotion_timeout_note_one']."<ul>
<li>".$lang_settings['text_halfleech_will_become']."<select name=halfleechbecome>".promotion_selection((isset($TORRENT['halfleechbecome']) ? $TORRENT['halfleechbecome'] : 1), 5)."</select>".$lang_settings['text_after']."<input type='text' style=\"width: 50px\" name=expirehalfleech value='".(isset($TORRENT["expirehalfleech"]) ? $TORRENT["expirehalfleech"] : 150 )."'>".$lang_settings['text_halfleech_timeout_default']."</li>

<li>".$lang_settings['text_free_will_become']."<select name=freebecome>".promotion_selection((isset($TORRENT['freebecome']) ? $TORRENT['freebecome'] : 1), 2)."</select>".$lang_settings['text_after']."<input type='text' style=\"width: 50px\" name=expirefree value='".(isset($TORRENT["expirefree"]) ? $TORRENT["expirefree"] : 60 )."'>".$lang_settings['text_free_timeout_default']."</li>

<li>".$lang_settings['text_twoup_will_become']."<select name=twoupbecome>".promotion_selection((isset($TORRENT['twoupbecome']) ? $TORRENT['twoupbecome'] : 1), 3)."</select>".$lang_settings['text_after']."<input type='text' style=\"width: 50px\" name=expiretwoup value='".(isset($TORRENT["expiretwoup"]) ? $TORRENT["expiretwoup"] : 60 )."'>".$lang_settings['text_twoup_timeout_default']."</li>

<li>".$lang_settings['text_freetwoup_will_become']."<select name=twoupfreebecome>".promotion_selection((isset($TORRENT['twoupfreebecome']) ? $TORRENT['twoupfreebecome'] : 1), 4)."</select>".$lang_settings['text_after']."<input type='text' style=\"width: 50px\" name=expiretwoupfree value='".(isset($TORRENT["expiretwoupfree"]) ? $TORRENT["expiretwoupfree"] : 30 )."'>".$lang_settings['text_freetwoup_timeout_default']."</li>

<li>".$lang_settings['text_halfleechtwoup_will_become']."<select name=twouphalfleechbecome>".promotion_selection((isset($TORRENT['twouphalfleechbecome']) ? $TORRENT['twouphalfleechbecome'] : 1), 6)."</select>".$lang_settings['text_after']."<input type='text' style=\"width: 50px\" name=expiretwouphalfleech value='".(isset($TORRENT["expiretwouphalfleech"]) ? $TORRENT["expiretwouphalfleech"] : 30 )."'>".$lang_settings['text_halfleechtwoup_timeout_default']."</li>

<li>".$lang_settings['text_thirtypercentleech_will_become']."<select name=thirtypercentleechbecome>".promotion_selection((isset($TORRENT['thirtypercentleechbecome']) ? $TORRENT['thirtypercentleechbecome'] : 1), 7)."</select>".$lang_settings['text_after']."<input type='text' style=\"width: 50px\" name=expirethirtypercentleech value='".(isset($TORRENT["expirethirtypercentleech"]) ? $TORRENT["expirethirtypercentleech"] : 30 )."'>".$lang_settings['text_thirtypercentleech_timeout_default']."</li>

<li>".$lang_settings['text_normal_will_become']."<select name=normalbecome>".promotion_selection((isset($TORRENT['normalbecome']) ? $TORRENT['normalbecome'] : 1), 0)."</select>".$lang_settings['text_after']."<input type='text' style=\"width: 50px\" name=expirenormal value='".(isset($TORRENT["expirenormal"]) ? $TORRENT["expirenormal"] : 0 )."'>".$lang_settings['text_normal_timeout_default']."</li>

</ul>".$lang_settings['text_promotion_timeout_note_two'], 1);

    tr($lang_settings['claim_label'], "<ul>
<li>".$lang_settings['claim_enabled'] . sprintf('&nbsp;<label><input type="radio" name="claim_enabled" value="yes"%s/>Yes</label><label><input type="radio" name="claim_enabled" value="no"%s/>No</label>', $TORRENT['claim_enabled'] == 'yes' ? ' checked' : '', $TORRENT['claim_enabled'] == 'no' ? ' checked' : '')."</li>
<li>".sprintf($lang_settings['claim_torrent_ttl'], sprintf('<input type="number" name="claim_torrent_ttl" value="%s" style="width: 50px"/>', $TORRENT['claim_torrent_ttl'] ?? \App\Models\Claim::TORRENT_TTL))."</li>
<li>".sprintf($lang_settings['claim_torrent_user_counts_up_limit'], sprintf('<input type="number" name="claim_torrent_user_counts_up_limit" value="%s" style="width: 50px"/>', $TORRENT['claim_torrent_user_counts_up_limit'] ?? \App\Models\Claim::USER_UP_LIMIT))."</li>
<li>".sprintf($lang_settings['claim_user_torrent_counts_up_limit'], sprintf('<input type="number" name="claim_user_torrent_counts_up_limit" value="%s" style="width: 50px"/>', $TORRENT['claim_user_torrent_counts_up_limit'] ?? \App\Models\Claim::TORRENT_UP_LIMIT))."</li>
<li>".sprintf($lang_settings['claim_remove_deduct_user_bonus'], sprintf('<input type="number" name="claim_remove_deduct_user_bonus" value="%s" style="width: 50px"/>', $TORRENT['claim_remove_deduct_user_bonus'] ?? \App\Models\Claim::REMOVE_DEDUCT))."</li>
<li>".sprintf($lang_settings['claim_give_up_deduct_user_bonus'], sprintf('<input type="number" name="claim_give_up_deduct_user_bonus" value="%s" style="width: 50px"/>', $TORRENT['claim_give_up_deduct_user_bonus'] ?? \App\Models\Claim::GIVE_UP_DEDUCT))."</li>
<li>".sprintf($lang_settings['claim_bonus_multiplier'], sprintf('<input type="number" name="claim_bonus_multiplier" value="%s" style="width: 50px"/>', $TORRENT['claim_bonus_multiplier'] ?? \App\Models\Claim::BONUS_MULTIPLIER))."</li>
<li>".sprintf($lang_settings['claim_reach_standard'], sprintf('<input type="number" name="claim_reach_standard_seed_time" value="%s" style="width: 50px"/>', $TORRENT['claim_reach_standard_seed_time'] ?? \App\Models\Claim::STANDARD_SEED_TIME_HOURS), sprintf('<input type="number" name="claim_reach_standard_uploaded" value="%s" style="width: 50px"/>', $TORRENT['claim_reach_standard_uploaded'] ?? \App\Models\Claim::STANDARD_UPLOADED_TIMES))."</li>
</ul>", 1);

    do_action('setting_fields', $TORRENT);

	tr($lang_settings['row_auto_pick_hot'], $lang_settings['text_torrents_uploaded_within']."<input type='text' style=\"width: 50px\" name=hotdays value='".(isset($TORRENT["hotdays"]) ? $TORRENT["hotdays"] : 7 )."'>".$lang_settings['text_days_with_more_than']."<input type='text' style=\"width: 50px\" name=hotseeder value='".(isset($TORRENT["hotseeder"]) ? $TORRENT["hotseeder"] : 10 )."'>".$lang_settings['text_be_picked_as_hot']."<br />".$lang_settings['text_auto_pick_hot_default'], 1);
	tr($lang_settings['row_uploader_get_double'], $lang_settings['text_torrent_uploader_gets']."<input type='text' style=\"width: 50px\" name=uploaderdouble value='".(isset($TORRENT["uploaderdouble"]) ? $TORRENT["uploaderdouble"] : 1 )."'>".$lang_settings['text_times_uploading_credit'].$lang_settings['text_uploader_get_double_default'], 1);
	tr($lang_settings['row_delete_dead_torrents'], $lang_settings['text_torrents_being_dead_for']."<input type='text' style=\"width: 50px\" name=deldeadtorrent value='".(isset($TORRENT["deldeadtorrent"]) ? $TORRENT["deldeadtorrent"] : 0 )."'>".$lang_settings['text_days_be_deleted']."<br />".$lang_settings['row_delete_dead_torrents_note'], 1);
	tr($lang_settings['row_save_settings'],"<input type='submit' name='save' value='".$lang_settings['submit_save_settings']."'>", 1);
	print ("</form>");
}
elseif ($action == 'mainsettings')	// main settings
{
	$MAIN = get_setting_from_db('main');
	stdhead($lang_settings['head_main_settings']);
	print ($notice);
	print ("<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='savesettings_main'>");
	$sh = "gmail.com";

	yesorno($lang_settings['row_site_online'], 'site_online', $MAIN['site_online'], $lang_settings['text_site_online_note']);
	yesorno($lang_settings['row_enable_invite_system'], 'invitesystem', $MAIN['invitesystem'], $lang_settings['text_invite_system_note']);
	tr($lang_settings['row_initial_uploading_amount'],"<input type='text' name=iniupload style=\"width: 100px\" value={$MAIN['iniupload']}> ".$lang_settings['text_initial_uploading_amount_note'], 1);
	tr($lang_settings['row_initial_invites'],"<input type='text' name=invite_count style=\"width: 50px\" value={$MAIN['invite_count']}> ".$lang_settings['text_initial_invites_note'], 1);
	tr($lang_settings['row_invite_timeout'],"<input type='text' name=invite_timeout style=\"width: 50px\" value={$MAIN['invite_timeout']}> ".$lang_settings['text_invite_timeout_note'], 1);
	yesorno($lang_settings['row_enable_registration_system'], 'registration', $MAIN['registration'], $lang_settings['row_allow_registrations']);
	tr($lang_settings['row_verification_type'],"<input type='radio' name='verification'" . ($MAIN["verification"] == "email" ? " checked" : " checked") . " value='email'> ".$lang_settings['text_email'] ." <input type='radio' name='verification'" . ($MAIN["verification"] == "admin" ? " checked" : "") . " value='admin'> ".$lang_settings['text_admin']." <input type='radio' name='verification'" . ($MAIN["verification"] == "automatic" ? " checked" : "") . " value='automatic'> ".$lang_settings['text_automatically']."<br />".$lang_settings['text_verification_type_note'], 1);
	yesorno($lang_settings['row_enable_wait_system'],'waitsystem', $MAIN['waitsystem'], $lang_settings['text_wait_system_note']);
	yesorno($lang_settings['row_enable_max_slots_system'],'maxdlsystem', $MAIN['maxdlsystem'], $lang_settings['text_max_slots_system_note']);
	yesorno($lang_settings['row_show_polls'], 'showpolls', $MAIN['showpolls'], $lang_settings['text_show_polls_note']);
	yesorno($lang_settings['row_show_stats'],'showstats', $MAIN['showstats'], $lang_settings['text_show_stats_note']);
	yesorno($lang_settings['row_show_last_posts'],'showlastxforumposts', $MAIN['showlastxforumposts'], $lang_settings['text_show_last_posts_note']);
	yesorno($lang_settings['row_show_last_torrents'],'showlastxtorrents', $MAIN['showlastxtorrents'], $lang_settings['text_show_last_torrents_note']);
	yesorno($lang_settings['row_show_server_load'],'showtrackerload', $MAIN['showtrackerload'], $lang_settings['text_show_server_load_note']);
	yesorno($lang_settings['row_show_forum_stats'],'showforumstats', $MAIN['showforumstats'], $lang_settings['text_show_forum_stats_note']);
	yesorno($lang_settings['row_show_hot'],'showhotmovies', $MAIN['showhotmovies'], $lang_settings['text_show_hot_note']);
	yesorno($lang_settings['row_show_classic'],'showclassicmovies', $MAIN['showclassicmovies'], $lang_settings['text_show_classic_note']);
	yesorno($lang_settings['row_show_top_uploader'],'show_top_uploader', $MAIN['show_top_uploader'], $lang_settings['text_show_top_uploader_note']);
	yesorno($lang_settings['row_enable_imdb_system'],'showimdbinfo', $MAIN['showimdbinfo'], $lang_settings['text_imdb_system_note']);

    $imdbLanguages = "<select name=imdb_language>";
    foreach (\Nexus\Imdb\Imdb::listSupportLanguages() as $key => $value) {
        $imdbLanguages .= sprintf('<option value="%s"%s>%s</option>', $key, $key == $MAIN['imdb_language'] ? ' selected' : '', $value);
    }
    $imdbLanguages .= "</select>";
    tr($lang_settings['row_imdb_language'], $imdbLanguages."<br />".$lang_settings['text_imdb_language_note'], 1);

	yesorno($lang_settings['row_enable_pt_gen_system'],'enable_pt_gen_system', $MAIN['enable_pt_gen_system'], $lang_settings['text_enable_pt_gen_system_note']);
	tr($lang_settings['row_pt_gen_api_point'],"<input type='text' name=\"pt_gen_api_point\" style=\"width: 300px\" value={$MAIN['pt_gen_api_point']}> ".$lang_settings['text_pt_gen_api_point_note'], 1);
	yesorno($lang_settings['row_enable_nfo'],'enablenfo', $MAIN['enablenfo'], $lang_settings['text_enable_nfo_note']);
	yesorno($lang_settings['row_enable_technical_info'],'enable_technical_info', $MAIN['enable_technical_info'], $lang_settings['text_enable_technical_info']);
	yesorno($lang_settings['row_enable_school_system'],'enableschool', $MAIN['enableschool'], $lang_settings['text_school_system_note']);
	yesorno($lang_settings['row_restrict_email_domain'],'restrictemail', $MAIN['restrictemail'], $lang_settings['text_restrict_email_domain_note']);
	yesorno($lang_settings['row_show_shoutbox'],'showshoutbox', $MAIN['showshoutbox'], $lang_settings['text_show_shoutbox_note']);
	yesorno($lang_settings['row_show_funbox'],'showfunbox', $MAIN['showfunbox'], $lang_settings['text_show_funbox_note']);
	yesorno($lang_settings['row_enable_offer_section'],'showoffer', $MAIN['showoffer'], $lang_settings['text_offer_section_note']);
	yesorno($lang_settings['row_show_donation'],'donation', $MAIN['donation'], $lang_settings['text_show_donation_note']);
//	if (THISTRACKER == "HDStar")
	yesorno($lang_settings['row_show_special_section'],'spsct', $MAIN['spsct'], $lang_settings['text_show_special_section_note']);
	yesorno($lang_settings['row_weekend_free_uploading'],'sptime', $MAIN['sptime'], $lang_settings['text_weekend_free_uploading_note']);
	yesorno($lang_settings['row_enable_helpbox'],'showhelpbox', $MAIN['showhelpbox'], $lang_settings['text_helpbox_note']);
	yesorno($lang_settings['row_enable_bitbucket'],'enablebitbucket', $MAIN['enablebitbucket'], $lang_settings['text_bitbucket_note']);
	yesorno($lang_settings['row_enable_small_description'],'smalldescription', $MAIN['smalldescription'], $lang_settings['text_small_description_note']);
//	if (THISTRACKER == "PTShow")
	yesorno($lang_settings['row_ptshow_naming_style'],'altname', $MAIN['altname'], $lang_settings['text_ptshow_naming_style_note']);
	yesorno($lang_settings['row_use_external_forum'],'extforum', $MAIN['extforum'], $lang_settings['text_use_external_forum_note']);
	tr($lang_settings['row_external_forum_url'],"<input type='text' style=\"width: 300px\" name=extforumurl value='".($MAIN["extforumurl"] ? $MAIN["extforumurl"] : "")."'> ".$lang_settings['text_external_forum_url_note'], 1);
	$res = sql_query("SELECT id, name FROM searchbox") or sqlerr(__FILE__, __LINE__);
	$catlist = "";
	$bcatlist = $scatlist = '';
	while($array = mysql_fetch_array($res)){
		$bcatlist .= "<input type=radio name=browsecat value='".$array['id']."'".($MAIN["browsecat"] == $array['id'] ? " checked" : "").">".$array['name']."&nbsp;";
		$scatlist .= "<input type=radio name=specialcat value='".$array['id']."'".($MAIN["specialcat"] == $array['id'] ? " checked" : "").">".$array['name']."&nbsp;";
	}
	tr($lang_settings['row_torrents_category_mode'], $bcatlist."<br />".$lang_settings['text_torrents_category_mode_note'], 1);
//	if (THISTRACKER == "HDStar")
	tr($lang_settings['row_special_category_mode'], $scatlist."<br />".$lang_settings['text_special_category_mode_note'], 1);

    $allSiteLanguages = \App\Models\Language::query()->where('site_lang', 1)->get();
    $allEnabled = \App\Models\Language::listEnabled(true);
    $langlist = "";
    foreach ($allSiteLanguages as $lang) {
        $langlist .= sprintf('<label><input type="checkbox" name="site_language_enabled[]" value="%s"%s/>%s</label>&nbsp;', $lang->site_lang_folder, in_array($lang->site_lang_folder, $allEnabled) ? " checked" : "", $lang->lang_name);
    }
    tr($lang_settings['row_site_language_enabled'], $langlist."<br />".$lang_settings['text_site_language_enabled_note'], 1);

	$langlist = "";
	foreach ($allSiteLanguages as $lang) {
        $langlist .= sprintf(
            '<label><input type="radio" name="defaultlang" value="%s"%s%s/>%s</label>&nbsp;',
            $lang->site_lang_folder,
            $MAIN["defaultlang"] == $lang->site_lang_folder ? " checked" : "",
            !in_array($lang->site_lang_folder, $allEnabled) ? " disabled" : "",
            $lang->lang_name
        );
    }
	tr($lang_settings['row_default_site_language'], $langlist."<br />".$lang_settings['text_default_site_language_note'], 1);
	$changeDefaultLangJs = <<<JS
let msg = "{$lang_settings['keep_at_least_one']}"
jQuery('input[name="site_language_enabled[]"]').on("change", function () {
    let enabledCount = jQuery('input[name="site_language_enabled[]"]:checked').length
    if (enabledCount == 0 && !this.checked) {
         alert(msg)
        this.checked = true
        return
    }
    let target = jQuery('input[name="defaultlang"][value="' + this.value +'"]')
    if (this.checked) {
        target.prop("disabled", false);
    } else {
        target.prop("disabled", true);
        if (target.is(":checked")) {
            target.prop("checked", false)
            jQuery('input[name="defaultlang"]:enabled:first').prop("checked", true)
        }
    }
})
JS;
    \Nexus\Nexus::js($changeDefaultLangJs, 'footer', false);

	$res = sql_query("SELECT * FROM stylesheets ORDER BY name") or sqlerr(__FILE__, __LINE__);
	$csslist = "<select name=defstylesheet>";
	while($array = mysql_fetch_array($res))
		$csslist .= "<option value='".$array['id']."'".($MAIN["defstylesheet"] == $array['id'] ? " selected" : "").">".$array['name']."</option>";
	$csslist .= "</select>";
	tr($lang_settings['row_default_stylesheet'], $csslist."<br />".$lang_settings['text_default_stylesheet_note'], 1);
	tr($lang_settings['row_site_logo'],"<input type='text' style=\"width: 100px\" name='logo' value='".($MAIN["logo"] ? $MAIN["logo"] : "")."'>".$lang_settings['text_site_logo_note'], 1);
	tr($lang_settings['row_max_torrent_size'],"<input type='text' style=\"width: 100px\" name='max_torrent_size' value='".($MAIN["max_torrent_size"] ? $MAIN["max_torrent_size"] : 1048576)."'>".$lang_settings['text_max_torrent_size_note'], 1);
	tr($lang_settings['row_announce_interval'], $lang_settings['text_announce_interval_note_one']."<br /><ul><li>".$lang_settings['text_announce_default']."<input type='text' style=\"width: 100px\" name=announce_interval value='".($MAIN["announce_interval"] ? $MAIN["announce_interval"] : 1800)."'> ".$lang_settings['text_announce_default_default']."</li><li>".$lang_settings['text_for_torrents_older_than']."<input type='text' style=\"width: 100px\" name=annintertwoage value='".($MAIN["annintertwoage"] ? $MAIN["annintertwoage"] : 7)."'>".$lang_settings['text_days']."<input type='text' style=\"width: 100px\" name=annintertwo value='".($MAIN["annintertwo"] ? $MAIN["annintertwo"] : 2700)."'> ".$lang_settings['text_announce_two_default']."</li><li>".$lang_settings['text_for_torrents_older_than']."<input type='text' style=\"width: 100px\" name=anninterthreeage value='".($MAIN["anninterthreeage"] ? $MAIN["anninterthreeage"] : 30)."'>".$lang_settings['text_days']."<input type='text' style=\"width: 100px\" name=anninterthree value='".($MAIN["anninterthree"] ? $MAIN["anninterthree"] : 3600)."'> ".$lang_settings['text_announce_three_default']."</li></ul>".$lang_settings['text_announce_interval_note_two'], 1);
	tr($lang_settings['row_cleanup_interval'], $lang_settings['text_cleanup_interval_note_one']."<br /><ul><li>".$lang_settings['text_priority_one']."<input type='text' style=\"width: 100px\" name=autoclean_interval_one value='".($MAIN["autoclean_interval_one"] ? $MAIN["autoclean_interval_one"] : 900)."'> ".$lang_settings['text_priority_one_note']."</li><li>".$lang_settings['text_priority_two']."<input type='text' style=\"width: 100px\" name=autoclean_interval_two value='".($MAIN["autoclean_interval_two"] ? $MAIN["autoclean_interval_two"] : 1800)."'> ".$lang_settings['text_priority_two_note']."</li><li>".$lang_settings['text_priority_three']."<input type='text' style=\"width: 100px\" name=autoclean_interval_three value='".($MAIN["autoclean_interval_three"] ? $MAIN["autoclean_interval_three"] : 3600)."'> ".$lang_settings['text_priority_three_note']."</li><li>".$lang_settings['text_priority_four']."<input type='text' style=\"width: 100px\" name=autoclean_interval_four value='".($MAIN["autoclean_interval_four"] ? $MAIN["autoclean_interval_four"] : 43200)."'> ".$lang_settings['text_priority_four_note']."</li><li>".$lang_settings['text_priority_five']."<input type='text' style=\"width: 100px\" name=autoclean_interval_five value='".($MAIN["autoclean_interval_five"] ? $MAIN["autoclean_interval_five"] : 648000)."'> ".$lang_settings['text_priority_five_note']."</li></ul>".$lang_settings['text_cleanup_interval_note_two'], 1);
	tr($lang_settings['row_signup_timeout'],"<input type='text' style=\"width: 100px\" name=signup_timeout value='".($MAIN["signup_timeout"] ? $MAIN["signup_timeout"] : 259200)."'> ".$lang_settings['text_signup_timeout_note'], 1);
	tr($lang_settings['row_min_offer_votes'],"<input type='text' style=\"width: 100px\" name=minoffervotes value='".($MAIN["minoffervotes"] ? $MAIN["minoffervotes"] : 15)."'> ".$lang_settings['text_min_offer_votes_note'], 1);
	tr($lang_settings['row_offer_vote_timeout'],"<input type='text' style=\"width: 100px\" name=offervotetimeout value='".(isset($MAIN["offervotetimeout"]) ? $MAIN["offervotetimeout"] : 259200)."'> ".$lang_settings['text_offer_vote_timeout_note'], 1);
	tr($lang_settings['row_offer_upload_timeout'],"<input type='text' style=\"width: 100px\" name=offeruptimeout value='".(isset($MAIN["offeruptimeout"]) ? $MAIN["offeruptimeout"] : 86400)."'> ".$lang_settings['text_offer_upload_timeout_note'], 1);
	tr($lang_settings['row_max_subtitle_size'],"<input type='text' style=\"width: 100px\" name=maxsubsize value='".(isset($MAIN["maxsubsize"]) ? $MAIN["maxsubsize"] : 3145728)."'> ". $lang_settings['text_max_subtitle_size_note'], 1);
	tr($lang_settings['row_posts_per_page'],"<input type='text' style=\"width: 100px\" name=postsperpage value='".($MAIN["postsperpage"] ? $MAIN["postsperpage"] : 10)."'> ".$lang_settings['text_posts_per_page_note'], 1);
	tr($lang_settings['row_topics_per_page'],"<input type='text' style=\"width: 100px\" name=topicsperpage value='".($MAIN["topicsperpage"] ? $MAIN["topicsperpage"] : 20)."'> ".$lang_settings['text_topics_per_page_note'], 1);
	tr($lang_settings['row_torrents_per_page'],"<input type='text' style=\"width: 100px\" name=torrentsperpage value='".($MAIN["torrentsperpage"] ? $MAIN["torrentsperpage"] : 50)."'> ".$lang_settings['text_torrents_per_page_note'], 1);
	tr($lang_settings['row_number_of_news'],"<input type='text' style=\"width: 100px\" name=maxnewsnum value='".($MAIN["maxnewsnum"] ? $MAIN["maxnewsnum"] : 3)."'> ".$lang_settings['text_number_of_news_note'], 1);
	tr($lang_settings['row_torrent_dead_time'],"<input type='text' style=\"width: 100px\" name=max_dead_torrent_time value='".($MAIN["max_dead_torrent_time"] ? $MAIN["max_dead_torrent_time"] : "21600")."'> ".$lang_settings['text_torrent_dead_time_note'], 1);
	tr($lang_settings['row_max_users'],"<input type='text' style=\"width: 100px\" name=maxusers value='".($MAIN["maxusers"] ? $MAIN["maxusers"] : "2500" )."'> ".$lang_settings['text_max_users'], 1);
	tr($lang_settings['row_site_accountant_userid'],"<input type='text' style=\"width: 200px\" name=\"ACCOUNTANTID\" value='".($MAIN['ACCOUNTANTID'] ? $MAIN['ACCOUNTANTID'] : "")."'> ".$lang_settings['text_site_accountant_userid_note'], 1);
	tr($lang_settings['row_alipay_account'],"<input type='text' style=\"width: 200px\" name=\"ALIPAYACCOUNT\" value='".($MAIN['ALIPAYACCOUNT'] ? $MAIN['ALIPAYACCOUNT'] : "")."'> ".$lang_settings['text_alipal_account_note'], 1);
	tr($lang_settings['row_paypal_account'],"<input type='text' style=\"width: 200px\" name=PAYPALACCOUNT value='".($MAIN["PAYPALACCOUNT"] ? $MAIN["PAYPALACCOUNT"] : "")."'> ".$lang_settings['text_paypal_account_note'], 1);
	tr($lang_settings['row_site_email'],"<input type='text' style=\"width: 200px\" name=SITEEMAIL value='".($MAIN["SITEEMAIL"] ? $MAIN["SITEEMAIL"] : "noreply@".$sh)."'> ".$lang_settings['text_site_email_note'], 1);
	tr($lang_settings['row_report_email'],"<input type='text' style=\"width: 200px\" name=reportemail value='".($MAIN["reportemail"] ? $MAIN["reportemail"] : "report@".$sh)."'> ".$lang_settings['text_report_email_note'], 1);
	tr($lang_settings['row_site_slogan'],"<input type='text' style=\"width: 300px\" name=SLOGAN value='".($MAIN["SLOGAN"] ? $MAIN["SLOGAN"]: "")."'> ".$lang_settings['text_site_slogan_note'], 1);
	tr($lang_settings['row_icp_license'],"<input type='text' style=\"width: 300px\" name=icplicense value='".($MAIN["icplicense"] ? $MAIN["icplicense"]: "")."'> ".$lang_settings['text_icp_license_note'], 1);
	tr($lang_settings['row_torrent_directory'], "<input type='text' style=\"width: 100px\" name=torrent_dir value='".($MAIN["torrent_dir"] ? $MAIN["torrent_dir"] : "torrents")."'> ".$lang_settings['text_torrent_directory'], 1);
	tr($lang_settings['row_bitbucket_directory'],"<input type='text' style=\"width: 100px\" name=bitbucket value='".($MAIN["bitbucket"] ? $MAIN["bitbucket"] : "bitbucket")."'> ".$lang_settings['text_bitbucket_directory_note'], 1);
	tr($lang_settings['row_torrent_name_prefix'], "<input type='text' style=\"width: 100px\" name=torrentnameprefix value='".($MAIN["torrentnameprefix"] ? $MAIN["torrentnameprefix"] : "[Nexus]")."'> ".$lang_settings['text_torrent_name_prefix_note'], 1);

	tr($lang_settings['row_save_settings'],"<input type='submit' name='save' value='".$lang_settings['submit_save_settings']."'>", 1);
	print ("</form>");
}
elseif ($action == 'miscsettings')
{
    $result = \App\Models\Setting::getByWhereRaw("name like 'misc.%'");
    $misc = $result['misc'] ?? [];
    stdhead($lang_settings['head_torrent_settings']);
    print ($notice);
    print ("<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='savesettings_misc'>");
    tr($lang_settings['row_misc_donation_custom'],"<textarea cols=\"100\"  rows=\"10\" name='donation_custom'>".($misc['donation_custom'] ?? '')."</textarea><br/>".$lang_settings['text_donation_custom_note'], 1);
    tr($lang_settings['row_save_settings'],"<input type='submit' name='save' value='".$lang_settings['submit_save_settings']."'>", 1);
    print ("</form>");
}
elseif ($action == 'showmenu')	// settings main page
{
	stdhead($lang_settings['head_website_settings']);
	print ($notice);
	tr($lang_settings['row_basic_settings'], "<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='basicsettings'><input type='submit' value=\"".$lang_settings['submit_basic_settings']."\"> ".$lang_settings['text_basic_settings_note']."</form>", 1);
	tr($lang_settings['row_main_settings'], "<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='mainsettings'><input type='submit' value=\"".$lang_settings['submit_main_settings']."\"> ".$lang_settings['text_main_settings_note']."</form>", 1);
	tr($lang_settings['row_smtp_settings'], "<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='smtpsettings'><input type='submit' value=\"".$lang_settings['submit_smtp_settings']."\"> ".$lang_settings['text_smtp_settings_note']."</form>", 1);
	tr($lang_settings['row_security_settings'],"<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='securitysettings'><input type='submit' value=\"".$lang_settings['submit_security_settings']."\"> ".$lang_settings['text_security_settings_note']."</form>", 1);
	tr($lang_settings['row_authority_settings'],"<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='authoritysettings'><input type='submit' value=\"".$lang_settings['submit_authority_settings']."\"> ".$lang_settings['text_authority_settings_note']."</form>", 1);
	tr($lang_settings['row_tweak_settings'],"<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='tweaksettings'><input type='submit' value=\"".$lang_settings['submit_tweak_settings']."\"> ".$lang_settings['text_tweak_settings_note']."</form>", 1);
	tr($lang_settings['row_bonus_settings'], "<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='bonussettings'><input type='submit' value=\"".$lang_settings['submit_bonus_settings']."\"> ".$lang_settings['text_bonus_settings_note']."</form>", 1);
	tr($lang_settings['row_account_settings'], "<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='accountsettings'><input type='submit' value=\"".$lang_settings['submit_account_settings']."\"> ".$lang_settings['text_account_settings_settings']."</form>", 1);
	tr($lang_settings['row_torrents_settings'], "<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='torrentsettings'><input type='submit' value=\"".$lang_settings['submit_torrents_settings']."\"> ".$lang_settings['text_torrents_settings_note']."</form>", 1);
	tr($lang_settings['row_attachment_settings'], "<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='attachmentsettings'><input type='submit' value=\"".$lang_settings['submit_attachment_settings']."\"> ".$lang_settings['text_attachment_settings_note']."</form>", 1);
	tr($lang_settings['row_advertisement_settings'], "<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='advertisementsettings'><input type='submit' value=\"".$lang_settings['submit_advertisement_settings']."\"> ".$lang_settings['text_advertisement_settings_note']."</form>", 1);
	tr($lang_settings['row_misc_settings'], "<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='miscsettings'><input type='submit' value=\"".$lang_settings['submit_misc_settings']."\"> ".$lang_settings['text_misc_settings_note']."</form>", 1);
//	tr($lang_settings['row_code_settings'], "<form method='post' action='".$_SERVER["SCRIPT_NAME"]."'><input type='hidden' name='action' value='codesettings'><input type='submit' value=\"".$lang_settings['submit_code_settings']."\"> ".$lang_settings['text_code_settings_note']."</form>", 1);
}
print("</table>");
stdfoot();
?>

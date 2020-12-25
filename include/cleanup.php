<?php
# IMPORTANT: Do not edit below unless you know what you are doing!
if(!defined('IN_TRACKER'))
die('Hacking attempt!');

function printProgress($msg) {
	echo $msg.'...done<br />';
	ob_flush();
	flush();
}
function docleanup($forceAll = 0, $printProgress = false) {
	//require_once(get_langfile_path("cleanup.php",true));
	global $lang_cleanup_target;
	global $torrent_dir, $signup_timeout, $max_dead_torrent_time, $autoclean_interval_one, $autoclean_interval_two, $autoclean_interval_three, $autoclean_interval_four, $autoclean_interval_five, $SITENAME,$bonus,$invite_timeout,$offervotetimeout_main,$offeruptimeout_main, $iniupload_main;
	global $donortimes_bonus, $perseeding_bonus, $maxseeding_bonus, $tzero_bonus, $nzero_bonus, $bzero_bonus, $l_bonus;
	global $expirehalfleech_torrent, $expirefree_torrent, $expiretwoup_torrent, $expiretwoupfree_torrent, $expiretwouphalfleech_torrent, $expirethirtypercentleech_torrent, $expirenormal_torrent, $hotdays_torrent, $hotseeder_torrent,$halfleechbecome_torrent,$freebecome_torrent,$twoupbecome_torrent,$twoupfreebecome_torrent, $twouphalfleechbecome_torrent, $thirtypercentleechbecome_torrent, $normalbecome_torrent, $deldeadtorrent_torrent;
	global $neverdelete_account, $neverdeletepacked_account, $deletepacked_account, $deleteunpacked_account, $deletenotransfer_account, $deletenotransfertwo_account, $deletepeasant_account, $psdlone_account, $psratioone_account, $psdltwo_account, $psratiotwo_account, $psdlthree_account, $psratiothree_account, $psdlfour_account, $psratiofour_account, $psdlfive_account, $psratiofive_account, $putime_account, $pudl_account, $puprratio_account, $puderatio_account, $eutime_account, $eudl_account, $euprratio_account, $euderatio_account, $cutime_account, $cudl_account, $cuprratio_account, $cuderatio_account, $iutime_account, $iudl_account, $iuprratio_account, $iuderatio_account, $vutime_account, $vudl_account, $vuprratio_account, $vuderatio_account, $exutime_account, $exudl_account, $exuprratio_account, $exuderatio_account, $uutime_account, $uudl_account, $uuprratio_account, $uuderatio_account, $nmtime_account, $nmdl_account, $nmprratio_account, $nmderatio_account, $getInvitesByPromotion_class;
	global $enablenoad_advertisement, $noad_advertisement;
	global $Cache;
	global $rootpath;

	require_once($rootpath . '/lang/_target/lang_cleanup.php');

	set_time_limit(0);
	ignore_user_abort(1);
	$now = time();

//Priority Class 1: cleanup every 15 mins
//2.update peer status
	$deadtime = deadtime();
	$deadtime = date("Y-m-d H:i:s",$deadtime);
	sql_query("DELETE FROM peers WHERE last_action < ".sqlesc($deadtime)) or sqlerr(__FILE__, __LINE__);
	if ($printProgress) {
		printProgress('update peer status');
	}
//11.calculate seeding bonus
	$res = sql_query("SELECT DISTINCT userid FROM peers WHERE seeder = 'yes'") or sqlerr(__FILE__, __LINE__);
	if (mysql_num_rows($res) > 0)
	{
		$sqrtof2 = sqrt(2);
		$logofpointone = log(0.1);
		$valueone = $logofpointone / $tzero_bonus;
		$pi = 3.141592653589793;
		$valuetwo = $bzero_bonus * ( 2 / $pi);
		$valuethree = $logofpointone / ($nzero_bonus - 1);
		$timenow = TIMENOW;
		$sectoweek = 7*24*60*60;
		while ($arr = mysql_fetch_assoc($res))	//loop for different users
		{
			$A = 0;
			$count = 0;
			$all_bonus = 0;
			$torrentres = sql_query("select torrents.added, torrents.size, torrents.seeders from torrents LEFT JOIN peers ON peers.torrent = torrents.id WHERE peers.userid = $arr[userid] AND peers.seeder ='yes'")  or sqlerr(__FILE__, __LINE__);
			while ($torrent = mysql_fetch_array($torrentres))
			{
				$weeks_alive = ($timenow - strtotime($torrent[added])) / $sectoweek;
				$gb_size = $torrent[size] / 1073741824;
				$temp = (1 - exp($valueone * $weeks_alive)) * $gb_size * (1 + $sqrtof2 * exp($valuethree * ($torrent[seeders] - 1)));
				$A += $temp;
				$count++;
			}
			if ($count > $maxseeding_bonus)
				$count = $maxseeding_bonus;
			$all_bonus = ($valuetwo * atan($A / $l_bonus) + ($perseeding_bonus * $count)) / (3600 / $autoclean_interval_one);
			$is_donor = get_single_value("users","donor","WHERE id=".$arr['userid']);
			if ($is_donor == 'yes' && $donortimes_bonus > 0)
				$all_bonus = $all_bonus * $donortimes_bonus;
			KPS("+",$all_bonus,$arr["userid"]);
		}
	}
	if ($printProgress) {
		printProgress('calculate seeding bonus');
	}

//Priority Class 2: cleanup every 30 mins
	$res = sql_query("SELECT value_u FROM avps WHERE arg = 'lastcleantime2'");
	$row = mysql_fetch_array($res);
	if (!$row) {
		sql_query("INSERT INTO avps (arg, value_u) VALUES ('lastcleantime2',".sqlesc($now).")") or sqlerr(__FILE__, __LINE__);
		return;
	}
	$ts = $row[0];
	if ($ts + $autoclean_interval_two > $now && !$forceAll) {
		return 'Cleanup ends at Priority Class 1';
	} else {
		sql_query("UPDATE avps SET value_u = ".sqlesc($now)." WHERE arg='lastcleantime2'") or sqlerr(__FILE__, __LINE__);
	}

	//2.5.update torrents' visibility
	$deadtime = deadtime() - $max_dead_torrent_time;
	sql_query("UPDATE torrents SET visible='no' WHERE visible='yes' AND last_action < FROM_UNIXTIME($deadtime) AND seeders=0") or sqlerr(__FILE__, __LINE__);
	if ($printProgress) {
		printProgress("update torrents' visibility");
	}
//Priority Class 3: cleanup every 60 mins
	$res = sql_query("SELECT value_u FROM avps WHERE arg = 'lastcleantime3'");
	$row = mysql_fetch_array($res);
	if (!$row) {
		sql_query("INSERT INTO avps (arg, value_u) VALUES ('lastcleantime3',$now)") or sqlerr(__FILE__, __LINE__);
		return;
	}
	$ts = $row[0];
	if ($ts + $autoclean_interval_three > $now && !$forceAll) {
		return 'Cleanup ends at Priority Class 2';
	} else {
		sql_query("UPDATE avps SET value_u = ".sqlesc($now)." WHERE arg='lastcleantime3'") or sqlerr(__FILE__, __LINE__);
	}

	//4.update count of seeders, leechers, comments for torrents
	$torrents = array();
	$res = sql_query("SELECT torrent, seeder, COUNT(*) AS c FROM peers GROUP BY torrent, seeder") or sqlerr(__FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($res)) {
		if ($row["seeder"] == "yes")
		$key = "seeders";
		else
		$key = "leechers";
		$torrents[$row["torrent"]][$key] = $row["c"];
	}

	$res = sql_query("SELECT torrent, COUNT(*) AS c FROM comments GROUP BY torrent") or sqlerr(__FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($res)) {
		$torrents[$row["torrent"]]["comments"] = $row["c"];
	}

	$fields = explode(":", "comments:leechers:seeders");
	$res = sql_query("SELECT id, seeders, leechers, comments FROM torrents") or sqlerr(__FILE__, __LINE__);
	while ($row = mysql_fetch_assoc($res)) {
		$id = $row["id"];
		$torr = $torrents[$id];
		foreach ($fields as $field) {
			if (!isset($torr[$field]))
			$torr[$field] = 0;
		}
		$update = array();
		foreach ($fields as $field) {
			if ($torr[$field] != $row[$field])
			$update[] = "$field = " . $torr[$field];
		}
		if (count($update))
		sql_query("UPDATE torrents SET " . implode(",", $update) . " WHERE id = $id") or sqlerr(__FILE__, __LINE__);
	}
	if ($printProgress) {
		printProgress("update count of seeders, leechers, comments for torrents");
	}

	//set no-advertisement-by-bonus time out
	sql_query("UPDATE users SET noad='no' WHERE noaduntil < ".sqlesc(date("Y-m-d H:i:s")).($enablenoad_advertisement == 'yes' ? " AND class < ".sqlesc($noad_advertisement) : ""));
	if ($printProgress) {
		printProgress("set no-advertisement-by-bonus time out");
	}
	//12. update forum post/topic count
	$forums = sql_query("select id from forums") or sqlerr(__FILE__, __LINE__);
	while ($forum = mysql_fetch_assoc($forums))
	{
		$postcount = 0;
		$topiccount = 0;
		$topics = sql_query("select id from topics where forumid=$forum[id]") or sqlerr(__FILE__, __LINE__);
		while ($topic = mysql_fetch_assoc($topics))
		{
			$res = sql_query("select count(*) from posts where topicid=$topic[id]") or sqlerr(__FILE__, __LINE__);
			$arr = mysql_fetch_row($res);
			$postcount += $arr[0];
			++$topiccount;
		}
		sql_query("update forums set postcount=$postcount, topiccount=$topiccount where id=$forum[id]") or sqlerr(__FILE__, __LINE__);
	}
	$Cache->delete_value('forums_list');
	if ($printProgress) {
		printProgress("update forum post/topic count");
	}
	//14.cleanup offers
	//Delete offers if not voted on after some time
	if($offervotetimeout_main){
		$secs = (int)$offervotetimeout_main;
		$dt = sqlesc(date("Y-m-d H:i:s",(TIMENOW - ($offervotetimeout_main))));
		$res = sql_query("SELECT id, name FROM offers WHERE added < $dt AND allowed <> 'allowed'") or sqlerr(__FILE__, __LINE__);
		while($arr = mysql_fetch_assoc($res)){
		sql_query("DELETE FROM offers WHERE id=$arr[id]") or sqlerr(__FILE__, __LINE__);
		sql_query("DELETE FROM offervotes WHERE offerid=$arr[id]") or sqlerr(__FILE__, __LINE__);
		sql_query("DELETE FROM comments WHERE offer=$arr[id]") or sqlerr(__FILE__, __LINE__);
		write_log("Offer $arr[id] ($arr[name]) was deleted by system (vote timeout)",'normal');
		}
	}
	if ($printProgress) {
		printProgress("delete offers if not voted on after some time");
	}

	//Delete offers if not uploaded after being voted on for some time.
	if($offeruptimeout_main){
		$secs = (int)$offeruptimeout_main;
		$dt = sqlesc(date("Y-m-d H:i:s",(TIMENOW - ($secs))));
		$res = sql_query("SELECT id, name FROM offers WHERE allowedtime < $dt AND allowed = 'allowed'") or sqlerr(__FILE__, __LINE__);
		while($arr = mysql_fetch_assoc($res)){
		sql_query("DELETE FROM offers WHERE id=$arr[id]") or sqlerr(__FILE__, __LINE__);
		sql_query("DELETE FROM offervotes WHERE offerid=$arr[id]") or sqlerr(__FILE__, __LINE__);
		sql_query("DELETE FROM comments WHERE offer=$arr[id]") or sqlerr(__FILE__, __LINE__);
		write_log("Offer $arr[id] ($arr[name]) was deleted by system (upload timeout)",'normal');
		}
	}
	if ($printProgress) {
		printProgress("delete offers if not uploaded after being voted on for some time.");
	}

	//15.cleanup torrents
	//Start: expire torrent promotion
function torrent_promotion_expire($days, $type = 2, $targettype = 1){
	$secs = (int)($days * 86400); //XX days
	$dt = sqlesc(date("Y-m-d H:i:s",(TIMENOW - ($secs))));
	$res = sql_query("SELECT id, name FROM torrents WHERE added < $dt AND sp_state = ".sqlesc($type).' AND promotion_time_type=0') or sqlerr(__FILE__, __LINE__);
	switch($targettype)
	{
		case 1: //normal
		{
			$sp_state = 1;
			$become = "normal";
			break;
		}
		case 2: //Free
		{
			$sp_state = 2;
			$become = "Free";
			break;
		}
		case 3: //2X
		{
			$sp_state = 3;
			$become = "2X";
			break;
		}
		case 4: //2X Free
		{
			$sp_state = 4;
			$become = "2X Free";
			break;
		}
		case 5: //Half Leech
		{
			$sp_state = 5;
			$become = "50%";
			break;
		}
		case 6: //2X Half Leech
		{
			$sp_state = 6;
			$become = "2X 50%";
			break;
		}
		default: //normal
		{
			$sp_state = 1;
			$become = "normal";
			break;
		}
	}
	while($arr = mysql_fetch_assoc($res)){
		sql_query("UPDATE torrents SET sp_state = ".sqlesc($sp_state)." WHERE id=$arr[id]") or sqlerr(__FILE__, __LINE__);
		if ($sp_state == 1)
			write_log("Torrent $arr[id] ($arr[name]) is no longer on promotion (time expired)",'normal');
		else write_log("Promotion type for torrent $arr[id] ($arr[name]) is changed to ".$become." (time expired)",'normal');
	}
}
	if ($expirehalfleech_torrent)
		torrent_promotion_expire($expirehalfleech_torrent, 5, $halfleechbecome_torrent);
	if ($expirefree_torrent)
		torrent_promotion_expire($expirefree_torrent, 2, $freebecome_torrent);
	if ($expiretwoup_torrent)
		torrent_promotion_expire($expiretwoup_torrent, 3, $twoupbecome_torrent);
	if ($expiretwoupfree_torrent)
		torrent_promotion_expire($expiretwoupfree_torrent, 4, $twoupfreebecome_torrent);
	if ($expiretwouphalfleech_torrent)
		torrent_promotion_expire($expiretwouphalfleech_torrent, 6, $twouphalfleechbecome_torrent);
	if ($expirethirtypercentleech_torrent)
		torrent_promotion_expire($expirethirtypercentleech_torrent, 7, $thirtypercentleechbecome_torrent);
	if ($expirenormal_torrent)
		torrent_promotion_expire($expirenormal_torrent, 1, $normalbecome_torrent);

	//expire individual torrent promotion
	sql_query("UPDATE torrents SET sp_state = 1, promotion_time_type=0, promotion_until='0000-00-00 00:00:00' WHERE promotion_time_type=2 AND promotion_until < ".sqlesc(date("Y-m-d H:i:s",TIMENOW))) or sqlerr(__FILE__, __LINE__);

	//End: expire torrent promotion
	if ($printProgress) {
		printProgress("expire torrent promotion");
	}
	//automatically pick hot
	if ($hotdays_torrent)
	{
		$secs = (int)($hotdays_torrent * 86400); //XX days
		$dt = sqlesc(date("Y-m-d H:i:s",(TIMENOW - ($secs))));
		sql_query("UPDATE torrents SET picktype = 'hot' WHERE added > $dt AND picktype = 'normal' AND seeders > ".sqlesc($hotseeder_torrent)) or sqlerr(__FILE__, __LINE__);
	}
	if ($printProgress) {
		printProgress("automatically pick hot");
	}

//Priority Class 4: cleanup every 24 hours
	$res = sql_query("SELECT value_u FROM avps WHERE arg = 'lastcleantime4'");
	$row = mysql_fetch_array($res);
	if (!$row) {
		sql_query("INSERT INTO avps (arg, value_u) VALUES ('lastcleantime4',$now)") or sqlerr(__FILE__, __LINE__);
		return;
	}
	$ts = $row[0];
	if ($ts + $autoclean_interval_four > $now && !$forceAll) {
		return 'Cleanup ends at Priority Class 3';
	} else {
		sql_query("UPDATE avps SET value_u = ".sqlesc($now)." WHERE arg='lastcleantime4'") or sqlerr(__FILE__, __LINE__);
	}

	//3.delete unconfirmed accounts
	$deadtime = time() - $signup_timeout;
	sql_query("DELETE FROM users WHERE status = 'pending' AND added < FROM_UNIXTIME($deadtime) AND last_login < FROM_UNIXTIME($deadtime) AND last_access < FROM_UNIXTIME($deadtime)") or sqlerr(__FILE__, __LINE__);
	if ($printProgress) {
		printProgress("delete unconfirmed accounts");
	}

	//5.delete old login attempts
	$secs = 12*60*60; // Delete failed login attempts per half day.
	$dt = sqlesc(date("Y-m-d H:i:s",(TIMENOW - $secs))); // calculate date.
	sql_query("DELETE FROM loginattempts WHERE banned='no' AND added < $dt") or sqlerr(__FILE__, __LINE__);
	if ($printProgress) {
		printProgress("delete old login attempts");
	}

	//6.delete old invite codes
	$secs = $invite_timeout*24*60*60; // when?
	$dt = sqlesc(date("Y-m-d H:i:s",(TIMENOW - $secs))); // calculate date.
	sql_query("DELETE FROM invites WHERE time_invited < $dt") or sqlerr(__FILE__, __LINE__);
	if ($printProgress) {
		printProgress("delete old invite codes");
	}

	//7.delete regimage codes
	sql_query("TRUNCATE TABLE `regimages`") or sqlerr(__FILE__, __LINE__);
	if ($printProgress) {
		printProgress("delete regimage codes");
	}
	//10.clean up user accounts
	// make sure VIP or above never get deleted
	$neverdelete_account = ($neverdelete_account <= UC_VIP ? $neverdelete_account : UC_VIP);

	//delete inactive user accounts, no transfer. Alt. 1: last access time
	if ($deletenotransfer_account){
		$secs = $deletenotransfer_account*24*60*60;
		$dt = sqlesc(date("Y-m-d H:i:s",(TIMENOW - $secs)));
		$maxclass = $neverdelete_account;
		sql_query("DELETE FROM users WHERE parked='no' AND status='confirmed' AND class < $maxclass AND last_access < $dt AND (uploaded = 0 || uploaded = ".sqlesc($iniupload_main).") AND downloaded = 0") or sqlerr(__FILE__, __LINE__);
	}
	if ($printProgress) {
		printProgress("delete inactive user accounts, no transfer. Alt. 1: last access time");
	}

	//delete inactive user accounts, no transfer. Alt. 2: registering time
	if ($deletenotransfertwo_account){
		$secs = $deletenotransfertwo_account*24*60*60;
		$dt = sqlesc(date("Y-m-d H:i:s",(TIMENOW - $secs)));
		$maxclass = $neverdelete_account;
		sql_query("DELETE FROM users WHERE parked='no' AND status='confirmed' AND class < $maxclass AND added < $dt AND (uploaded = 0 || uploaded = ".sqlesc($iniupload_main).") AND downloaded = 0") or sqlerr(__FILE__, __LINE__);
	}
	if ($printProgress) {
		printProgress("delete inactive user accounts, no transfer. Alt. 2: registering time");
	}

	//delete inactive user accounts, not parked
	if ($deleteunpacked_account){
		$secs = $deleteunpacked_account*24*60*60;
		$dt = sqlesc(date("Y-m-d H:i:s",(TIMENOW - $secs)));
		$maxclass = $neverdelete_account;
	sql_query("DELETE FROM users WHERE parked='no' AND status='confirmed' AND class < $maxclass AND last_access < $dt") or sqlerr(__FILE__, __LINE__);
	}
	if ($printProgress) {
		printProgress("delete inactive user accounts, not parked");
	}

	//delete parked user accounts, parked
	if ($deletepacked_account){
		$secs = $deletepacked_account*24*60*60;
		$dt = sqlesc(date("Y-m-d H:i:s",(TIMENOW - $secs)));
		$maxclass = $neverdeletepacked_account;
		sql_query("DELETE FROM users WHERE parked='yes' AND status='confirmed' AND class < $maxclass AND last_access < $dt") or sqlerr(__FILE__, __LINE__);
	}
	if ($printProgress) {
		printProgress("delete parked user accounts, parked");
	}

	//remove VIP status if time's up
	$res = sql_query("SELECT id, modcomment FROM users WHERE vip_added='yes' AND vip_until < NOW()") or sqlerr(__FILE__, __LINE__);
	if (mysql_num_rows($res) > 0)
	{
		while ($arr = mysql_fetch_assoc($res))
		{
			$dt = sqlesc(date("Y-m-d H:i:s"));
			$subject = sqlesc($lang_cleanup_target[get_user_lang($arr[id])]['msg_vip_status_removed']); 
			$msg = sqlesc($lang_cleanup_target[get_user_lang($arr[id])]['msg_vip_status_removed_body']);
			///---AUTOSYSTEM MODCOMMENT---//
			$modcomment = htmlspecialchars($arr["modcomment"]);
			$modcomment =  date("Y-m-d") . " - VIP status removed by - AutoSystem.\n". $modcomment;
			$modcom =  sqlesc($modcomment);
			///---end
			sql_query("UPDATE users SET class = '1', vip_added = 'no', vip_until = '0000-00-00 00:00:00', modcomment = $modcom WHERE id = $arr[id]") or sqlerr(__FILE__, __LINE__);
			sql_query("INSERT INTO messages (sender, receiver, added, msg, subject) VALUES(0, $arr[id], $dt, $msg, $subject)") or sqlerr(__FILE__, __LINE__);
		}
	}
	if ($printProgress) {
		printProgress("remove VIP status if time's up");
	}

	// promote peasant back to user
function peasant_to_user($down_floor_gb, $down_roof_gb, $minratio){
	global $lang_cleanup_target;

	if ($down_floor_gb){
		$downlimit_floor = $down_floor_gb*1024*1024*1024;
		$downlimit_roof = $down_roof_gb*1024*1024*1024;
		$res = sql_query("SELECT id FROM users WHERE class = 0 AND downloaded >= $downlimit_floor ".($downlimit_roof > $down_floor_gb ? " AND downloaded < $downlimit_roof" : "")." AND uploaded / downloaded >= $minratio") or sqlerr(__FILE__, __LINE__);
		if (mysql_num_rows($res) > 0)
		{
			$dt = sqlesc(date("Y-m-d H:i:s"));
			while ($arr = mysql_fetch_assoc($res))
			{
				$subject = sqlesc($lang_cleanup_target[get_user_lang($arr[id])]['msg_low_ratio_warning_removed']);
				$msg = sqlesc($lang_cleanup_target[get_user_lang($arr[id])]['msg_your_ratio_warning_removed']);
				writecomment($arr[id],"Leech Warning removed by System.");
				sql_query("UPDATE users SET class = 1, leechwarn = 'no', leechwarnuntil = '0000-00-00 00:00:00' WHERE id = $arr[id]") or sqlerr(__FILE__, __LINE__);
				sql_query("INSERT INTO messages (sender, receiver, added, subject, msg) VALUES(0, $arr[id], $dt, $subject, $msg)") or sqlerr(__FILE__, __LINE__);
			}
		}
	}
}

	peasant_to_user($psdlfive_account,0, $psratiofive_account);
	peasant_to_user($psdlfour_account,$psdlfive_account, $psratiofour_account);
	peasant_to_user($psdlthree_account,$psdlfour_account, $psratiothree_account);
	peasant_to_user($psdltwo_account,$psdlthree_account, $psratiotwo_account);
	peasant_to_user($psdlone_account,$psdltwo_account, $psratioone_account);
	if ($printProgress) {
		printProgress("promote peasant back to user");
	}
	//end promote peasant back to user

	// start promotion
function promotion($class, $down_floor_gb, $minratio, $time_week, $addinvite = 0){
	global $lang_cleanup_target;
	$oriclass = $class - 1;

	if ($down_floor_gb){
		$limit = $down_floor_gb*1024*1024*1024;
		$maxdt = date("Y-m-d H:i:s",(TIMENOW - 86400*7*$time_week));
		$res = sql_query("SELECT id, max_class_once FROM users WHERE class = $oriclass AND downloaded >= $limit AND uploaded / downloaded >= $minratio AND added < ".sqlesc($maxdt)) or sqlerr(__FILE__, __LINE__);
		if (mysql_num_rows($res) > 0)
		{
			$dt = sqlesc(date("Y-m-d H:i:s"));
			while ($arr = mysql_fetch_assoc($res))
			{
				$subject = sqlesc($lang_cleanup_target[get_user_lang($arr[id])]['msg_promoted_to'].get_user_class_name($class,false,false,false));
				$msg = sqlesc($lang_cleanup_target[get_user_lang($arr[id])]['msg_now_you_are'].get_user_class_name($class,false,false,false).$lang_cleanup_target[get_user_lang($arr[id])]['msg_see_faq']);
				if($class<=$arr[max_class_once])
					sql_query("UPDATE users SET class = $class WHERE id = $arr[id]") or sqlerr(__FILE__, __LINE__);
				else
					sql_query("UPDATE users SET class = $class, max_class_once=$class, invites=invites+$addinvite WHERE id = $arr[id]") or sqlerr(__FILE__, __LINE__);

				sql_query("INSERT INTO messages (sender, receiver, added, subject, msg) VALUES(0, $arr[id], $dt, $subject, $msg)") or sqlerr(__FILE__, __LINE__);
			}
		}
	}
}
		//do not change the ascending order
	promotion(UC_POWER_USER, $pudl_account, $puprratio_account, $putime_account, $getInvitesByPromotion_class[UC_POWER_USER]);
	promotion(UC_ELITE_USER, $eudl_account, $euprratio_account, $eutime_account, $getInvitesByPromotion_class[UC_ELITE_USER]);
	promotion(UC_CRAZY_USER, $cudl_account, $cuprratio_account, $cutime_account, $getInvitesByPromotion_class[UC_CRAZY_USER]);
	promotion(UC_INSANE_USER, $iudl_account, $iuprratio_account, $iutime_account, $getInvitesByPromotion_class[UC_INSANE_USER]);
	promotion(UC_VETERAN_USER, $vudl_account, $vuprratio_account, $vutime_account, $getInvitesByPromotion_class[UC_VETERAN_USER]);
	promotion(UC_EXTREME_USER, $exudl_account, $exuprratio_account, $exutime_account, $getInvitesByPromotion_class[UC_EXTREME_USER]);
	promotion(UC_ULTIMATE_USER, $uudl_account, $uuprratio_account, $uutime_account, $getInvitesByPromotion_class[UC_ULTIMATE_USER]);
	promotion(UC_NEXUS_MASTER, $nmdl_account, $nmprratio_account, $nmtime_account, $getInvitesByPromotion_class[UC_NEXUS_MASTER]);
	// end promotion
	if ($printProgress) {
		printProgress("promote users to other classes");
	}

	// start demotion
function demotion($class,$deratio){
	global $lang_cleanup_target;

	$newclass = $class - 1;
	$res = sql_query("SELECT id FROM users WHERE class = $class AND uploaded / downloaded < $deratio") or sqlerr(__FILE__, __LINE__);
	if (mysql_num_rows($res) > 0)
	{
		$dt = sqlesc(date("Y-m-d H:i:s"));
		while ($arr = mysql_fetch_assoc($res))
		{
			$subject = $lang_cleanup_target[get_user_lang($arr[id])]['msg_demoted_to'].get_user_class_name($newclass,false,false,false);
			$msg = $lang_cleanup_target[get_user_lang($arr[id])]['msg_demoted_from'].get_user_class_name($class,false,false,false).$lang_cleanup_target[get_user_lang($arr[id])]['msg_to'].get_user_class_name($newclass,false,false,false).$lang_cleanup_target[get_user_lang($arr[id])]['msg_because_ratio_drop_below'].$deratio.".\n";
			sql_query("UPDATE users SET class = $newclass WHERE id = $arr[id]") or sqlerr(__FILE__, __LINE__);
			sql_query("INSERT INTO messages (sender, receiver, added, subject, msg) VALUES(0, $arr[id], $dt, ".sqlesc($subject).", ".sqlesc($msg).")") or sqlerr(__FILE__, __LINE__);
		}
	}
}
		//do not change the descending order
	demotion(UC_NEXUS_MASTER,$nmderatio_account);
	demotion(UC_ULTIMATE_USER,$uuderatio_account);
	demotion(UC_EXTREME_USER,$exuderatio_account);
	demotion(UC_VETERAN_USER,$vuderatio_account);
	demotion(UC_INSANE_USER,$iuderatio_account);
	demotion(UC_CRAZY_USER,$cuderatio_account);
	demotion(UC_ELITE_USER,$euderatio_account);
	demotion(UC_POWER_USER,$puderatio_account);
	if ($printProgress) {
		printProgress("demote users to other classes");
	}
	// end demotion

	// start demote users to peasant
function user_to_peasant($down_floor_gb, $minratio){
	global $lang_cleanup_target;
	global $deletepeasant_account;

	$length = $deletepeasant_account*86400; // warn users until xxx days
	$until = date("Y-m-d H:i:s",(TIMENOW + $length));
	$downlimit_floor = $down_floor_gb*1024*1024*1024;
	$res = sql_query("SELECT id FROM users WHERE class = 1 AND downloaded > $downlimit_floor AND uploaded / downloaded < $minratio") or sqlerr(__FILE__, __LINE__);
	if (mysql_num_rows($res) > 0)
	{
		$dt = sqlesc(date("Y-m-d H:i:s"));
		while ($arr = mysql_fetch_assoc($res))
		{
			$subject = $lang_cleanup_target[get_user_lang($arr[id])]['msg_demoted_to'].get_user_class_name(UC_PEASANT,false,false,false);
			$msg = $lang_cleanup_target[get_user_lang($arr[id])]['msg_must_fix_ratio_within'].$deletepeasant_account.$lang_cleanup_target[get_user_lang($arr[id])]['msg_days_or_get_banned'];
			writecomment($arr[id],"Leech Warned by System - Low Ratio.");
			sql_query("UPDATE users SET class = 0 , leechwarn = 'yes', leechwarnuntil = ".sqlesc($until)." WHERE id = $arr[id]") or sqlerr(__FILE__, __LINE__);
			sql_query("INSERT INTO messages (sender, receiver, added, subject, msg) VALUES(0, $arr[id], $dt, ".sqlesc($subject).", ".sqlesc($msg).")") or sqlerr(__FILE__, __LINE__);
		}
	}
}

	user_to_peasant($psdlone_account, $psratioone_account);
	user_to_peasant($psdltwo_account, $psratiotwo_account);
	user_to_peasant($psdlthree_account, $psratiothree_account);
	user_to_peasant($psdlfour_account, $psratiofour_account);
	user_to_peasant($psdlfive_account, $psratiofive_account);
	if ($printProgress) {
		printProgress("demote Users to peasant");
	}
	// end Users to Peasant

	//ban users with leechwarning expired
	$dt = sqlesc(date("Y-m-d H:i:s")); // take date time
	$res = sql_query("SELECT id FROM users WHERE enabled = 'yes' AND leechwarn = 'yes' AND leechwarnuntil < $dt") or sqlerr(__FILE__, __LINE__);

	if (mysql_num_rows($res) > 0)
	{
		while ($arr = mysql_fetch_assoc($res))
		{
			writecomment($arr[id],"Banned by System because of Leech Warning expired.");

			sql_query("UPDATE users SET enabled = 'no', leechwarnuntil = '0000-00-00 00:00:00' WHERE id = $arr[id]") or sqlerr(__FILE__, __LINE__);
		}
	}
	if ($printProgress) {
		printProgress("ban users with leechwarning expired");
	}

	//Remove warning of users
	$dt = sqlesc(date("Y-m-d H:i:s")); // take date time
	$res = sql_query("SELECT id FROM users WHERE enabled = 'yes' AND warned = 'yes' AND warneduntil < $dt") or sqlerr(__FILE__, __LINE__);

	if (mysql_num_rows($res) > 0)
	{
		while ($arr = mysql_fetch_assoc($res))
		{
			$subject = $lang_cleanup_target[get_user_lang($arr[id])]['msg_warning_removed'];
			$msg = $lang_cleanup_target[get_user_lang($arr[id])]['msg_your_warning_removed'];
			writecomment($arr[id],"Warning removed by System.");
			sql_query("UPDATE users SET warned = 'no', warneduntil = '0000-00-00 00:00:00' WHERE id = $arr[id]") or sqlerr(__FILE__, __LINE__);
			sql_query("INSERT INTO messages (sender, receiver, added, subject, msg) VALUES(0, $arr[id], $dt, ".sqlesc($subject).", ".sqlesc($msg).")") or sqlerr(__FILE__, __LINE__);
		}
	}
	if ($printProgress) {
		printProgress("remove warning of users");
	}

	//17.update total seeding and leeching time of users
	$res = sql_query("SELECT * FROM users") or sqlerr(__FILE__, __LINE__);
	while($arr = mysql_fetch_assoc($res))
	{
		//die("s" . $arr['id']);
		$res2 = sql_query("SELECT SUM(seedtime) as st, SUM(leechtime) as lt FROM snatched where userid = " . $arr['id'] . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
		$arr2 = mysql_fetch_assoc($res2) or sqlerr(__FILE__, __LINE__);
		
		//die("ss" . $arr2['st']);
		//die("sss" . "UPDATE users SET seedtime = " . $arr2['st'] . ", leechtime = " . $arr2['lt'] . " WHERE id = " . $arr['id']);
		
		sql_query("UPDATE users SET seedtime = " . intval($arr2['st']) . ", leechtime = " . intval($arr2['lt']) . " WHERE id = " . $arr['id']) or sqlerr(__FILE__, __LINE__);
	}
	if ($printProgress) {
		printProgress("update total seeding and leeching time of users");
	}

	// delete torrents that have been dead for a long time
	if ($deldeadtorrent_torrent > 0){
		$length = $deldeadtorrent_torrent*86400;
		$until = date("Y-m-d H:i:s",(TIMENOW - $length));
		$dt = sqlesc(date("Y-m-d H:i:s"));
		$res = sql_query("SELECT id, name, owner FROM torrents WHERE visible = 'no' AND last_action < ".sqlesc($until)." AND seeders = 0 AND leechers = 0") or sqlerr(__FILE__, __LINE__);
		while($arr = mysql_fetch_assoc($res))
		{
			deletetorrent($arr['id']);
			$subject = $lang_cleanup_target[get_user_lang($arr[owner])]['msg_your_torrent_deleted'];
			$msg = $lang_cleanup_target[get_user_lang($arr[owner])]['msg_your_torrent']."[i]".$arr['name']."[/i]".$lang_cleanup_target[get_user_lang($arr[owner])]['msg_was_deleted_because_dead'];
			sql_query("INSERT INTO messages (sender, receiver, added, subject, msg) VALUES(0, $arr[owner], $dt, ".sqlesc($subject).", ".sqlesc($msg).")") or sqlerr(__FILE__, __LINE__);
			write_log("Torrent $arr[id] ($arr[name]) is deleted by system because of being dead for a long time.",'normal');
		}
	}
	if ($printProgress) {
		printProgress("delete torrents that have been dead for a long time");
	}

//Priority Class 5: cleanup every 15 days
	$res = sql_query("SELECT value_u FROM avps WHERE arg = 'lastcleantime5'");
	$row = mysql_fetch_array($res);
	if (!$row) {
		sql_query("INSERT INTO avps (arg, value_u) VALUES ('lastcleantime5',$now)") or sqlerr(__FILE__, __LINE__);
		return;
	}
	$ts = $row[0];
	if ($ts + $autoclean_interval_five > $now && !$forceAll) {
		return 'Cleanup ends at Priority Class 4';
	} else {
		sql_query("UPDATE avps SET value_u = ".sqlesc($now)." WHERE arg='lastcleantime5'") or sqlerr(__FILE__, __LINE__);
	}

	//update clients' popularity
	$res = sql_query("SELECT id FROM agent_allowed_family");
	while($row = mysql_fetch_array($res)){
		$count = get_row_count("users","WHERE clientselect=".sqlesc($row['id']));
		sql_query("UPDATE agent_allowed_family SET hits=".sqlesc($count)." WHERE id=".sqlesc($row['id']));
	}
	if ($printProgress) {
		printProgress("update clients' popularity");
	}

	//delete old messages sent by system
	$length = 180*86400; //half a year
	$until = date("Y-m-d H:i:s",(TIMENOW - $length));
	sql_query("DELETE FROM messages WHERE sender = 0 AND added < ".sqlesc($until));
	if ($printProgress) {
		printProgress("delete old messages sent by system");
	}

	//delete old readpost records
	$length = 180*86400; //half a year
	$until = date("Y-m-d H:i:s",(TIMENOW - $length));
	$postIdHalfYearAgo = get_single_value('posts', 'id', 'WHERE added < ' . sqlesc($until).' ORDER BY added DESC');
	if ($postIdHalfYearAgo) {
		sql_query("UPDATE users SET last_catchup = ".sqlesc($postIdHalfYearAgo)." WHERE last_catchup < ".sqlesc($postIdHalfYearAgo));
		sql_query("DELETE FROM readposts WHERE lastpostread < ".sqlesc($postIdHalfYearAgo));
	}
	if ($printProgress) {
		printProgress("delete old readpost records");
	}

	//delete old ip log
	$length = 365*86400; //a year
	$until = date("Y-m-d H:i:s",(TIMENOW - $length));
	sql_query("DELETE FROM iplog WHERE access < ".sqlesc($until));
	if ($printProgress) {
		printProgress("delete old ip log");
	}

	//delete old general log
	$secs = 365*86400; //a year
	$until = date("Y-m-d H:i:s",(TIMENOW - $length));
	sql_query("DELETE FROM sitelog WHERE added < ".sqlesc($until)) or sqlerr(__FILE__, __LINE__);
	if ($printProgress) {
		printProgress("delete old general log");
	}

	//1.delete torrents that doesn't exist any more
	do {
		$res = sql_query("SELECT id FROM torrents") or sqlerr(__FILE__, __LINE__);
		$ar = array();
		while ($row = mysql_fetch_array($res)) {
			$id = $row[0];
			$ar[$id] = 1;
		}

		if (!count($ar))
		break;

		$dp = @opendir($torrent_dir);
		if (!$dp)
		break;

		$ar2 = array();
		while (($file = readdir($dp)) !== false) {
			if (!preg_match('/^(\d+)\.torrent$/', $file, $m))
			continue;
			$id = $m[1];
			$ar2[$id] = 1;
			if (isset($ar[$id]) && $ar[$id])
			continue;
			$ff = $torrent_dir . "/$file";
			unlink($ff);
		}
		closedir($dp);

		if (!count($ar2))
		break;

		$delids = array();
		foreach (array_keys($ar) as $k) {
			if (isset($ar2[$k]) && $ar2[$k])
			continue;
			$delids[] = $k;
			unset($ar[$k]);
		}
		if (count($delids))
		sql_query("DELETE FROM torrents WHERE id IN (" . join(",", $delids) . ")") or sqlerr(__FILE__, __LINE__);

		$res = sql_query("SELECT torrent FROM peers GROUP BY torrent") or sqlerr(__FILE__, __LINE__);
		$delids = array();
		while ($row = mysql_fetch_array($res)) {
			$id = $row[0];
			if (isset($ar[$id]) && $ar[$id])
			continue;
			$delids[] = $id;
		}
		if (count($delids))
		sql_query("DELETE FROM peers WHERE torrent IN (" . join(",", $delids) . ")") or sqlerr(__FILE__, __LINE__);

		$res = sql_query("SELECT torrent FROM files GROUP BY torrent") or sqlerr(__FILE__, __LINE__);
		$delids = array();
		while ($row = mysql_fetch_array($res)) {
			$id = $row[0];
			if ($ar[$id])
			continue;
			$delids[] = $id;
		}
		if (count($delids))
		sql_query("DELETE FROM files WHERE torrent IN (" . join(",", $delids) . ")") or sqlerr(__FILE__, __LINE__);
	} while (0);
	if ($printProgress) {
		printProgress("delete torrents that doesn't exist any more");
	}

	//8.lock topics where last post was made more than x days ago
	$secs = 365*24*60*60;
	sql_query("UPDATE topics, posts SET topics.locked='yes' WHERE topics.lastpost = posts.id AND topics.sticky = 'no' AND UNIX_TIMESTAMP(posts.added) < ".TIMENOW." - $secs") or sqlerr(__FILE__, __LINE__);

	if ($printProgress) {
		printProgress("lock topics where last post was made more than x days ago");
	}

	//9.delete report items older than four week
	$secs = 4*7*24*60*60;
	$dt = sqlesc(date("Y-m-d H:i:s",(TIMENOW - $secs)));
	sql_query("DELETE FROM reports WHERE dealtwith=1 AND added < $dt") or sqlerr(__FILE__, __LINE__);
	if ($printProgress) {
		printProgress("delete report items older than four week");
	}
	return 'Full cleanup is done';
}
?>

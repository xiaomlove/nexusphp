<?php
# IMPORTANT: Do not edit below unless you know what you are doing!

use App\Enums\ModelEventEnum;
use App\Models\Message;
use Nexus\Database\NexusDB;

if(!defined('IN_TRACKER'))
die('Hacking attempt!');

function printProgress($msg) {
    $br = php_sapi_name() == 'cli' ? "\n" : "<br />";
 	echo sprintf("[%s] [%s] %s ... done!%s", date('Y-m-d H:i:s'), nexus()->getRequestId(), $msg, $br);
}

function torrent_promotion_expire($days, $type = 2, $targettype = 1){
	$secs = (int)($days * 86400); //XX days
	$dt = date("Y-m-d H:i:s",(TIMENOW - ($secs)));
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
	$rows = NexusDB::table('torrents')
		->where('added', '<', $dt)
		->where('sp_state', $type)
		->where('promotion_time_type', 0)
		->get(['id', 'name']);
	foreach ($rows as $arr) {
		$arr = (array) $arr;
		NexusDB::table('torrents')->where('id', $arr['id'])->update(['sp_state' => $sp_state]);
        publish_model_event(ModelEventEnum::TORRENT_UPDATED, $arr['id']);
		if ($sp_state == 1)
			write_log("Torrent {$arr['id']} ({$arr['name']}) is no longer on promotion (time expired)",'normal');
		else write_log("Promotion type for torrent {$arr['id']} ({$arr['name']}) is changed to ".$become." (time expired)",'normal');
	}
}

function torrent_promotion_individual_expire() {
    $rows = NexusDB::table('torrents')
        ->where('promotion_time_type', 2)
        ->where('promotion_until', '<', date("Y-m-d H:i:s"))
        ->get(['id']);
    foreach ($rows as $arr) {
        $arr = (array) $arr;
        NexusDB::table('torrents')->where('id', $arr['id'])->update([
            'sp_state' => 1,
            'promotion_time_type' => 0,
            'promotion_until' => null,
        ]);
        publish_model_event(ModelEventEnum::TORRENT_UPDATED, $arr['id']);
    }
}

function peasant_to_user($down_floor_gb, $down_roof_gb, $minratio){

	if ($down_floor_gb){
		$downlimit_floor = $down_floor_gb*1024*1024*1024;
		$downlimit_roof = $down_roof_gb*1024*1024*1024;
		$query = NexusDB::table('users')
			->where('class', 0)
			->where('downloaded', '>=', $downlimit_floor)
			->whereRaw('uploaded / downloaded >= ?', [$minratio]);
		if ($downlimit_roof > $down_floor_gb) {
			$query->where('downloaded', '<', $downlimit_roof);
		}
		$rows = $query->get(['id']);
		if (count($rows) > 0)
		{
			$dt = date("Y-m-d H:i:s");
			foreach ($rows as $arr)
			{
				$arr = (array) $arr;
                $locale = get_user_locale($arr['id']);
                $subject = nexus_trans("cleanup.msg_low_ratio_warning_removed", [], $locale);
                $msg = nexus_trans("cleanup.msg_your_ratio_warning_removed", [], $locale);
				writecomment($arr['id'],"Leech Warning removed by System.");
				NexusDB::table('users')->where('id', $arr['id'])->update([
					'class' => 1,
					'leechwarn' => 'no',
					'leechwarnuntil' => null,
				]);
				Message::add([
					'sender' => 0,
					'receiver' => $arr['id'],
					'added' => $dt,
					'subject' => $subject,
					'msg' => $msg,
				]);
                publish_model_event(ModelEventEnum::USER_UPDATED, $arr['id']);
			}
		}
	}
}

function promotion($class, $down_floor_gb, $minratio, $time_week, $addinvite = 0){
	$oriclass = $class - 1;

	if ($down_floor_gb){
		$limit = $down_floor_gb*1024*1024*1024;
		$maxdt = date("Y-m-d H:i:s",(TIMENOW - 86400*7*$time_week));
		$minSeedPoints = \App\Models\User::getMinSeedPoints($class);
		if ($minSeedPoints === false) {
		    throw new \RuntimeException("class: $class can't get min seed points.");
        }
		$rows = NexusDB::table('users')
			->where('class', $oriclass)
			->where('downloaded', '>=', $limit)
			->where('seed_points', '>=', $minSeedPoints)
			->whereRaw('uploaded / downloaded >= ?', [$minratio])
			->where('added', '<', $maxdt)
			->get(['id', 'max_class_once']);
		$matchUserCount = count($rows);
        do_log("class: $oriclass -> $class, match user count: $matchUserCount");
		if ($matchUserCount > 0)
		{
			$dt = date("Y-m-d H:i:s");
			foreach ($rows as $arr)
			{
				$arr = (array) $arr;
				$locale = get_user_locale($arr['id']);
                $subject = nexus_trans("cleanup.msg_promoted_to", [], $locale).get_user_class_name($class,false,false,false);
                $msg = nexus_trans("cleanup.msg_now_you_are", [], $locale).get_user_class_name($class,false,false,false).nexus_trans("cleanup.msg_see_faq", [], $locale);

                if($class <= $arr['max_class_once']) {
                    do_log(sprintf('user: %s upgrade to class: %s', $arr['id'], $class));
                    NexusDB::table('users')->where('id', $arr['id'])->update(['class' => $class]);
                } else {
                    do_log(sprintf('user: %s upgrade to class: %s, and add invites: %s', $arr['id'], $class, $addinvite));
                    NexusDB::table('users')->where('id', $arr['id'])->update([
                        'class' => $class,
                        'max_class_once' => $class,
                        'invites' => NexusDB::raw('invites + ' . (int) $addinvite),
                    ]);
                }
				Message::add([
					'sender' => 0,
					'receiver' => $arr['id'],
					'added' => $dt,
					'subject' => $subject,
					'msg' => $msg,
				]);
                publish_model_event(ModelEventEnum::USER_UPDATED, $arr['id']);
			}
		}
	}
}

function demotion($class,$deratio){
	$newclass = $class - 1;
	$rows = NexusDB::table('users')
		->where('class', $class)
		->whereRaw('uploaded < downloaded * ?', [$deratio])
		->get(['id']);
    $matchUserCount = count($rows);
    do_log("class: $class -> $newclass, deratio: $deratio, match user count: $matchUserCount");
    if ($matchUserCount > 0)
	{
		$dt = date("Y-m-d H:i:s");
		foreach ($rows as $arr)
		{
			$arr = (array) $arr;
			$locale = get_user_locale($arr['id']);
            $subject = nexus_trans("cleanup.msg_demoted_to", [], $locale).get_user_class_name($newclass,false,false,false);
            $msg = nexus_trans("cleanup.msg_demoted_from", [], $locale).get_user_class_name($class,false,false,false).nexus_trans("cleanup.msg_to", [], $locale).get_user_class_name($newclass,false,false,false).nexus_trans("cleanup.msg_because_ratio_drop_below", [], $locale).$deratio.".\n";

            NexusDB::table('users')->where('id', $arr['id'])->update(['class' => $newclass]);
			Message::add([
				'sender' => 0,
				'receiver' => $arr['id'],
				'added' => $dt,
				'subject' => $subject,
				'msg' => $msg,
			]);
            publish_model_event(ModelEventEnum::USER_UPDATED, $arr['id']);
		}
	}
}

function user_to_peasant($down_floor_gb, $minratio){
	global $deletepeasant_account;

	$length = $deletepeasant_account*86400; // warn users until xxx days
	$until = date("Y-m-d H:i:s",(TIMENOW + $length));
	$downlimit_floor = $down_floor_gb*1024*1024*1024;
	$rows = NexusDB::table('users')
		->where('class', 1)
		->where('downloaded', '>', $downlimit_floor)
		->whereRaw('uploaded / downloaded < ?', [$minratio])
		->get(['id']);
	if (count($rows) > 0)
	{
		$dt = date("Y-m-d H:i:s");
		foreach ($rows as $arr)
		{
			$arr = (array) $arr;
            $locale = get_user_locale($arr['id']);
            $subject = nexus_trans("cleanup.msg_demoted_to", [], $locale).get_user_class_name(UC_PEASANT,false,false,false);
            $msg = nexus_trans("cleanup.msg_must_fix_ratio_within", [], $locale).$deletepeasant_account.nexus_trans("cleanup.msg_days_or_get_banned", [], $locale);

            writecomment($arr['id'],"Leech Warned by System - Low Ratio.");
			NexusDB::table('users')->where('id', $arr['id'])->update([
				'class' => 0,
				'leechwarn' => 'yes',
				'leechwarnuntil' => $until,
			]);
			Message::add([
				'sender' => 0,
				'receiver' => $arr['id'],
				'added' => $dt,
				'subject' => $subject,
				'msg' => $msg,
			]);
            publish_model_event(ModelEventEnum::USER_UPDATED, $arr['id']);
		}
	}
}

function ban_user_with_leech_warning_expired()
{
    $dt = date("Y-m-d H:i:s"); // take date time
    // VIP or above and donated won't effect
    $results = \App\Models\User::query()
        ->where('class', '<', \App\Models\User::CLASS_VIP)
        ->where('donor', 'no')
        ->where('enabled', \App\Models\User::ENABLED_YES)
        ->where('leechwarn', 'yes')
        ->where('leechwarnuntil', '<', $dt)
        ->get(['id', 'username', 'lang']);
    if ($results->isEmpty()) {
        return [];
    }
    $results->load('language');
    $uidArr = [];
    $userBanLogData = [];
    foreach ($results as $user) {
        $uid = $user->id;
        $uidArr[] = $uid;
        $userBanLogData[] = [
            'uid' => $uid,
            'username' => $user->username,
            'reason' => nexus_trans('cleanup.ban_user_with_leech_warning_expired', [], $user->locale),
        ];
        writecomment($uid,"Banned by System because of Leech Warning expired.", $user->modcomment);
    }
    $update = [
        'enabled' => \App\Models\User::ENABLED_NO,
        //old version site this field NOT NULL DEFAULT '0000-00-00 00:00:00'
//        'leechwarnuntil' => null,
    ];
    \App\Models\User::query()->whereIn('id', $uidArr)->update($update);
    \App\Models\UserBanLog::query()->insert($userBanLogData);
    do_log("ban user: " . implode(', ', $uidArr));
    foreach ($uidArr as $uid) {
        publish_model_event(ModelEventEnum::USER_UPDATED, $uid);
    }
    return $uidArr;
}


function disable_user(\Illuminate\Database\Eloquent\Builder $query, $reasonKey)
{
    $results = $query->where('enabled', \App\Models\User::ENABLED_YES)->get(['id', 'username', 'lang']);
    if ($results->isEmpty()) {
        return [];
    }
    $results->load('language');
    $uidArr = [];
    $userBanLogData = [];
    $userModifyLogs = [];
    foreach ($results as $user) {
        $uid = $user->id;
        $enableCacheResult = \Nexus\Database\NexusDB::cache_get(\App\Models\User::getUserEnableLatelyCacheKey($uid));
        if ($enableCacheResult) {
            do_log(sprintf("user: %s just enable at: %s, skip", $uid, $enableCacheResult));
            continue;
        }
        $uidArr[] = $uid;
        $reason = nexus_trans($reasonKey, [], $user->locale);
        $userBanLogData[] = [
            'uid' => $uid,
            'username' => $user->username,
            'reason' => $reason,
        ];
        $userModifyLogs[] = [
            'user_id' => $uid,
            'content' => sprintf("[CLEANUP] %s", $reason),
            'created_at' => date("Y-m-d H:i:s"),
            'updated_at' => date("Y-m-d H:i:s"),
        ];
    }
    if (empty($uidArr)) {
        return [];
    }
    \App\Models\User::query()->whereIn('id', $uidArr)->update(['enabled' => \App\Models\User::ENABLED_NO]);
    \App\Models\UserBanLog::query()->insert($userBanLogData);
    \App\Models\UserModifyLog::query()->insert($userModifyLogs);
    do_log("[DISABLE_USER]($reasonKey): " . implode(', ', $uidArr));
    foreach ($uidArr as $uid) {
        publish_model_event(ModelEventEnum::USER_DISABLED, $uid);
    }
    return $uidArr;
}

function docleanup($forceAll = 0, $printProgress = false) {
	//require_once(get_langfile_path("cleanup.php",true));
	global $torrent_dir, $signup_timeout, $max_dead_torrent_time, $autoclean_interval_one, $autoclean_interval_two, $autoclean_interval_three, $autoclean_interval_four, $autoclean_interval_five, $SITENAME,$bonus,$invite_timeout,$offervotetimeout_main,$offeruptimeout_main, $iniupload_main;
	global $donortimes_bonus, $perseeding_bonus, $maxseeding_bonus, $tzero_bonus, $nzero_bonus, $bzero_bonus, $l_bonus;
	global $expirehalfleech_torrent, $expirefree_torrent, $expiretwoup_torrent, $expiretwoupfree_torrent, $expiretwouphalfleech_torrent, $expirethirtypercentleech_torrent, $expirenormal_torrent, $hotdays_torrent, $hotseeder_torrent,$halfleechbecome_torrent,$freebecome_torrent,$twoupbecome_torrent,$twoupfreebecome_torrent, $twouphalfleechbecome_torrent, $thirtypercentleechbecome_torrent, $normalbecome_torrent, $deldeadtorrent_torrent;
	global $neverdelete_account, $neverdeletepacked_account, $deletepacked_account, $deleteunpacked_account, $deletenotransfer_account, $deletenotransfertwo_account, $deletepeasant_account, $psdlone_account, $psratioone_account, $psdltwo_account, $psratiotwo_account, $psdlthree_account, $psratiothree_account, $psdlfour_account, $psratiofour_account, $psdlfive_account, $psratiofive_account, $putime_account, $pudl_account, $puprratio_account, $puderatio_account, $eutime_account, $eudl_account, $euprratio_account, $euderatio_account, $cutime_account, $cudl_account, $cuprratio_account, $cuderatio_account, $iutime_account, $iudl_account, $iuprratio_account, $iuderatio_account, $vutime_account, $vudl_account, $vuprratio_account, $vuderatio_account, $exutime_account, $exudl_account, $exuprratio_account, $exuderatio_account, $uutime_account, $uudl_account, $uuprratio_account, $uuderatio_account, $nmtime_account, $nmdl_account, $nmprratio_account, $nmderatio_account, $getInvitesByPromotion_class;
	global $enablenoad_advertisement, $noad_advertisement;
	global $Cache;
	global $rootpath;
    $requestId = nexus()->getRequestId();
//	require_once($rootpath . '/lang/_target/lang_cleanup.php');
	set_time_limit(0);
	ignore_user_abort(1);
	$now = time();
    $carbonNow = \Carbon\Carbon::now();
	$nowStr = $carbonNow->toDateTimeString();
	do_log("start docleanup(), forceAll: $forceAll, printProgress: $printProgress, now: $now, " . date('Y-m-d H:i:s', $now));

//Priority Class 1: cleanup every 15 mins
//2.update peer status
	$deadtime = deadtime();
	$deadtime = date("Y-m-d H:i:s",$deadtime);
	sql_query("DELETE FROM peers WHERE last_action < ".sqlesc($deadtime)) or sqlerr(__FILE__, __LINE__);
	$log = 'update peer status';
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}
//11.calculate seeding bonus
//	$res = sql_query("SELECT DISTINCT userid FROM peers WHERE seeder = 'yes'") or sqlerr(__FILE__, __LINE__);
//	if (mysql_num_rows($res) > 0)
//	{
//	    $haremAdditionFactor = get_setting('bonus.harem_addition');
//	    $officialAdditionFactor = get_setting('bonus.official_addition');
//		while ($arr = mysql_fetch_assoc($res))	//loop for different users
//		{
//		    $userInfo = get_user_row($arr['userid']);
//            $isDonor = is_donor($userInfo);
//            $seedBonusResult = calculate_seed_bonus($arr['userid']);
//            $bonusLog = "[CLEANUP_CALCULATE_SEED_BONUS], user: {$arr['userid']}, seedBonusResult: " . nexus_json_encode($seedBonusResult);
//            $all_bonus = $seedBonusResult['seed_bonus'];
//            $bonusLog .= ", all_bonus: $all_bonus";
//            if ($isDonor) {
//                $all_bonus = $all_bonus * $donortimes_bonus;
//                $bonusLog .= ", isDonor, donortimes_bonus: $donortimes_bonus, all_bonus: $all_bonus";
//            }
//            if ($officialAdditionFactor > 0) {
//                $officialAddition = $seedBonusResult['official_bonus'] * $officialAdditionFactor;
//                $all_bonus += $officialAddition;
//                $bonusLog .= ", officialAdditionFactor: $officialAdditionFactor, official_bonus: {$seedBonusResult['official_bonus']}, officialAddition: $officialAddition, all_bonus: $all_bonus";
//            }
//            if ($haremAdditionFactor > 0) {
//                $haremBonus = calculate_harem_addition($arr['userid']);
//                $haremAddition =  $haremBonus * $haremAdditionFactor;
//                $all_bonus += $haremAddition;
//                $bonusLog .= ", haremAdditionFactor: $haremAdditionFactor, haremBonus: $haremBonus, haremAddition: $haremAddition, all_bonus: $all_bonus";
//            }
//            $dividend = 3600 / $autoclean_interval_one;
//            $all_bonus = $all_bonus / $dividend;
//            $seed_points = $seedBonusResult['seed_points'] / $dividend;
//            $sql = "update users set seed_points = ifnull(seed_points, 0) + $seed_points, seedbonus = seedbonus + $all_bonus where id = {$arr["userid"]}";
//            do_log("$bonusLog, query: $sql");
//			sql_query($sql);
//		}
//	}

    //rest seed_points_per_hour
    $seedPointsUpdatedAtMin = $carbonNow->subSeconds(2*intval($autoclean_interval_one))->toDateTimeString();
    sql_query("update users set seed_points_per_hour = 0, seed_bonus_per_hour = 0, seeding_torrent_count = 0, seeding_torrent_size = 0 where seed_points_updated_at < " . sqlesc($seedPointsUpdatedAtMin));

	\App\Repositories\CleanupRepository::runBatchJobCalculateUserSeedBonus($requestId);

	$log = 'calculate seeding bonus';
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}

//Priority Class 2: cleanup every 30 mins
	$res = sql_query("SELECT value_u FROM avps WHERE arg = 'lastcleantime2'");
	$row = mysql_fetch_array($res);
	if (!$row && !$forceAll) {
		sql_query("INSERT INTO avps (arg, value_u) VALUES ('lastcleantime2',".sqlesc($now).")") or sqlerr(__FILE__, __LINE__);
		$log = "no value for arg: 'lastcleantime2', return";
		do_log($log);
		return $log;
	}
	$ts = $row[0] ?? 0;
	if ($ts + $autoclean_interval_two > $now && !$forceAll) {
		$log = 'Cleanup ends at Priority Class 1';
		do_log($log . ", $ts + $autoclean_interval_two > $now");
		return $log;
	} else {
		sql_query("UPDATE avps SET value_u = ".sqlesc($now)." WHERE arg='lastcleantime2'") or sqlerr(__FILE__, __LINE__);
	}

	//2.5.update torrents' visibility
	$deadtime = deadtime() - $max_dead_torrent_time;
    $lastActionDeadTime = date("Y-m-d H:i:s",$deadtime);
	sql_query("UPDATE torrents SET visible='no' WHERE visible='yes' AND last_action < '$lastActionDeadTime' AND seeders=0") or sqlerr(__FILE__, __LINE__);
	$log = "update torrents' visibility";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}

//Priority Class 3: cleanup every 60 mins
	$res = sql_query("SELECT value_u FROM avps WHERE arg = 'lastcleantime3'");
	$row = mysql_fetch_array($res);
	if (!$row && !$forceAll) {
		sql_query("INSERT INTO avps (arg, value_u) VALUES ('lastcleantime3',$now)") or sqlerr(__FILE__, __LINE__);
		$log = "no value for arg: 'lastcleantime3', return";
		do_log($log);
		return $log;
	}
	$ts = $row[0] ?? 0;
	if ($ts + $autoclean_interval_three > $now && !$forceAll) {
		$log = 'Cleanup ends at Priority Class 2';
		do_log($log . ", $ts + $autoclean_interval_three > $now");
		return $log;
	} else {
		sql_query("UPDATE avps SET value_u = ".sqlesc($now)." WHERE arg='lastcleantime3'") or sqlerr(__FILE__, __LINE__);
	}

	//4.update count of seeders, leechers, comments for torrents
//	$torrents = array();
//	$res = sql_query("SELECT torrent, seeder, COUNT(*) AS c FROM peers GROUP BY torrent, seeder") or sqlerr(__FILE__, __LINE__);
//	while ($row = mysql_fetch_assoc($res)) {
//		if ($row["seeder"] == "yes")
//		$key = "seeders";
//		else
//		$key = "leechers";
//		$torrents[$row["torrent"]][$key] = $row["c"];
//	}
//
//	$res = sql_query("SELECT torrent, COUNT(*) AS c FROM comments GROUP BY torrent") or sqlerr(__FILE__, __LINE__);
//	while ($row = mysql_fetch_assoc($res)) {
//		$torrents[$row["torrent"]]["comments"] = $row["c"];
//	}
//
//	$fields = explode(":", "comments:leechers:seeders");
//	$res = sql_query("SELECT id, seeders, leechers, comments FROM torrents") or sqlerr(__FILE__, __LINE__);
//	while ($row = mysql_fetch_assoc($res)) {
//		$id = $row["id"];
//		$torr = $torrents[$id] ?? [];
//		foreach ($fields as $field) {
//			if (!isset($torr[$field]))
//			$torr[$field] = 0;
//		}
//		$update = array();
//		foreach ($fields as $field) {
//			if ($torr[$field] != $row[$field])
//			$update[] = "$field = " . $torr[$field];
//		}
//		if (count($update))
//		sql_query("UPDATE torrents SET " . implode(",", $update) . " WHERE id = $id") or sqlerr(__FILE__, __LINE__);
//	}

    \App\Repositories\CleanupRepository::runBatchJobUpdateTorrentSeedersEtc($requestId);

	$log = "update count of seeders, leechers, comments for torrents";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
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
		$topics = sql_query("select id from topics where forumid={$forum['id']}") or sqlerr(__FILE__, __LINE__);
		while ($topic = mysql_fetch_assoc($topics))
		{
			$res = sql_query("select count(*) from posts where topicid={$topic['id']}") or sqlerr(__FILE__, __LINE__);
			$arr = mysql_fetch_row($res);
			$postcount += $arr[0];
			++$topiccount;
		}
		sql_query("update forums set postcount=$postcount, topiccount=$topiccount where id={$forum['id']}") or sqlerr(__FILE__, __LINE__);
	}
	$Cache->delete_value('forums_list');
	$log = "update forum post/topic count";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}
	//14.cleanup offers
	//Delete offers if not voted on after some time
	if($offervotetimeout_main){
		$secs = (int)$offervotetimeout_main;
		$dt = sqlesc(date("Y-m-d H:i:s",(TIMENOW - ($offervotetimeout_main))));
		$res = sql_query("SELECT id, name FROM offers WHERE added < $dt AND allowed <> 'allowed'") or sqlerr(__FILE__, __LINE__);
		while($arr = mysql_fetch_assoc($res)){
		sql_query("DELETE FROM offers WHERE id={$arr['id']}") or sqlerr(__FILE__, __LINE__);
		sql_query("DELETE FROM offervotes WHERE offerid={$arr['id']}") or sqlerr(__FILE__, __LINE__);
		sql_query("DELETE FROM comments WHERE offer={$arr['id']}") or sqlerr(__FILE__, __LINE__);
		write_log("Offer {$arr['id']} ({$arr['name']}) was deleted by system (vote timeout)",'normal');
		}
	}
	$log = "delete offers if not voted on after some time";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}

	//Delete offers if not uploaded after being voted on for some time.
	if($offeruptimeout_main){
		$secs = (int)$offeruptimeout_main;
		$dt = sqlesc(date("Y-m-d H:i:s",(TIMENOW - ($secs))));
		$res = sql_query("SELECT id, name FROM offers WHERE allowedtime < $dt AND allowed = 'allowed'") or sqlerr(__FILE__, __LINE__);
		while($arr = mysql_fetch_assoc($res)){
		sql_query("DELETE FROM offers WHERE id={$arr['id']}") or sqlerr(__FILE__, __LINE__);
		sql_query("DELETE FROM offervotes WHERE offerid={$arr['id']}") or sqlerr(__FILE__, __LINE__);
		sql_query("DELETE FROM comments WHERE offer={$arr['id']}") or sqlerr(__FILE__, __LINE__);
		write_log("Offer {$arr['id']} ({$arr['name']}) was deleted by system (upload timeout)",'normal');
		}
	}
	$log = "delete offers if not uploaded after being voted on for some time.";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}

	//15.cleanup torrents
	//Start: expire torrent promotion
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
//	sql_query("UPDATE torrents SET sp_state = 1, promotion_time_type=0, promotion_until=null WHERE promotion_time_type=2 AND promotion_until < ".sqlesc(date("Y-m-d H:i:s",TIMENOW))) or sqlerr(__FILE__, __LINE__);
    torrent_promotion_individual_expire();
	//End: expire torrent promotion
	$log = "expire torrent promotion";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}

	//expire torrent sticky
    $toBeExpirePosStates = [
        \App\Models\Torrent::POS_STATE_STICKY_FIRST,
        \App\Models\Torrent::POS_STATE_STICKY_SECOND,
    ];
	$update = [
	    'pos_state' => \App\Models\Torrent::POS_STATE_STICKY_NONE,
        'pos_state_until' => null,
    ];
    \App\Models\Torrent::query()
        ->whereIn('pos_state', $toBeExpirePosStates)
        ->whereNotNull('pos_state_until')
        ->where('pos_state_until', '<', now())
        ->update($update);
    $log = "expire torrent pos state";
    do_log($log);
    if ($printProgress) {
        printProgress($log);
    }


	//automatically pick hot
	if ($hotdays_torrent)
	{
		$secs = (int)($hotdays_torrent * 86400); //XX days
		$dt = sqlesc(date("Y-m-d H:i:s",(TIMENOW - ($secs))));
		sql_query("UPDATE torrents SET picktype = 'hot' WHERE added > $dt AND picktype = 'normal' AND seeders > ".sqlesc($hotseeder_torrent)) or sqlerr(__FILE__, __LINE__);
	}
	if ($printProgress) {
		$log = "automatically pick hot";
		do_log($log);
		printProgress($log);
	}


//Priority Class 4: cleanup every 24 hours
	$res = sql_query("SELECT value_u FROM avps WHERE arg = 'lastcleantime4'");
	$row = mysql_fetch_array($res);
	if (!$row && !$forceAll) {
		sql_query("INSERT INTO avps (arg, value_u) VALUES ('lastcleantime4',$now)") or sqlerr(__FILE__, __LINE__);
		$log = "no value for arg: 'lastcleantime4', return";
		do_log($log);
		return $log;
	}
	$ts = $row[0] ?? 0;
	if ($ts + $autoclean_interval_four > $now && !$forceAll) {
		$log = 'Cleanup ends at Priority Class 3';
		do_log($log . ", $ts + $autoclean_interval_four > $now");
		return $log;
	} else {
		sql_query("UPDATE avps SET value_u = ".sqlesc($now)." WHERE arg='lastcleantime4'") or sqlerr(__FILE__, __LINE__);
	}

	//3.delete unconfirmed accounts
	$deadtime = time() - $signup_timeout;
    $deadlineField = \Nexus\Database\NexusDB::fromUnixTimestampField($deadtime);
	sql_query("DELETE FROM users WHERE status = 'pending' AND added < $deadlineField AND last_login < $deadlineField AND last_access < $deadlineField") or sqlerr(__FILE__, __LINE__);
//	$query = \App\Models\User::query()
//        ->where('status', 'pending')
//        ->whereRaw("added < FROM_UNIXTIME($deadtime)")
//        ->whereRaw("last_login < FROM_UNIXTIME($deadtime)")
//        ->whereRaw("last_access < FROM_UNIXTIME($deadtime)");
//    disable_user($query, "cleanup.disable_user_unconfirmed");
    $log = "delete unconfirmed accounts";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}

	//5.delete old login attempts
	$secs = 12*60*60; // Delete failed login attempts per half day.
	$dt = sqlesc(date("Y-m-d H:i:s",(TIMENOW - $secs))); // calculate date.
	sql_query("DELETE FROM loginattempts WHERE banned='no' AND added < $dt") or sqlerr(__FILE__, __LINE__);
	$log = "delete old login attempts";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}

	//6.delete old invite codes
	$secs = $invite_timeout*24*60*60; // when?
	$dt = sqlesc(date("Y-m-d H:i:s",(TIMENOW - $secs))); // calculate date.
	sql_query("DELETE FROM invites WHERE ((time_invited < $dt and time_invited is not null and invitee != '') or (invitee = '' and expired_at < '$nowStr' and expired_at is not null))") or sqlerr(__FILE__, __LINE__);
	$log = "delete old invite codes";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}

	//7.delete regimage codes
	sql_query("TRUNCATE TABLE regimages") or sqlerr(__FILE__, __LINE__);
	$log = "delete regimage codes";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}
	//10.clean up user accounts
	// make sure VIP or above never get deleted
	$neverdelete_account = ($neverdelete_account <= UC_VIP ? $neverdelete_account : UC_VIP);

	//delete inactive user accounts, no transfer. Alt. 1: last access time
	if ($deletenotransfer_account){
		$secs = $deletenotransfer_account*24*60*60;
		$dt = date("Y-m-d H:i:s",(TIMENOW - $secs));
		$maxclass = $neverdelete_account;
//		sql_query("DELETE FROM users WHERE parked='no' AND status='confirmed' AND class < $maxclass AND last_access < $dt AND (uploaded = 0 || uploaded = ".sqlesc($iniupload_main).") AND downloaded = 0") or sqlerr(__FILE__, __LINE__);
        $query = \App\Models\User::query()
            ->where('parked', 'no')
            ->where('status', 'confirmed')
            ->where("class","<", $maxclass)
            ->where("last_access","<", $dt)
            ->where("downloaded",0)
            ->where(function (\Illuminate\Database\Eloquent\Builder $query) use ($iniupload_main) {
                $query->where('uploaded', 0)->orWhere('uploaded', $iniupload_main);
            });
        disable_user($query, "cleanup.disable_user_no_transfer_alt_last_access_time");
	}
	$log = "delete inactive user accounts, no transfer. Alt. 1: last access time";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}

	//delete inactive user accounts, no transfer. Alt. 2: registering time
	if ($deletenotransfertwo_account){
		$secs = $deletenotransfertwo_account*24*60*60;
		$dt = date("Y-m-d H:i:s",(TIMENOW - $secs));
		$maxclass = $neverdelete_account;
//		sql_query("DELETE FROM users WHERE parked='no' AND status='confirmed' AND class < $maxclass AND added < $dt AND (uploaded = 0 || uploaded = ".sqlesc($iniupload_main).") AND downloaded = 0") or sqlerr(__FILE__, __LINE__);
        $query = \App\Models\User::query()
            ->where('parked', 'no')
            ->where('status', 'confirmed')
            ->where("class","<", $maxclass)
            ->where("added","<", $dt)
            ->where("downloaded",0)
            ->where(function (\Illuminate\Database\Eloquent\Builder $query) use ($iniupload_main) {
                $query->where('uploaded', 0)->orWhere('uploaded', $iniupload_main);
            });
        disable_user($query, "cleanup.disable_user_no_transfer_alt_register_time");
	}
	$log = "delete inactive user accounts, no transfer. Alt. 2: registering time";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}

	//delete inactive user accounts, not parked
	if ($deleteunpacked_account){
		$secs = $deleteunpacked_account*24*60*60;
		$dt = date("Y-m-d H:i:s",(TIMENOW - $secs));
		$maxclass = $neverdelete_account;
//	    sql_query("DELETE FROM users WHERE parked='no' AND status='confirmed' AND class < $maxclass AND last_access < $dt") or sqlerr(__FILE__, __LINE__);
        $query = \App\Models\User::query()
            ->where('parked', 'no')
            ->where('status', 'confirmed')
            ->where("class","<", $maxclass)
            ->where("last_access","<", $dt);
        disable_user($query, "cleanup.disable_user_not_parked");
	}
	$log = "delete inactive user accounts, not parked";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}

	//delete parked user accounts, parked
	if ($deletepacked_account){
		$secs = $deletepacked_account*24*60*60;
		$dt = date("Y-m-d H:i:s",(TIMENOW - $secs));
		$maxclass = $neverdeletepacked_account;
//		sql_query("DELETE FROM users WHERE parked='yes' AND status='confirmed' AND class < $maxclass AND last_access < $dt") or sqlerr(__FILE__, __LINE__);
        $query = \App\Models\User::query()
            ->where('parked', 'yes')
            ->where('status', 'confirmed')
            ->where("class","<", $maxclass)
            ->where("last_access","<", $dt);
        disable_user($query, "cleanup.disable_user_parked");
	}
	$log = "delete parked user accounts, parked";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}

	//destroy disabled accounts
    $userRep = new \App\Repositories\UserRepository();
    $destroyDisabledDays = get_setting('account.destroy_disabled');
    if ($destroyDisabledDays > 0) {
        $secs = $destroyDisabledDays*24*60*60;
        $dt = date("Y-m-d H:i:s",(TIMENOW - $secs));
        \App\Models\User::query()
            ->where('enabled', 'no')
            ->where("last_access","<", $dt)
            ->select(['id', 'username', 'lang'])
            ->orderBy("id", "asc")
            ->chunk(2000, function (\Illuminate\Support\Collection $users) use ($userRep) {
                $userRep->destroy($users, 'cleanup.destroy_disabled_account');
            });
    }
    $log = "destroy disabled accounts";
    do_log($log);
    if ($printProgress) {
        printProgress($log);
    }

    //migrate to job: RemoveUserVipStatus
	//remove VIP status if time's up
//	$res = sql_query("SELECT id, class FROM users WHERE vip_added='yes' AND vip_until < NOW()") or sqlerr(__FILE__, __LINE__);
//	$userModifyLogs = [];
//    if (mysql_num_rows($res) > 0)
//	{
//		while ($arr = mysql_fetch_assoc($res))
//		{
//			$dt = sqlesc(date("Y-m-d H:i:s"));
//            $locale = get_user_locale($arr['id']);
//            $subject = sqlesc(nexus_trans("cleanup.msg_vip_status_removed", [], $locale));
//            $msg = sqlesc(nexus_trans("cleanup.msg_vip_status_removed_body", [], $locale));
//            $userModifyLogs[] = [
//                'user_id' => $arr['id'],
//                'content' => "VIP status removed by - AutoSystem",
//                'created_at' => date("Y-m-d H:i:s"),
//                'updated_at' => date("Y-m-d H:i:s"),
//            ];
//			if ($arr['class'] > \App\Models\User::CLASS_VIP) {
//                /**
//                 * @since 1.8
//                 * never demotion VIP above
//                 */
//                sql_query("UPDATE users SET vip_added = 'no', vip_until = null WHERE id = {$arr['id']}") or sqlerr(__FILE__, __LINE__);
//            } else {
//                sql_query("UPDATE users SET class = '1', vip_added = 'no', vip_until = null WHERE id = {$arr['id']}") or sqlerr(__FILE__, __LINE__);
//                sql_query("INSERT INTO messages (sender, receiver, added, msg, subject) VALUES(0, {$arr['id']}, $dt, $msg, $subject)") or sqlerr(__FILE__, __LINE__);
//            }
//            publish_model_event(ModelEventEnum::USER_UPDATED, $arr['id']);
//		}
//	}
//    if (!empty($userModifyLogs)) {
//        \App\Models\UserModifyLog::query()->insert($userModifyLogs);
//    }
//	$log = "remove VIP status if time's up";
//	do_log($log);
//	if ($printProgress) {
//		printProgress($log);
//	}

    //migrate to job: RemoveUserDonorStatus
    //remove donor status if time's up
//    $userModifyLogs = [];
//    $res = sql_query("SELECT id FROM users WHERE donor='yes' AND donoruntil is not null and donoruntil != '0000-00-00 00:00:00' and donoruntil < NOW()") or sqlerr(__FILE__, __LINE__);
//    if (mysql_num_rows($res) > 0)
//    {
//        while ($arr = mysql_fetch_assoc($res))
//        {
//            $dt = sqlesc(date("Y-m-d H:i:s"));
//            $locale = get_user_locale($arr['id']);
//            $subject = sqlesc(nexus_trans("cleanup.msg_donor_status_removed", [], $locale));
//            $msg = sqlesc(nexus_trans("cleanup.msg_donor_status_removed_body", [], $locale));
//            $userModifyLogs[] = [
//                'user_id' => $arr['id'],
//                'content' => "donor status removed by - AutoSystem",
//                'created_at' => date("Y-m-d H:i:s"),
//                'updated_at' => date("Y-m-d H:i:s"),
//            ];
//            sql_query("UPDATE users SET donor = 'no' WHERE id = {$arr['id']}") or sqlerr(__FILE__, __LINE__);
//            sql_query("INSERT INTO messages (sender, receiver, added, msg, subject) VALUES(0, {$arr['id']}, $dt, $msg, $subject)") or sqlerr(__FILE__, __LINE__);
//            publish_model_event(ModelEventEnum::USER_UPDATED, $arr['id']);
//        }
//    }
//    if (!empty($userModifyLogs)) {
//        \App\Models\UserModifyLog::query()->insert($userModifyLogs);
//    }
//    $log = "remove donor status if time's up";
//    do_log($log);
//    if ($printProgress) {
//        printProgress($log);
//    }

	// promote peasant back to user

	peasant_to_user($psdlfive_account,0, $psratiofive_account);
	peasant_to_user($psdlfour_account,$psdlfive_account, $psratiofour_account);
	peasant_to_user($psdlthree_account,$psdlfour_account, $psratiothree_account);
	peasant_to_user($psdltwo_account,$psdlthree_account, $psratiotwo_account);
	peasant_to_user($psdlone_account,$psdltwo_account, $psratioone_account);
	$log = "promote peasant back to user";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}
	//end promote peasant back to user

	// start promotion
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
	$log = "promote users to other classes";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}

	// start demotion

		//do not change the descending order
	demotion(UC_NEXUS_MASTER,$nmderatio_account);
	demotion(UC_ULTIMATE_USER,$uuderatio_account);
	demotion(UC_EXTREME_USER,$exuderatio_account);
	demotion(UC_VETERAN_USER,$vuderatio_account);
	demotion(UC_INSANE_USER,$iuderatio_account);
	demotion(UC_CRAZY_USER,$cuderatio_account);
	demotion(UC_ELITE_USER,$euderatio_account);
	demotion(UC_POWER_USER,$puderatio_account);
	$log = "demote users to other classes";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}
	// end demotion

	// start demote users to peasant

	user_to_peasant($psdlone_account, $psratioone_account);
	user_to_peasant($psdltwo_account, $psratiotwo_account);
	user_to_peasant($psdlthree_account, $psratiothree_account);
	user_to_peasant($psdlfour_account, $psratiofour_account);
	user_to_peasant($psdlfive_account, $psratiofive_account);
	$log = "demote Users to peasant";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}
	// end Users to Peasant

	//ban users with leechwarning expired
//	$dt = sqlesc(date("Y-m-d H:i:s")); // take date time
//	$res = sql_query("SELECT id FROM users WHERE enabled = 'yes' AND leechwarn = 'yes' AND leechwarnuntil < $dt") or sqlerr(__FILE__, __LINE__);
//
//	if (mysql_num_rows($res) > 0)
//	{
//		while ($arr = mysql_fetch_assoc($res))
//		{
//			writecomment($arr['id'],"Banned by System because of Leech Warning expired.");
//
//			sql_query("UPDATE users SET enabled = 'no', leechwarnuntil = null WHERE id = {$arr['id']}") or sqlerr(__FILE__, __LINE__);
//
//
//		}
//	}
    ban_user_with_leech_warning_expired();
	$log = "ban users with leechwarning expired";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}

    //migrate to job: RemoveUserWarning
	//Remove warning of users
//	$dt = sqlesc(date("Y-m-d H:i:s")); // take date time
//	$res = sql_query("SELECT id FROM users WHERE enabled = 'yes' AND warned = 'yes' AND warneduntil < $dt") or sqlerr(__FILE__, __LINE__);
//
//	if (mysql_num_rows($res) > 0)
//	{
//		while ($arr = mysql_fetch_assoc($res))
//		{
//            $locale = get_user_locale($arr['id']);
//            $subject = nexus_trans("cleanup.msg_warning_removed", [], $locale);
//            $msg = nexus_trans("cleanup.msg_your_warning_removed", [], $locale);
//			writecomment($arr['id'],"Warning removed by System.");
//			sql_query("UPDATE users SET warned = 'no', warneduntil = null WHERE id = {$arr['id']}") or sqlerr(__FILE__, __LINE__);
//			sql_query("INSERT INTO messages (sender, receiver, added, subject, msg) VALUES(0, {$arr['id']}, $dt, ".sqlesc($subject).", ".sqlesc($msg).")") or sqlerr(__FILE__, __LINE__);
//		}
//	}
//	$log = "remove warning of users";
//	do_log($log);
//	if ($printProgress) {
//		printProgress($log);
//	}

	//17.update total seeding and leeching time of users
//	$res = sql_query("SELECT id FROM users where enabled = 'yes' and status = 'confirmed'") or sqlerr(__FILE__, __LINE__);
//	while($arr = mysql_fetch_assoc($res))
//	{
//		//die("s" . $arr['id']);
//		$res2 = sql_query("SELECT SUM(seedtime) as st, SUM(leechtime) as lt FROM snatched where userid = " . $arr['id'] . " LIMIT 1") or sqlerr(__FILE__, __LINE__);
//		$arr2 = mysql_fetch_assoc($res2) or sqlerr(__FILE__, __LINE__);
//
//		//die("ss" . $arr2['st']);
//		//die("sss" . "UPDATE users SET seedtime = " . $arr2['st'] . ", leechtime = " . $arr2['lt'] . " WHERE id = " . $arr['id']);
//
//		sql_query("UPDATE users SET seedtime = " . intval($arr2['st']) . ", leechtime = " . intval($arr2['lt']) . " WHERE id = " . $arr['id']) or sqlerr(__FILE__, __LINE__);
//	}

    \App\Repositories\CleanupRepository::runBatchJobUpdateUserSeedingLeechingTime($requestId);

	$log = "update total seeding and leeching time of users";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}

	//update exam progress
    //move to cronjob from v1.7
//    $examRep = new \App\Repositories\ExamRepository();
//    $updateExamProgressResult = $examRep->updateProgressBulk();
//    $log = 'update exam progress';
//    do_log($log . ", result: " . json_encode($updateExamProgressResult));
//    if ($printProgress) {
//        printProgress($log);
//    }

	// delete torrents that have been dead for a long time
	if ($deldeadtorrent_torrent > 0){
		$length = $deldeadtorrent_torrent*86400;
		$until = date("Y-m-d H:i:s",(TIMENOW - $length));
		$dt = sqlesc(date("Y-m-d H:i:s"));
		$res = sql_query("SELECT torrents.id, torrents.name, torrents.owner, users.id as uid FROM torrents left join users on torrents.owner = users.id WHERE torrents.visible = 'no' AND torrents.last_action < ".sqlesc($until)." AND torrents.seeders = 0 AND torrents.leechers = 0") or sqlerr(__FILE__, __LINE__);
		while($arr = mysql_fetch_assoc($res))
		{
			deletetorrent($arr['id']);
            if (!empty($arr['uid'])) {
                $locale = get_user_locale($arr['owner']);
                $subject = nexus_trans("cleanup.msg_your_torrent_deleted", [], $locale);
                $msg = nexus_trans("cleanup.msg_your_torrent", [], $locale)."[i]".$arr['name']."[/i]".nexus_trans("cleanup.msg_was_deleted_because_dead", [], $locale);
                sql_query("INSERT INTO messages (sender, receiver, added, subject, msg) VALUES(0, {$arr['owner']}, $dt, ".sqlesc($subject).", ".sqlesc($msg).")") or sqlerr(__FILE__, __LINE__);
                write_log("Torrent {$arr['id']} ({$arr['name']}) is deleted by system because of being dead for a long time.",'normal');
            }
		}
	}
	$log = "delete torrents that have been dead for a long time";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}

    //delete old ip log
    $length = 90*86400; //90 days
    $until = date("Y-m-d H:i:s",(TIMENOW - $length));
    sql_query("DELETE FROM iplog WHERE access < ".sqlesc($until));
    $log = "delete old ip log";
    do_log($log);
    if ($printProgress) {
        printProgress($log);
    }

    //delete failed jobs
    $length = 10*86400; //10 days
    $until = date("Y-m-d H:i:s",(TIMENOW - $length));
    sql_query("DELETE FROM failed_jobs WHERE failed_at < ".sqlesc($until));
    $log = "delete failed jobs";
    do_log($log);
    if ($printProgress) {
        printProgress($log);
    }



    //cost too many time, migrate to schedule run command
    //sync to Meilisearch
//    $meiliRep = new \App\Repositories\MeiliSearchRepository();
//    if ($meiliRep->isEnabled()) {
//        $meiliRep->import();
//    }
//    $log = "sync to Meilisearch";
//    do_log($log);
//    if ($printProgress) {
//        printProgress($log);
//    }

//Priority Class 5: cleanup every 15 days
	$res = sql_query("SELECT value_u FROM avps WHERE arg = 'lastcleantime5'");
	$row = mysql_fetch_array($res);
	if (!$row && !$forceAll) {
		sql_query("INSERT INTO avps (arg, value_u) VALUES ('lastcleantime5',$now)") or sqlerr(__FILE__, __LINE__);
		$log = "no value for arg: 'lastcleantime5', return";
		do_log($log);
		return $log;
	}
	$ts = $row[0] ?? 0;
	if ($ts + $autoclean_interval_five > $now && !$forceAll) {
		$log = 'Cleanup ends at Priority Class 4';
		do_log($log . ", $ts + $autoclean_interval_five > $now");
		return $log;
	} else {
		sql_query("UPDATE avps SET value_u = ".sqlesc($now)." WHERE arg='lastcleantime5'") or sqlerr(__FILE__, __LINE__);
	}

	//update clients' popularity
	$res = sql_query("SELECT id FROM agent_allowed_family");
	while($row = mysql_fetch_array($res)){
		$count = get_row_count("users","WHERE clientselect=".sqlesc($row['id']));
		sql_query("UPDATE agent_allowed_family SET hits=".sqlesc($count)." WHERE id=".sqlesc($row['id']));
	}
	$log = "update clients' popularity";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}

	//delete old messages sent by system
	$length = 180*86400; //half a year
	$until = date("Y-m-d H:i:s",(TIMENOW - $length));
	sql_query("DELETE FROM messages WHERE sender = 0 AND added < ".sqlesc($until));
	$log = "delete old messages sent by system";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}

	//delete old readpost records
	$length = 180*86400; //half a year
	$until = date("Y-m-d H:i:s",(TIMENOW - $length));
	$postIdHalfYearAgo = get_single_value('posts', 'id', 'WHERE added < ' . sqlesc($until).' ORDER BY added DESC');
	if ($postIdHalfYearAgo) {
		sql_query("UPDATE users SET last_catchup = ".sqlesc($postIdHalfYearAgo)." WHERE last_catchup < ".sqlesc($postIdHalfYearAgo));
		sql_query("DELETE FROM readposts WHERE lastpostread < ".sqlesc($postIdHalfYearAgo));
	}
	$log = "delete old readpost records";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}

    //delete old cheaters
    $until = date("Y-m-d H:i:s",(TIMENOW - $length));
    sql_query("DELETE FROM cheaters WHERE added < ".sqlesc($until)) or sqlerr(__FILE__, __LINE__);
    $log = "delete old cheaters";
    do_log($log);
    if ($printProgress) {
        printProgress($log);
    }

    //delete old shoutbox
    $until = TIMENOW - $length;
    \Nexus\Database\NexusDB::table('shoutbox')->where('date', '<', $until)->delete();
    $log = "delete old shoutbox";
    do_log($log);
    if ($printProgress) {
        printProgress($log);
    }

	//delete old general log
	$until = date("Y-m-d H:i:s",(TIMENOW - $length));
	sql_query("DELETE FROM sitelog WHERE added < " . sqlesc($until)) or sqlerr(__FILE__, __LINE__);
	$log = "delete old general log";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}

	//1.delete torrents that doesn't exist any more
//	do {
//		$res = sql_query("SELECT id FROM torrents") or sqlerr(__FILE__, __LINE__);
//		$ar = array();
//		while ($row = mysql_fetch_array($res)) {
//			$id = $row[0];
//			$ar[$id] = 1;
//		}
//
//		if (!count($ar))
//		break;
//
//		$dp = @opendir($torrent_dir);
//		if (!$dp)
//		break;
//
//		$ar2 = array();
//		while (($file = readdir($dp)) !== false) {
//			if (!preg_match('/^(\d+)\.torrent$/', $file, $m))
//			continue;
//			$id = $m[1];
//			$ar2[$id] = 1;
//			if (isset($ar[$id]) && $ar[$id])
//			continue;
//			$ff = $torrent_dir . "/$file";
//			unlink($ff);
//		}
//		closedir($dp);
//
//		if (!count($ar2))
//		break;
//
//		$delids = array();
//		foreach (array_keys($ar) as $k) {
//			if (isset($ar2[$k]) && $ar2[$k])
//			continue;
//			$delids[] = $k;
//			unset($ar[$k]);
//		}
//		if (count($delids))
//		sql_query("DELETE FROM torrents WHERE id IN (" . join(",", $delids) . ")") or sqlerr(__FILE__, __LINE__);
//
//		$res = sql_query("SELECT torrent FROM peers GROUP BY torrent") or sqlerr(__FILE__, __LINE__);
//		$delids = array();
//		while ($row = mysql_fetch_array($res)) {
//			$id = $row[0];
//			if (isset($ar[$id]) && $ar[$id])
//			continue;
//			$delids[] = $id;
//		}
//		if (count($delids))
//		sql_query("DELETE FROM peers WHERE torrent IN (" . join(",", $delids) . ")") or sqlerr(__FILE__, __LINE__);
//
//		$res = sql_query("SELECT torrent FROM files GROUP BY torrent") or sqlerr(__FILE__, __LINE__);
//		$delids = array();
//		while ($row = mysql_fetch_array($res)) {
//			$id = $row[0];
//			if ($ar[$id])
//			continue;
//			$delids[] = $id;
//		}
//		if (count($delids))
//		sql_query("DELETE FROM files WHERE torrent IN (" . join(",", $delids) . ")") or sqlerr(__FILE__, __LINE__);
//	} while (0);
//    $log = "delete torrents that doesn't exist any more";
//    do_log($log);
//	if ($printProgress) {
//		printProgress($log);
//	}

	//8.lock topics where last post was made more than x days ago
	$secs = 365*24*60*60;
    $postAddedField = \Nexus\Database\NexusDB::unixTimestampField('posts.added');
    $diff = TIMENOW - $secs;
//	sql_query("UPDATE topics, posts SET topics.locked='yes' WHERE topics.lastpost = posts.id AND topics.sticky = 'no' AND $postAddedField < ".TIMENOW." - $secs") or sqlerr(__FILE__, __LINE__);
	sql_query("UPDATE topics SET locked='yes' WHERE sticky = 'no' AND lastpost in (select id from posts where $postAddedField < $diff)");

	$log = "lock topics where last post was made more than x days ago";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}

	//9.delete report items older than four week
	$secs = 4*7*24*60*60;
	$dt = sqlesc(date("Y-m-d H:i:s",(TIMENOW - $secs)));
	sql_query("DELETE FROM reports WHERE dealtwith=1 AND added < $dt") or sqlerr(__FILE__, __LINE__);
	$log = "delete report items older than four week";
	do_log($log);
	if ($printProgress) {
		printProgress($log);
	}

    //remove duplicate user ban logs
    //No need to do that, disable + destroy will have two records, sometimes disable will enable again
//    $log = "clear user ban log duplicate";
//	\App\Models\UserBanLog::clearUserBanLogDuplicate();
//    do_log($log);
//    if ($printProgress) {
//        printProgress($log);
//    }

    sql_query("delete from oauth_auth_codes where expires_at <= '$nowStr'");
    $log = "delete oauth auth code expired";
    do_log($log);
    if ($printProgress) {
        printProgress($log);
    }

    sql_query("delete from oauth_access_tokens where expires_at <= '$nowStr'");
    $log = "delete oauth access token expired";
    do_log($log);
    if ($printProgress) {
        printProgress($log);
    }

    sql_query("delete from oauth_refresh_tokens where expires_at <= '$nowStr'");
    $log = "delete oauth refresh token expired";
    do_log($log);
    if ($printProgress) {
        printProgress($log);
    }

	$log = 'Full cleanup is done';
	do_log($log);
    if ($printProgress) {
        printProgress($log);
    }
	return $log;
}
?>

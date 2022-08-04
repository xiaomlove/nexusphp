<?php
require_once('../include/bittorrent.php');
dbconn();
require_once(get_langfile_path());
require(get_langfile_path("",true));
loggedinorreturn();
parked();

function bonusarray($option = 0){
	global $onegbupload_bonus,$fivegbupload_bonus,$tengbupload_bonus,$oneinvite_bonus,$customtitle_bonus,$vipstatus_bonus, $basictax_bonus, $taxpercentage_bonus, $bonusnoadpoint_advertisement, $bonusnoadtime_advertisement;
	global $lang_mybonus;

	$results = [];
    //1.0 GB Uploaded
    $bonus = array();
    $bonus['points'] = $onegbupload_bonus;
    $bonus['art'] = 'traffic';
    $bonus['menge'] = 1073741824;
    $bonus['name'] = $lang_mybonus['text_uploaded_one'];
    $bonus['description'] = $lang_mybonus['text_uploaded_note'];
	$results[] = $bonus;

    //5.0 GB Uploaded
    $bonus = array();
    $bonus['points'] = $fivegbupload_bonus;
    $bonus['art'] = 'traffic';
    $bonus['menge'] = 5368709120;
    $bonus['name'] = $lang_mybonus['text_uploaded_two'];
    $bonus['description'] = $lang_mybonus['text_uploaded_note'];
    $results[] = $bonus;


    //10.0 GB Uploaded
    $bonus = array();
    $bonus['points'] = $tengbupload_bonus;
    $bonus['art'] = 'traffic';
    $bonus['menge'] = 10737418240;
    $bonus['name'] = $lang_mybonus['text_uploaded_three'];
    $bonus['description'] = $lang_mybonus['text_uploaded_note'];
    $results[] = $bonus;

    //Invite
    $bonus = array();
    $bonus['points'] = $oneinvite_bonus;
    $bonus['art'] = 'invite';
    $bonus['menge'] = 1;
    $bonus['name'] = $lang_mybonus['text_buy_invite'];
    $bonus['description'] = $lang_mybonus['text_buy_invite_note'];
    $results[] = $bonus;

    //Custom Title
    $bonus = array();
    $bonus['points'] = $customtitle_bonus;
    $bonus['art'] = 'title';
    $bonus['menge'] = 0;
    $bonus['name'] = $lang_mybonus['text_custom_title'];
    $bonus['description'] = $lang_mybonus['text_custom_title_note'];
    $results[] = $bonus;


    //VIP Status
    $bonus = array();
    $bonus['points'] = $vipstatus_bonus;
    $bonus['art'] = 'class';
    $bonus['menge'] = 0;
    $bonus['name'] = $lang_mybonus['text_vip_status'];
    $bonus['description'] = $lang_mybonus['text_vip_status_note'];
    $results[] = $bonus;

    //Bonus Gift
    $bonus = array();
    $bonus['points'] = 25;
    $bonus['art'] = 'gift_1';
    $bonus['menge'] = 0;
    $bonus['name'] = $lang_mybonus['text_bonus_gift'];
    $bonus['description'] = $lang_mybonus['text_bonus_gift_note'];
    if ($basictax_bonus || $taxpercentage_bonus){
        $onehundredaftertax = 100 - $taxpercentage_bonus - $basictax_bonus;
        $bonus['description'] .= "<br /><br />".$lang_mybonus['text_system_charges_receiver']."<b>".($basictax_bonus ? $basictax_bonus.$lang_mybonus['text_tax_bonus_point'].add_s($basictax_bonus).($taxpercentage_bonus ? $lang_mybonus['text_tax_plus'] : "") : "").($taxpercentage_bonus ? $taxpercentage_bonus.$lang_mybonus['text_percent_of_transfered_amount'] : "")."</b>".$lang_mybonus['text_as_tax'].$onehundredaftertax.$lang_mybonus['text_tax_example_note'];
    }
    $results[] = $bonus;


    //No ad for 15 days
    $bonus = array();
    $bonus['points'] = $bonusnoadpoint_advertisement;
    $bonus['art'] = 'noad';
    $bonus['menge'] = $bonusnoadtime_advertisement * 86400;
    $bonus['name'] = $bonusnoadtime_advertisement.$lang_mybonus['text_no_advertisements'];
    $bonus['description'] = $lang_mybonus['text_no_advertisements_note'];
    $results[] = $bonus;

    //Attendance card
    $bonus = array();
    $bonus['points'] = \App\Models\BonusLogs::getBonusForBuyAttendanceCard();
    $bonus['art'] = 'attendance_card';
    $bonus['menge'] = 0;
    $bonus['name'] = $lang_mybonus['text_attendance_card'];
    $bonus['description'] = $lang_mybonus['text_attendance_card_note'];
    $results[] = $bonus;

    //Donate
    $bonus = array();
    $bonus['points'] = 1000;
    $bonus['art'] = 'gift_2';
    $bonus['menge'] = 0;
    $bonus['name'] = $lang_mybonus['text_charity_giving'];
    $bonus['description'] = $lang_mybonus['text_charity_giving_note'];
    $results[] = $bonus;


    //Cancel hit and run
    $bonus = array();
    $bonus['points'] = \App\Models\BonusLogs::getBonusForCancelHitAndRun();
    $bonus['art'] = 'cancel_hr';
    $bonus['menge'] = 0;
    $bonus['name'] = $lang_mybonus['text_cancel_hr_title'];
    $bonus['description'] = '<p>
            <span style="">' . $lang_mybonus['text_cancel_hr_label'] . '</span>
            <input type="number" name="hr_id" />
        </p>';
    $results[] = $bonus;

    //Buy medal
    $medals = \App\Models\Medal::query()->where('get_type', \App\Models\Medal::GET_TYPE_EXCHANGE)->get();
    foreach ($medals as $medal) {
        $results[] = [
            'points' => $medal->price,
            'art' => 'buy_medal',
            'menge' => 0,
            'name' => $medal->name,
            'description' => sprintf(
                '<div style="display: flex;align-items: center"><div style="padding: 10px">%s</div><div><img src="%s" style="max-height: 120px"/></div></div><input type="hidden" name="medal_id" value="%s">',
                $medal->description, $medal->image_large, $medal->id
            ),
            'medal_id' => $medal->id,
        ];
    }

    return $results;

//
//	switch ($option)
//	{
//		case 1: {//1.0 GB Uploaded
//			$bonus['points'] = $onegbupload_bonus;
//			$bonus['art'] = 'traffic';
//			$bonus['menge'] = 1073741824;
//			$bonus['name'] = $lang_mybonus['text_uploaded_one'];
//			$bonus['description'] = $lang_mybonus['text_uploaded_note'];
//			break;
//			}
//		case 2: {//5.0 GB Uploaded
//			$bonus['points'] = $fivegbupload_bonus;
//			$bonus['art'] = 'traffic';
//			$bonus['menge'] = 5368709120;
//			$bonus['name'] = $lang_mybonus['text_uploaded_two'];
//			$bonus['description'] = $lang_mybonus['text_uploaded_note'];
//			break;
//			}
//		case 3: {//10.0 GB Uploaded
//			$bonus['points'] = $tengbupload_bonus;
//			$bonus['art'] = 'traffic';
//			$bonus['menge'] = 10737418240;
//			$bonus['name'] = $lang_mybonus['text_uploaded_three'];
//			$bonus['description'] = $lang_mybonus['text_uploaded_note'];
//			break;
//			}
//		case 4: {//Invite
//			$bonus['points'] = $oneinvite_bonus;
//			$bonus['art'] = 'invite';
//			$bonus['menge'] = 1;
//			$bonus['name'] = $lang_mybonus['text_buy_invite'];
//			$bonus['description'] = $lang_mybonus['text_buy_invite_note'];
//			break;
//			}
//		case 5: {//Custom Title
//			$bonus['points'] = $customtitle_bonus;
//			$bonus['art'] = 'title';
//			$bonus['menge'] = 0;
//			$bonus['name'] = $lang_mybonus['text_custom_title'];
//			$bonus['description'] = $lang_mybonus['text_custom_title_note'];
//			break;
//			}
//		case 6: {//VIP Status
//			$bonus['points'] = $vipstatus_bonus;
//			$bonus['art'] = 'class';
//			$bonus['menge'] = 0;
//			$bonus['name'] = $lang_mybonus['text_vip_status'];
//			$bonus['description'] = $lang_mybonus['text_vip_status_note'];
//			break;
//			}
//		case 7: {//Bonus Gift
//			$bonus['points'] = 25;
//			$bonus['art'] = 'gift_1';
//			$bonus['menge'] = 0;
//			$bonus['name'] = $lang_mybonus['text_bonus_gift'];
//			$bonus['description'] = $lang_mybonus['text_bonus_gift_note'];
//			if ($basictax_bonus || $taxpercentage_bonus){
//				$onehundredaftertax = 100 - $taxpercentage_bonus - $basictax_bonus;
//				$bonus['description'] .= "<br /><br />".$lang_mybonus['text_system_charges_receiver']."<b>".($basictax_bonus ? $basictax_bonus.$lang_mybonus['text_tax_bonus_point'].add_s($basictax_bonus).($taxpercentage_bonus ? $lang_mybonus['text_tax_plus'] : "") : "").($taxpercentage_bonus ? $taxpercentage_bonus.$lang_mybonus['text_percent_of_transfered_amount'] : "")."</b>".$lang_mybonus['text_as_tax'].$onehundredaftertax.$lang_mybonus['text_tax_example_note'];
//				}
//			break;
//			}
//		case 8: {
//			$bonus['points'] = $bonusnoadpoint_advertisement;
//			$bonus['art'] = 'noad';
//			$bonus['menge'] = $bonusnoadtime_advertisement * 86400;
//			$bonus['name'] = $bonusnoadtime_advertisement.$lang_mybonus['text_no_advertisements'];
//			$bonus['description'] = $lang_mybonus['text_no_advertisements_note'];
//			break;
//			}
//		case 9: {
//			$bonus['points'] = 1000;
//			$bonus['art'] = 'gift_2';
//			$bonus['menge'] = 0;
//			$bonus['name'] = $lang_mybonus['text_charity_giving'];
//			$bonus['description'] = $lang_mybonus['text_charity_giving_note'];
//			break;
//			}
//        case 10: {
//            $bonus['points'] = \App\Models\BonusLogs::getBonusForCancelHitAndRun();
//            $bonus['art'] = 'cancel_hr';
//            $bonus['menge'] = 0;
//            $bonus['name'] = $lang_mybonus['text_cancel_hr_title'];
//            $bonus['description'] = '<p>
//            <span style="">' . $lang_mybonus['text_cancel_hr_label'] . '</span>
//            <input type="number" name="hr_id" />
//        </p>';
//            break;
//        }
//		default: break;
//	}
//	return $bonus;
}

$allBonus = bonusarray();

if ($bonus_tweak == "disable" || $bonus_tweak == "disablesave")
	stderr($lang_mybonus['std_sorry'],$lang_mybonus['std_karma_system_disabled'].($bonus_tweak == "disablesave" ? "<b>".$lang_mybonus['std_points_active']."</b>" : ""),false);

$action = htmlspecialchars($_GET['action'] ?? '');
$do = htmlspecialchars($_GET['do'] ?? null);
unset($msg);
if (isset($do)) {
	if ($do == "upload")
	$msg = $lang_mybonus['text_success_upload'];
	elseif ($do == "invite")
	$msg = $lang_mybonus['text_success_invites'];
	elseif ($do == "vip")
	$msg =  $lang_mybonus['text_success_vip']."<b>".get_user_class_name(UC_VIP,false,false,true)."</b>".$lang_mybonus['text_success_vip_two'];
	elseif ($do == "vipfalse")
	$msg =  $lang_mybonus['text_no_permission'];
	elseif ($do == "title")
	$msg = $lang_mybonus['text_success_custom_title'];
	elseif ($do == "transfer")
	$msg =  $lang_mybonus['text_success_gift'];
	elseif ($do == "noad")
	$msg =  $lang_mybonus['text_success_no_ad'];
	elseif ($do == "charity")
	$msg =  $lang_mybonus['text_success_charity'];
    elseif ($do == "cancel_hr")
        $msg =  $lang_mybonus['text_success_cancel_hr'];
    elseif ($do == "buy_medal")
        $msg =  $lang_mybonus['text_success_buy_medal'];
    elseif ($do == "attendance_card")
        $msg =  $lang_mybonus['text_success_buy_attendance_card'];
	else
	$msg = '';
}
	stdhead($CURUSER['username'] . $lang_mybonus['head_karma_page']);

	$bonus = number_format($CURUSER['seedbonus'], 1);
if (!$action) {
	print("<table align=\"center\" width=\"97%\" border=\"1\" cellspacing=\"0\" cellpadding=\"3\">\n");
	print("<tr><td class=\"colhead\" colspan=\"4\" align=\"center\"><font class=\"big\">".$SITENAME.$lang_mybonus['text_karma_system']."</font></td></tr>\n");
	if ($msg)
	print("<tr><td align=\"center\" colspan=\"4\"><font class=\"striking\">". $msg ."</font></td></tr>");
?>
<tr><td class="text" align="center" colspan="4"><?php echo $lang_mybonus['text_exchange_your_karma']?><?php echo $bonus?><?php echo $lang_mybonus['text_for_goodies'] ?>
<br /><b><?php echo $lang_mybonus['text_no_buttons_note'] ?></b></td></tr>
<?php

print("<tr><td class=\"colhead\" align=\"center\">".$lang_mybonus['col_option']."</td>".
"<td class=\"colhead\" align=\"left\">".$lang_mybonus['col_description']."</td>".
"<td class=\"colhead\" align=\"center\">".$lang_mybonus['col_points']."</td>".
"<td class=\"colhead\" align=\"center\">".$lang_mybonus['col_trade']."</td>".
"</tr>");


for ($i=0; $i < count($allBonus); $i++)
{
	$bonusarray = $allBonus[$i];
	if (
	    ($bonusarray['art'] == 'gift_1' && $bonusgift_bonus == 'no')
        || ($bonusarray['art'] == 'noad' && ($enablead_advertisement == 'no' || $bonusnoad_advertisement == 'no'))
        || ($bonusarray['art'] == 'cancel_hr' && !\App\Models\HitAndRun::getIsEnabled())
    ) {
        continue;
    }

	print("<tr>");
	print("<form action=\"?action=exchange\" method=\"post\">");
	print("<td class=\"rowhead_center\"><input type=\"hidden\" name=\"option\" value=\"".$i."\" /><b>".($i + 1)."</b></td>");
	if ($bonusarray['art'] == 'title'){ //for Custom Title!
	    $otheroption_title = "<input type=\"text\" name=\"title\" style=\"width: 200px\" maxlength=\"30\" />";
	    print("<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name']."</h1>".$bonusarray['description']."<br /><br />".$lang_mybonus['text_enter_titile'].$otheroption_title.$lang_mybonus['text_click_exchange']."</td><td class=\"rowfollow\" align='center'>".number_format($bonusarray['points'])."</td>");
	}
	elseif ($bonusarray['art'] == 'gift_1'){  //for Give A Karma Gift
			$otheroption = "<table width=\"100%\"><tr><td class=\"embedded\"><b>".$lang_mybonus['text_username']."</b><input type=\"text\" name=\"username\" style=\"width: 200px\" maxlength=\"24\" /></td><td class=\"embedded\"><b>".$lang_mybonus['text_to_be_given']."</b><select name=\"bonusgift\" id=\"giftselect\" onchange=\"customgift();\"> <option value=\"25\"> 25</option><option value=\"50\"> 50</option><option value=\"100\"> 100</option> <option value=\"200\"> 200</option> <option value=\"300\"> 300</option> <option value=\"400\"> 400</option><option value=\"500\"> 500</option><option value=\"1000\" selected=\"selected\"> 1,000</option><option value=\"5000\"> 5,000</option><option value=\"10000\"> 10,000</option><option value=\"0\">".$lang_mybonus['text_custom']."</option></select><input type=\"text\" name=\"bonusgift\" id=\"giftcustom\" style='width: 80px' disabled=\"disabled\" />".$lang_mybonus['text_karma_points']."</td></tr><tr><td class=\"embedded\" colspan=\"2\"><b>".$lang_mybonus['text_message']."</b><input type=\"text\" name=\"message\" style=\"width: 400px\" maxlength=\"100\" /></td></tr></table>";
			print("<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name']."</h1>".$bonusarray['description']."<br /><br />".$lang_mybonus['text_enter_receiver_name']."<br />$otheroption</td><td class=\"rowfollow nowrap\" align='center'>".$lang_mybonus['text_min']."25<br />".$lang_mybonus['text_max']."10,000</td>");
	}
	elseif ($bonusarray['art'] == 'gift_2'){  //charity giving
			$otheroption = "<table width=\"100%\"><tr><td class=\"embedded\">".$lang_mybonus['text_ratio_below']."<select name=\"ratiocharity\"> <option value=\"0.1\"> 0.1</option><option value=\"0.2\"> 0.2</option><option value=\"0.3\" selected=\"selected\"> 0.3</option> <option value=\"0.4\"> 0.4</option> <option value=\"0.5\"> 0.5</option> <option value=\"0.6\"> 0.6</option><option value=\"0.7\"> 0.7</option><option value=\"0.8\"> 0.8</option></select>".$lang_mybonus['text_and_downloaded_above']." 10 GB</td><td class=\"embedded\"><b>".$lang_mybonus['text_to_be_given']."</b><select name=\"bonuscharity\" id=\"charityselect\" > <option value=\"1000\"> 1,000</option><option value=\"2000\"> 2,000</option><option value=\"3000\" selected=\"selected\"> 3000</option> <option value=\"5000\"> 5,000</option> <option value=\"8000\"> 8,000</option> <option value=\"10000\"> 10,000</option><option value=\"20000\"> 20,000</option><option value=\"50000\"> 50,000</option></select>".$lang_mybonus['text_karma_points']."</td></tr></table>";
			print("<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name']."</h1>".$bonusarray['description']."<br /><br />".$lang_mybonus['text_select_receiver_ratio']."<br />$otheroption</td><td class=\"rowfollow nowrap\" align='center'>".$lang_mybonus['text_min']."1,000<br />".$lang_mybonus['text_max']."50,000</td>");
	}
	else {  //for VIP or Upload
		print("<td class=\"rowfollow\" align='left'><h1>".$bonusarray['name']."</h1>".$bonusarray['description']."</td><td class=\"rowfollow\" align='center'>".number_format($bonusarray['points'])."</td>");
	}

	if($CURUSER['seedbonus'] >= $bonusarray['points'])
	{
		if ($bonusarray['art'] == 'gift_1'){
			print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_karma_gift']."\" /></td>");
		}
		elseif ($bonusarray['art'] == 'noad'){
			if ($enablenoad_advertisement == 'yes' && get_user_class() >= $noad_advertisement)
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_class_above_no_ad']."\" disabled=\"disabled\" /></td>");
			elseif (strtotime($CURUSER['noaduntil']) >= TIMENOW)
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_already_disabled']."\" disabled=\"disabled\" /></td>");
			elseif (get_user_class() < $bonusnoad_advertisement)
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".get_user_class_name($bonusnoad_advertisement,false,false,true).$lang_mybonus['text_plus_only']."\" disabled=\"disabled\" /></td>");
			else
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
		}
		elseif ($bonusarray['art'] == 'gift_2'){
			print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_charity_giving']."\" /></td>");
		}
		elseif($bonusarray['art'] == 'invite')
		{
			if(get_user_class() < $buyinvite_class)
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".get_user_class_name($buyinvite_class,false,false,true).$lang_mybonus['text_plus_only']."\" disabled=\"disabled\" /></td>");
			else
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
		}
		elseif ($bonusarray['art'] == 'class')
		{
			if (get_user_class() >= UC_VIP)
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['std_class_above_vip']."\" disabled=\"disabled\" /></td>");
			else
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
		}
		elseif ($bonusarray['art'] == 'title')
			print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
		elseif ($bonusarray['art'] == 'traffic')
		{
			if ($CURUSER['downloaded'] > 0){
				if ($CURUSER['uploaded'] > $dlamountlimit_bonus * 1073741824)//Uploaded amount reach limit
					$ratio = $CURUSER['uploaded']/$CURUSER['downloaded'];
				else $ratio = 0;
			}
			else $ratio = $ratiolimit_bonus + 1; //Ratio always above limit
			if ($ratiolimit_bonus > 0 && $ratio > $ratiolimit_bonus){
				print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['text_ratio_too_high']."\" disabled=\"disabled\" /></td>");
			}
			else print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
		} else {
            print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['submit_exchange']."\" /></td>");
        }
	}
	else
	{
		print("<td class=\"rowfollow\" align=\"center\"><input type=\"submit\" name=\"submit\" value=\"".$lang_mybonus['text_more_points_needed']."\" disabled=\"disabled\" /></td>");
	}
	print("</form>");
	print("</tr>");

}

print("</table><br />");
?>

<table width="97%" cellpadding="3">
<tr><td class="colhead" align="center"><font class="big"><?php echo $lang_mybonus['text_what_is_karma'] ?></font></td></tr>
<tr><td class="text" align="left">
<?php
print("<h1>".$lang_mybonus['text_get_by_seeding']."</h1>");
print("<ul>");
if ($perseeding_bonus > 0)
	print("<li>".$perseeding_bonus.$lang_mybonus['text_point'].add_s($perseeding_bonus).$lang_mybonus['text_for_seeding_torrent'].$maxseeding_bonus.$lang_mybonus['text_torrent'].add_s($maxseeding_bonus).")</li>");
print("<li>".$lang_mybonus['text_bonus_formula_one'].$tzero_bonus.$lang_mybonus['text_bonus_formula_two'].$nzero_bonus.$lang_mybonus['text_bonus_formula_three'].$bzero_bonus.$lang_mybonus['text_bonus_formula_four'].$l_bonus.$lang_mybonus['text_bonus_formula_five']."</li>");
if ($donortimes_bonus)
	print("<li>".$lang_mybonus['text_donors_always_get'].$donortimes_bonus.$lang_mybonus['text_times_of_bonus']."</li>");

print("</ul>");

//		$sqrtof2 = sqrt(2);
//		$logofpointone = log(0.1);
//		$valueone = $logofpointone / $tzero_bonus;
//		$pi = 3.141592653589793;
//		$valuetwo = $bzero_bonus * ( 2 / $pi);
//		$valuethree = $logofpointone / ($nzero_bonus - 1);
//		$timenow = strtotime(date("Y-m-d H:i:s"));
//		$sectoweek = 7*24*60*60;
//		$A = 0;
//		$count = 0;
//		$torrentres = sql_query("select torrents.id, torrents.added, torrents.size, torrents.seeders from torrents LEFT JOIN peers ON peers.torrent = torrents.id WHERE peers.userid = $CURUSER[id] AND peers.seeder ='yes' GROUP BY torrents.id")  or sqlerr(__FILE__, __LINE__);
//		while ($torrent = mysql_fetch_array($torrentres))
//		{
//			$weeks_alive = ($timenow - strtotime($torrent['added'])) / $sectoweek;
//			$gb_size = $torrent['size'] / 1073741824;
//			$temp = (1 - exp($valueone * $weeks_alive)) * $gb_size * (1 + $sqrtof2 * exp($valuethree * ($torrent['seeders'] - 1)));
//			$A += $temp;
//			$count++;
//		}
//		if ($count > $maxseeding_bonus)
//			$count = $maxseeding_bonus;
//		$all_bonus = $valuetwo * atan($A / $l_bonus) + ($perseeding_bonus * $count);

$seedBonusResult = calculate_seed_bonus($CURUSER['id']);
$all_bonus = $seedBonusResult['all_bonus'];
$A = $seedBonusResult['A'];

		$percent = $all_bonus * 100 / ($bzero_bonus + $perseeding_bonus * $maxseeding_bonus);
	print("<div align=\"center\">".$lang_mybonus['text_you_are_currently_getting'].round($all_bonus,3).$lang_mybonus['text_point'].add_s($all_bonus).$lang_mybonus['text_per_hour']." (A = ".round($A,1).")</div><table align=\"center\" border=\"0\" width=\"400\"><tr><td class=\"loadbarbg\" style='border: none; padding: 0px;'>");

	if ($percent <= 30) $loadpic = "loadbarred";
	elseif ($percent <= 60) $loadpic = "loadbaryellow";
	else $loadpic = "loadbargreen";
	$width = $percent * 4;
	print("<img class=\"".$loadpic."\" src=\"pic/trans.gif\" style=\"width: ".$width."px;\" alt=\"".$percent."%\" /></td></tr></table>");

$factor = get_setting('bonus.harem_addition');
$addition = calculate_harem_addition($CURUSER['id']);
$totalBonus = number_format($all_bonus + $addition * $factor, 3);
$summaryTable = '<table cellspacing="4" cellpadding="4" style="width: 50%"><tbody>';
$summaryTable .= '<tr style="font-weight: bold"><td>'.$lang_mybonus['reward_type'].'</td><td>'.$lang_mybonus['bonus_base'].'</td><td>'.$lang_mybonus['addition'].'</td><td>'.$lang_mybonus['got_bonus'].'</td><td>'.$lang_mybonus['total'].'</td></tr>';
$summaryTable .= sprintf(
    '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td rowspan="2">%s</td></tr>',
    $lang_mybonus['reward_type_basic'],
    round($all_bonus,3),
    '-',
    round($all_bonus,3),
    $totalBonus
);
if ($factor > 0) {
    $summaryTable .= sprintf(
        '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
        $lang_mybonus['reward_type_harem_addition'],
        number_format($addition, 3),
        number_format($factor * 100, 2) . '%',
        number_format($addition * $factor, 3)
    );
}
$summaryTable .= '</tbody></table>';

print '<div style="display: flex;justify-content: center;margin-top: 20px;">'.$summaryTable.'</div>';

print("<h1>".$lang_mybonus['text_other_things_get_bonus']."</h1>");
print("<ul>");
if ($uploadtorrent_bonus > 0)
	print("<li>".$lang_mybonus['text_upload_torrent'].$uploadtorrent_bonus.$lang_mybonus['text_point'].add_s($uploadtorrent_bonus)."</li>");
if ($uploadsubtitle_bonus > 0)
	print("<li>".$lang_mybonus['text_upload_subtitle'].$uploadsubtitle_bonus.$lang_mybonus['text_point'].add_s($uploadsubtitle_bonus)."</li>");
if ($starttopic_bonus > 0)
	print("<li>".$lang_mybonus['text_start_topic'].$starttopic_bonus.$lang_mybonus['text_point'].add_s($starttopic_bonus)."</li>");
if ($makepost_bonus > 0)
	print("<li>".$lang_mybonus['text_make_post'].$makepost_bonus.$lang_mybonus['text_point'].add_s($makepost_bonus)."</li>");
if ($addcomment_bonus > 0)
	print("<li>".$lang_mybonus['text_add_comment'].$addcomment_bonus.$lang_mybonus['text_point'].add_s($addcomment_bonus)."</li>");
if ($pollvote_bonus > 0)
	print("<li>".$lang_mybonus['text_poll_vote'].$pollvote_bonus.$lang_mybonus['text_point'].add_s($pollvote_bonus)."</li>");
if ($offervote_bonus > 0)
	print("<li>".$lang_mybonus['text_offer_vote'].$offervote_bonus.$lang_mybonus['text_point'].add_s($offervote_bonus)."</li>");
if ($funboxvote_bonus > 0)
	print("<li>".$lang_mybonus['text_funbox_vote'].$funboxvote_bonus.$lang_mybonus['text_point'].add_s($funboxvote_bonus)."</li>");
if ($ratetorrent_bonus > 0)
	print("<li>".$lang_mybonus['text_rate_torrent'].$ratetorrent_bonus.$lang_mybonus['text_point'].add_s($ratetorrent_bonus)."</li>");
if ($saythanks_bonus > 0)
	print("<li>".$lang_mybonus['text_say_thanks'].$saythanks_bonus.$lang_mybonus['text_point'].add_s($saythanks_bonus)."</li>");
if ($receivethanks_bonus > 0)
	print("<li>".$lang_mybonus['text_receive_thanks'].$receivethanks_bonus.$lang_mybonus['text_point'].add_s($receivethanks_bonus)."</li>");
if ($adclickbonus_advertisement > 0)
	print("<li>".$lang_mybonus['text_click_on_ad'].$adclickbonus_advertisement.$lang_mybonus['text_point'].add_s($adclickbonus_advertisement)."</li>");
if ($prolinkpoint_bonus > 0)
	print("<li>".$lang_mybonus['text_promotion_link_clicked'].$prolinkpoint_bonus.$lang_mybonus['text_point'].add_s($prolinkpoint_bonus)."</li>");
if ($funboxreward_bonus > 0)
	print("<li>".$lang_mybonus['text_funbox_reward']."</li>");
print($lang_mybonus['text_howto_get_karma_four']);
if ($ratiolimit_bonus > 0)
	print("<li>".$lang_mybonus['text_user_with_ratio_above'].$ratiolimit_bonus.$lang_mybonus['text_and_uploaded_amount_above'].$dlamountlimit_bonus.$lang_mybonus['text_cannot_exchange_uploading']."</li>");
print($lang_mybonus['text_howto_get_karma_five'].$uploadtorrent_bonus.$lang_mybonus['text_point'].add_s($uploadtorrent_bonus).$lang_mybonus['text_howto_get_karma_six']);
?>
</td></tr></table>
<?php
}

// Bonus exchange
if ($action == "exchange") {
	if (isset($_POST["userid"]) || isset($_POST["points"]) || isset($_POST["bonus"]) || isset($_POST["art"]) || !isset($_POST['option']) || !isset($allBonus[$_POST['option']])){
		write_log("User " . $CURUSER["username"] . "," . $CURUSER["ip"] . " is trying to cheat at bonus system",'mod');
		die($lang_mybonus['text_cheat_alert']);
	}
	$option = intval($_POST["option"] ?? 0);
	$bonusarray = $allBonus[$option];
	$points = $bonusarray['points'];
	$userid = $CURUSER['id'];
	$art = $bonusarray['art'];

	$bonuscomment = $CURUSER['bonuscomment'];
	$seedbonus=$CURUSER['seedbonus']-$points;

	if($CURUSER['seedbonus'] >= $points) {
        $bonusRep = new \App\Repositories\BonusRepository();
		//=== trade for upload
		if($art == "traffic") {
			if ($CURUSER['uploaded'] > $dlamountlimit_bonus * 1073741824)//uploaded amount reach limit
			$ratio = $CURUSER['uploaded']/$CURUSER['downloaded'];
			else $ratio = 0;
			if ($ratiolimit_bonus > 0 && $ratio > $ratiolimit_bonus)
				die($lang_mybonus['text_cheat_alert']);
			else {
			$upload = $CURUSER['uploaded'];
			$up = $upload + $bonusarray['menge'];
//			$bonuscomment = date("Y-m-d") . " - " .$points. " Points for upload bonus.\n " .$bonuscomment;
//			sql_query("UPDATE users SET uploaded = ".sqlesc($up).", seedbonus = seedbonus - $points, bonuscomment = ".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_EXCHANGE_UPLOAD, $points. " Points for upload bonus.", ['uploaded' => $up]);
			nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=upload");
			}
		}
		//=== trade for one month VIP status ***note "SET class = '10'" change "10" to whatever your VIP class number is
		elseif($art == "class") {
			if (get_user_class() >= UC_VIP) {
				stdmsg($lang_mybonus['std_no_permission'],$lang_mybonus['std_class_above_vip'], 0);
				stdfoot();
				die;
			}
			$vip_until = date("Y-m-d H:i:s",(strtotime(date("Y-m-d H:i:s")) + 28*86400));
//			$bonuscomment = date("Y-m-d") . " - " .$points. " Points for 1 month VIP Status.\n " .htmlspecialchars($bonuscomment);
//			sql_query("UPDATE users SET class = '".UC_VIP."', vip_added = 'yes', vip_until = ".sqlesc($vip_until).", seedbonus = seedbonus - $points, bonuscomment=".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_BUY_VIP, $points. " Points for 1 month VIP Status.", ['class' => UC_VIP, 'vip_added' => 'yes', 'vip_until' => $vip_until]);
			nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=vip");
		}
		//=== trade for invites
		elseif($art == "invite") {
			if(get_user_class() < $buyinvite_class)
				die(get_user_class_name($buyinvite_class,false,false,true).$lang_mybonus['text_plus_only']);
			$invites = $CURUSER['invites'];
			$inv = $invites+$bonusarray['menge'];
//			$bonuscomment = date("Y-m-d") . " - " .$points. " Points for invites.\n " .htmlspecialchars($bonuscomment);
//			sql_query("UPDATE users SET invites = ".sqlesc($inv).", seedbonus = seedbonus - $points, bonuscomment=".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_EXCHANGE_INVITE, $points. " Points for invites.", ['invites' => $inv, ]);
			nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=invite");
		}
		//=== trade for special title
		/**** the $words array are words that you DO NOT want the user to have... use to filter "bad words" & user class...
		the user class is just for show, but what the hell tongue.gif Add more or edit to your liking.
		*note if they try to use a restricted word, they will recieve the special title "I just wasted my karma" *****/
		elseif($art == "title") {
			//===custom title
			$title = $_POST["title"];
			$words = array("fuck", "shit", "pussy", "cunt", "nigger", "Staff Leader","SysOp", "Administrator","Moderator","Uploader","Retiree","VIP","Nexus Master","Ultimate User","Extreme User","Veteran User","Insane User","Crazy User","Elite User","Power User","User","Peasant","Champion");
			$title = str_replace($words, $lang_mybonus['text_wasted_karma'], $title);
//			$bonuscomment = date("Y-m-d") . " - " .$points. " Points for custom title. Old title is ".htmlspecialchars(trim($CURUSER["title"]))." and new title is $title\n " .htmlspecialchars($bonuscomment);
//			sql_query("UPDATE users SET title = ".sqlesc($title).", seedbonus = seedbonus - $points, bonuscomment = ".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
            $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_CUSTOM_TITLE, $points. " Points for custom title. Old title is ".htmlspecialchars(trim($CURUSER["title"]))." and new title is $title.", ['title' => $title, ]);
			nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=title");
		}
		elseif($art == "noad" && $enablead_advertisement == 'yes' && $enablebonusnoad_advertisement == 'yes') {
			if (($enablenoad_advertisement == 'yes' && get_user_class() >= $noad_advertisement) || strtotime($CURUSER['noaduntil']) >= TIMENOW || get_user_class() < $bonusnoad_advertisement)
				die($lang_mybonus['text_cheat_alert']);
			else{
				$noaduntil = date("Y-m-d H:i:s",(TIMENOW + $bonusarray['menge']));
//				$bonuscomment = date("Y-m-d") . " - " .$points. " Points for ".$bonusnoadtime_advertisement." days without ads.\n " .htmlspecialchars($bonuscomment);
//				sql_query("UPDATE users SET noad='yes', noaduntil='".$noaduntil."', seedbonus = seedbonus - $points, bonuscomment = ".sqlesc($bonuscomment)." WHERE id=".sqlesc($userid));
                $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_NO_AD, $points. " Points for ".$bonusnoadtime_advertisement." days without ads.", ['noad' => 'yes', 'noaduntil' => $noaduntil]);
				nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=noad");
			}
		}
		elseif($art == 'gift_2') // charity giving
		{
			$points = intval($_POST["bonuscharity"] ?? 0);
			if ($points < 1000 || $points > 50000){
				stdmsg($lang_mybonus['text_error'], $lang_mybonus['bonus_amount_not_allowed_two'], 0);
				stdfoot();
				die();
			}
			$ratiocharity = $_POST["ratiocharity"];
			if ($ratiocharity < 0.1 || $ratiocharity > 0.8){
				stdmsg($lang_mybonus['text_error'], $lang_mybonus['bonus_ratio_not_allowed']);
				stdfoot();
				die();
			}
			if($CURUSER['seedbonus'] >= $points) {
				$points2= number_format($points,1);
//				$bonuscomment = date("Y-m-d") . " - " .$points2. " Points as charity to users with ratio below ".htmlspecialchars(trim($ratiocharity)).".\n " .htmlspecialchars($bonuscomment);
				$charityReceiverCount = get_row_count("users", "WHERE enabled='yes' AND 10737418240 < downloaded AND $ratiocharity > uploaded/downloaded");
				if ($charityReceiverCount) {
//					sql_query("UPDATE users SET seedbonus = seedbonus - $points, charity = charity + $points, bonuscomment = ".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
                    $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_GIFT_TO_LOW_SHARE_RATIO, $points. " Points as charity to users with ratio below ".htmlspecialchars(trim($ratiocharity)).".", ['charity' => \Nexus\Database\NexusDB::raw("charity + $points"), ]);
					$charityPerUser = $points/$charityReceiverCount;
					sql_query("UPDATE users SET seedbonus = seedbonus + $charityPerUser WHERE enabled='yes' AND 10737418240 < downloaded AND $ratiocharity > uploaded/downloaded") or sqlerr(__FILE__, __LINE__);
					nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=charity");
				}
				else
				{
					stdmsg($lang_mybonus['std_sorry'], $lang_mybonus['std_no_users_need_charity']);
					stdfoot();
					die;
				}
			}
		}
		elseif($art == "gift_1" && $bonusgift_bonus == 'yes') {
			//=== trade for giving the gift of karma
			$points = $_POST["bonusgift"];
			$message = $_POST["message"];
			//==gift for peeps with no more options
			$usernamegift = sqlesc(trim($_POST["username"]));
			$res = sql_query("SELECT id, bonuscomment FROM users WHERE username=" . $usernamegift);
			$arr = mysql_fetch_assoc($res);
			$useridgift = $arr['id'];
			$userseedbonus = $arr['seedbonus'];
			$receiverbonuscomment = $arr['bonuscomment'];
			if ($points < 25 || $points > 10000) {
				//write_log("User " . $CURUSER["username"] . "," . $CURUSER["ip"] . " is hacking bonus system",'mod');
				stdmsg($lang_mybonus['text_error'], $lang_mybonus['bonus_amount_not_allowed']);
				stdfoot();
				die();
			}
			if($CURUSER['seedbonus'] >= $points) {
				$points2= number_format($points,1);
//				$bonuscomment = date("Y-m-d") . " - " .$points2. " Points as gift to ".htmlspecialchars(trim($_POST["username"])).".\n " .htmlspecialchars($bonuscomment);

				$aftertaxpoint = $points;
				if ($taxpercentage_bonus)
					$aftertaxpoint -= $aftertaxpoint * $taxpercentage_bonus * 0.01;
				if ($basictax_bonus)
					$aftertaxpoint -= $basictax_bonus;

				$points2receiver = number_format($aftertaxpoint,1);
				$newreceiverbonuscomment = date("Y-m-d") . " + " .$points2receiver. " Points (after tax) as a gift from ".($CURUSER["username"]).".\n " .htmlspecialchars($receiverbonuscomment);
				if ($userid==$useridgift){
					stdmsg($lang_mybonus['text_huh'], $lang_mybonus['text_karma_self_giving_warning'], 0);
					stdfoot();
					die;
				}
				if (!$useridgift){
					stdmsg($lang_mybonus['text_error'], $lang_mybonus['text_receiver_not_exists'], 0);
					stdfoot();
					die;
				}

//				sql_query("UPDATE users SET seedbonus = seedbonus - $points, bonuscomment = ".sqlesc($bonuscomment)." WHERE id = ".sqlesc($userid)) or sqlerr(__FILE__, __LINE__);
                $bonusRep->consumeUserBonus($CURUSER['id'], $points, \App\Models\BonusLogs::BUSINESS_TYPE_GIFT_TO_SOMEONE, $points2 . " Points as gift to ".htmlspecialchars(trim($_POST["username"])));
				sql_query("UPDATE users SET seedbonus = seedbonus + $aftertaxpoint, bonuscomment = ".sqlesc($newreceiverbonuscomment)." WHERE id = ".sqlesc($useridgift));

				//===send message
				$subject = sqlesc($lang_mybonus_target[get_user_lang($useridgift)]['msg_someone_loves_you']);
				$added = sqlesc(date("Y-m-d H:i:s"));
				$msg = $lang_mybonus_target[get_user_lang($useridgift)]['msg_you_have_been_given'].$points2.$lang_mybonus_target[get_user_lang($useridgift)]['msg_after_tax'].$points2receiver.$lang_mybonus_target[get_user_lang($useridgift)]['msg_karma_points_by'].$CURUSER['username'];
				if ($message)
					$msg .= "\n".$lang_mybonus_target[get_user_lang($useridgift)]['msg_personal_message_from'].$CURUSER['username'].$lang_mybonus_target[get_user_lang($useridgift)]['msg_colon'].$message;
				$msg = sqlesc($msg);
				sql_query("INSERT INTO messages (sender, subject, receiver, msg, added) VALUES(0, $subject, $useridgift, $msg, $added)") or sqlerr(__FILE__, __LINE__);
				$usernamegift = unesc($_POST["username"]);
                nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=transfer");
			}
			else{
				print("<table width=\"97%\"><tr><td class=\"colhead\" align=\"left\" colspan=\"2\"><h1>".$lang_mybonus['text_oups']."</h1></td></tr>");
				print("<tr><td align=\"left\"></td><td align=\"left\">".$lang_mybonus['text_not_enough_karma']."<br /><br /></td></tr></table>");
			}
		} elseif ($art == 'cancel_hr') {
		    if (empty($_POST['hr_id'])) {
		        stderr("Error","Invalid H&R ID: " . ($_POST['hr_id'] ?? ''), false, false);
            }
		    try {
		        $bonusRep->consumeToCancelHitAndRun($userid, $_POST['hr_id']);
                nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=cancel_hr");
            } catch (\Exception $exception) {
		        do_log($exception->getMessage(), 'error');
		        stderr('Error', "Something wrong...", false, false);
            }
        } elseif ($art == 'buy_medal') {
            if (empty($_POST['medal_id'])) {
                stderr("Error","Invalid Medal ID: " . ($_POST['medal_id'] ?? ''), false, false);
            }
            try {
                $bonusRep->consumeToBuyMedal($userid, $_POST['medal_id']);
                nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=buy_medal");
            } catch (\Exception $exception) {
                do_log($exception->getMessage(), 'error');
                stderr('Error', "Something wrong...", false, false);
            }
        } elseif ($art == 'attendance_card') {
            try {
                $bonusRep->consumeToBuyAttendanceCard($userid);
                nexus_redirect("" . get_protocol_prefix() . "$BASEURL/mybonus.php?do=attendance_card");
            } catch (\Exception $exception) {
                do_log($exception->getMessage(), 'error');
                stderr('Error', "Something wrong...", false, false);
            }
        }
	}
}
stdfoot();
?>

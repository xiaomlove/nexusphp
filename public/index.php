<?php
require "../include/bittorrent.php";
dbconn(true);
require_once(get_langfile_path());
loggedinorreturn(true);
$userid = $CURUSER["id"];
if ($_SERVER["REQUEST_METHOD"] == "POST")
{
	if ($showpolls_main == "yes")
	{
		$choice = $_POST["choice"];
		if ($CURUSER && $choice != "" && $choice < 256 && $choice == floor($choice))
		{
			$res = sql_query("SELECT * FROM polls ORDER BY added DESC LIMIT 1") or sqlerr(__FILE__, __LINE__);
			$arr = mysql_fetch_assoc($res) or die($lang_index['std_no_poll']);
			$pollid = $arr["id"];

			$hasvoted = get_row_count("pollanswers","WHERE pollid=".sqlesc($pollid)." && userid=".sqlesc($CURUSER["id"]));
			if ($hasvoted)
				stderr($lang_index['std_error'],$lang_index['std_duplicate_votes_denied']);
			sql_query("INSERT INTO pollanswers VALUES(0, ".sqlesc($pollid).", ".sqlesc($CURUSER["id"]).", ".sqlesc($choice).")") or sqlerr(__FILE__, __LINE__);
			$Cache->delete_value('current_poll_content');
			$Cache->delete_value('current_poll_result', true);
			if (mysql_affected_rows() != 1)
			stderr($lang_index['std_error'], $lang_index['std_vote_not_counted']);
			//add karma
			KPS("+",$pollvote_bonus,$userid);

			header("Location: " . get_protocol_prefix() . "$BASEURL/");
			die;
		}
		else
		stderr($lang_index['std_error'], $lang_index['std_option_unselected']);
	}
}
stdhead($lang_index['head_home']);
begin_main_frame();

// ------------- start: recent news ------------------//
print("<h2>".$lang_index['text_recent_news'].(get_user_class() >= $newsmanage_class ? " - <font class=\"small\">[<a class=\"altlink\" href=\"news.php\"><b>".$lang_index['text_news_page']."</b></a>]</font>" : "")."</h2>");

$Cache->new_page('recent_news', 86400, true);
if (!$Cache->get_page()){
$res = sql_query("SELECT * FROM news ORDER BY added DESC LIMIT ".(int)$maxnewsnum_main) or sqlerr(__FILE__, __LINE__);
if (mysql_num_rows($res) > 0)
{
	$Cache->add_whole_row();
	print("<table width=\"100%\"><tr><td class=\"text\"><div style=\"margin-left: 16pt;\">\n");
	$Cache->end_whole_row();
	$news_flag = 0;
	while($array = mysql_fetch_array($res))
	{
		$Cache->add_row();
		$Cache->add_part();
		if ($news_flag < 1) {
			print("<a href=\"javascript: klappe_news('a".$array['id']."')\"><img class=\"minus\" src=\"pic/trans.gif\" id=\"pica".$array['id']."\" alt=\"Show/Hide\" title=\"".$lang_index['title_show_or_hide']."\" />&nbsp;" . date("Y.m.d",strtotime($array['added'])) . " - " ."<b>". $array['title'] . "</b></a>");
			print("<div id=\"ka".$array['id']."\" style=\"display: block;\"> ".format_comment($array["body"],0)." </div> ");
			$news_flag = $news_flag + 1;
		}
		else
		{
			print("<a href=\"javascript: klappe_news('a".$array['id']."')\"><br /><img class=\"plus\" src=\"pic/trans.gif\" id=\"pica".$array['id']."\" alt=\"Show/Hide\" title=\"".$lang_index['title_show_or_hide']."\" />&nbsp;" . date("Y.m.d",strtotime($array['added'])) . " - " ."<b>". $array['title'] . "</b></a>");
			print("<div id=\"ka".$array['id']."\" style=\"display: none;\"> ".format_comment($array["body"],0)." </div> ");
		}
		$Cache->end_part();
		$Cache->add_part();
		print("  &nbsp; [<a class=\"faqlink\" href=\"news.php?action=edit&amp;newsid=" . $array['id'] . "\"><b>".$lang_index['text_e']."</b></a>]");
		print(" [<a class=\"faqlink\" href=\"news.php?action=delete&amp;newsid=" . $array['id'] . "\"><b>".$lang_index['text_d']."</b></a>]");
		$Cache->end_part();
		$Cache->end_row();
	}
	$Cache->break_loop();
	$Cache->add_whole_row();
	print("</div></td></tr></table>\n");
	$Cache->end_whole_row();
}
	$Cache->cache_page();
}
echo $Cache->next_row();
while($Cache->next_row()){
	echo $Cache->next_part();
	if (get_user_class() >= $newsmanage_class)
	echo $Cache->next_part();
}
echo $Cache->next_row();
// ------------- end: recent news ------------------//
// ------------- start: hot and classic movies ------------------//
//displayHotAndClassic();
// ------------- end: hot and classic movies ------------------//
// ------------- start: funbox ------------------//
if ($showfunbox_main == "yes" && (!isset($CURUSER) || $CURUSER['showfb'] == "yes")){
	// Get the newest fun stuff
	if (!$row = $Cache->get_value('current_fun_content')){
		$result = sql_query("SELECT fun.*, IF(ADDTIME(added, '1 0:0:0') < NOW(),true,false) AS neednew FROM fun WHERE status != 'banned' AND status != 'dull' ORDER BY added DESC LIMIT 1") or sqlerr(__FILE__,__LINE__);
		$row = mysql_fetch_array($result);
		$Cache->cache_value('current_fun_content', $row, 1043);
	}
	if (!$row) //There is no funbox item
	{
		print("<h2>".$lang_index['text_funbox'].(get_user_class() >= $newfunitem_class ? "<font class=\"small\"> - [<a class=\"altlink\" href=\"fun.php?action=new\"><b>".$lang_index['text_new_fun']."</b></a>]</font>" : "")."</h2>");
	}
	else
	{
	$totalvote = $Cache->get_value('current_fun_vote_count');
	if ($totalvote == ""){
		$totalvote = get_row_count("funvotes", "WHERE funid = ".sqlesc($row['id']));
		$Cache->cache_value('current_fun_vote_count', $totalvote, 756);
	}
	$funvote = $Cache->get_value('current_fun_vote_funny_count');
	if ($funvote == ""){
		$funvote = get_row_count("funvotes", "WHERE funid = ".sqlesc($row['id'])." AND vote='fun'");
		$Cache->cache_value('current_fun_vote_funny_count', $funvote, 756);
	}
//check whether current user has voted
	$funvoted = get_row_count("funvotes", "WHERE funid = ".sqlesc($row['id'])." AND userid=".sqlesc($CURUSER['id']));

	print ("<h2>".$lang_index['text_funbox']);
	if ($CURUSER)
	{
		print("<font class=\"small\">".(get_user_class() >= $log_class ? " - [<a class=\"altlink\" href=\"log.php?action=funbox\"><b>".$lang_index['text_more_fun']."</b></a>]": "").($row['neednew'] && get_user_class() >= $newfunitem_class ? " - [<a class=altlink href=\"fun.php?action=new\"><b>".$lang_index['text_new_fun']."</b></a>]" : "" ).( ($CURUSER['id'] == $row['userid'] || get_user_class() >= $funmanage_class) ? " - [<a class=\"altlink\" href=\"fun.php?action=edit&amp;id=".$row['id']."&amp;returnto=index.php\"><b>".$lang_index['text_edit']."</b></a>]" : "").(get_user_class() >= $funmanage_class ? " - [<a class=\"altlink\" href=\"fun.php?action=delete&amp;id=".$row['id']."&amp;returnto=index.php\"><b>".$lang_index['text_delete']."</b></a>] - [<a class=\"altlink\" href=\"fun.php?action=ban&amp;id=".$row['id']."&amp;returnto=index.php\"><b>".$lang_index['text_ban']."</b></a>]" : "")."</font>");
	}
	print("</h2>");

	print("<table width=\"100%\"><tr><td class=\"text\">");
	print("<iframe src=\"fun.php?action=view\" width='100%' height='300' frameborder='0' name='funbox' marginwidth='0' marginheight='0'></iframe><br /><br />\n");

	if ($CURUSER)
	{
		$funonclick = " onclick=\"funvote(".$row['id'].",'fun'".")\"";
		$dullonclick = " onclick=\"funvote(".$row['id'].",'dull'".")\"";
		print("<span id=\"funvote\"><b>".$funvote."</b>".$lang_index['text_out_of'].$totalvote.$lang_index['text_people_found_it'].($funvoted ? "" : "<font class=\"striking\">".$lang_index['text_your_opinion']."</font>&nbsp;&nbsp;<input type=\"button\" class='btn' name='fun' id='fun' ".$funonclick." value=\"".$lang_index['submit_fun']."\" />&nbsp;<input type=\"button\" class='btn' name='dull' id='dull' ".$dullonclick." value=\"".$lang_index['submit_dull']."\" />")."</span><span id=\"voteaccept\" style=\"display: none;\">".$lang_index['text_vote_accepted']."</span>");
	}
	print("</td></tr></table>");
	}
}
// ------------- end: funbox ------------------//
// ------------- start: shoutbox ------------------//
if ($showshoutbox_main == "yes") {
?>
<h2><?php echo $lang_index['text_shoutbox'] ?> - <font class="small"><?php echo $lang_index['text_auto_refresh_after']?></font><font class='striking' id="countdown"></font><font class="small"><?php echo $lang_index['text_seconds']?></font></h2>
<?php
	print("<table width=\"100%\"><tr><td class=\"text\">\n");
	print("<iframe src='shoutbox.php?type=shoutbox' width='100%' height='180' frameborder='0' name='sbox' marginwidth='0' marginheight='0'></iframe><br /><br />\n");
	print("<form action='shoutbox.php' method='get' target='sbox' name='shbox'>\n");
    print('<div style="display: flex">');
	print("<label for='shbox_text'>".$lang_index['text_message']."</label><input type='text' name='shbox_text' id='shbox_text' size='100' style='flex-grow: 1; border: 1px solid gray;' />  <input type='submit' id='hbsubmit' class='btn' name='shout' value=\"".$lang_index['sumbit_shout']."\" />");
	if ($CURUSER['hidehb'] != 'yes' && $showhelpbox_main =='yes')
		print("<input type='submit' class='btn' name='toguest' value=\"".$lang_index['sumbit_to_guest']."\" />");
	print("<input type='reset' class='btn' value=\"".$lang_index['submit_clear']."\" /> <input type='hidden' name='sent' value='yes' /><input type='hidden' name='type' value='shoutbox' />");
	print('</div>');
    print(smile_row("shbox","shbox_text"));
	print("</form></td></tr></table>");
}
// ------------- end: shoutbox ------------------//
// ------------- start: latest forum posts ------------------//

if ($showlastxforumposts_main == "yes" && $CURUSER)
{
	$res = sql_query("SELECT posts.id AS pid, posts.userid AS userpost, posts.added, topics.id AS tid, topics.subject, topics.forumid, topics.views, forums.name FROM posts, topics, forums WHERE posts.topicid = topics.id AND topics.forumid = forums.id AND minclassread <=" . sqlesc(get_user_class()) . " ORDER BY posts.id DESC LIMIT 5") or sqlerr(__FILE__,__LINE__);
	if(mysql_num_rows($res) != 0)
	{
		print("<h2>".$lang_index['text_last_five_posts']."</h2>");
		print("<table width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\"><tr><td class=\"colhead\" width=\"100%\" align=\"left\">".$lang_index['col_topic_title']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_view']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_author']."</td><td class=\"colhead\" align=\"left\">".$lang_index['col_posted_at']."</td></tr>");

		while ($postsx = mysql_fetch_assoc($res))
		{
			print("<tr><td><a href=\"forums.php?action=viewtopic&amp;topicid=".$postsx["tid"]."&amp;page=p".$postsx["pid"]."#pid".$postsx["pid"]."\"><b>".htmlspecialchars($postsx["subject"])."</b></a><br />".$lang_index['text_in']."<a href=\"forums.php?action=viewforum&amp;forumid=".$postsx["forumid"]."\">".htmlspecialchars($postsx["name"])."</a></td><td align=\"center\">".$postsx["views"]."</td><td align=\"center\">" . get_username($postsx["userpost"]) ."</td><td>".gettime($postsx["added"])."</td></tr>");
		}
		print("</table>");
	}
}

// ------------- end: latest forum posts ------------------//
// ------------- start: latest torrents ------------------//

if ($showlastxtorrents_main == "yes") {
		$result = sql_query("SELECT id,name,small_descr,leechers,seeders FROM torrents where visible='yes' ORDER BY id DESC LIMIT 5") or sqlerr(__FILE__, __LINE__);
		if(mysql_num_rows($result) != 0 )
		{
			print ("<h2>".$lang_index['text_last_five_torrent']."</h2>");
			print ("<table width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\"><tr><td class=\"colhead\" width=\"100%\">".$lang_index['col_name']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_seeder']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_leecher']."</td></tr>");

			while( $row = mysql_fetch_assoc($result) )
			{
				print ("<tr><a href=\"details.php?id=". $row['id'] ."&amp;hit=1\"><td><a href=\"details.php?id=". $row['id'] ."&amp;hit=1\"><b>" . htmlspecialchars($row['name']) . "</b><br/>" . htmlspecialchars($row['small_descr']) ."</td></a><td align=\"center\">" . $row['seeders'] . "</td><td align=\"center\">" . $row['leechers'] . "</td></tr>");
			}
			print ("</table>");
		}
}
// ------------- end: latest torrents ------------------//

// ------------- start: top uploader ------------------//

if (get_setting('main.show_top_uploader') == "yes") {
    $topUploaderBaseQuery = \App\Models\Torrent::query()
        ->selectRaw("owner, count(*) as counts")
        ->groupBy('owner')
        ->orderBy("counts", "desc")
        ->take(10);
    $userStatResult = \Nexus\Database\NexusDB::remember("index_top_uploader_all", 60, function () use ($topUploaderBaseQuery) {
        return (clone $topUploaderBaseQuery)->get();
    });
    if($userStatResult->isNotEmpty())
    {
        \Nexus\Nexus::css('.tr-top-uploader-tab>td {cursor: pointer}', 'footer', false);
        $toggleTimeRangeJs = <<<JS
jQuery(".tr-top-uploader-tab").on("click", "td", function () {
    let _this = jQuery(this)
    if (_this.hasClass("colhead")) {
        return
    }
    _this.parent().children().removeClass("colhead")
    _this.addClass("colhead")
    jQuery(".top-uploader").hide()
    jQuery("." + _this.attr("data-table")).fadeIn()

})
JS;
        \Nexus\Nexus::js($toggleTimeRangeJs, "footer", false);
        print ("<h2>".$lang_index['top_uploader_title']."</h2>");
        print("<table width='100%'><tr class='tr-top-uploader-tab' title='{$lang_index['top_uploader_toggle_time_range_tab']}'><td class='colhead' align='center' data-table='top-uploader-recently'>{$lang_index['top_uploader_toggle_time_range_recently']}</td><td align='center' data-table='top-uploader-all'>{$lang_index['top_uploader_toggle_time_range_all']}</td></tr></table>");

        $userTorrentCounts = $userStatResult->pluck('counts', 'owner');
        $uidArr = $userStatResult->pluck('owner')->toArray();
        $result = \App\Models\User::query()->whereIn('id', $uidArr)->orderByRaw(sprintf("field(id,%s)", implode(',', $uidArr)))->get(['id', 'username']);
        print ("<table class='top-uploader top-uploader-all' width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\" style='display: none'><tr><td class=\"colhead\" width=\"\">".$lang_index['col_author']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_counts']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_ranking']."</td></tr>");
        foreach ($result as $ranking => $row)
        {
            print ("<tr><td>" . get_username($row->id) . "</td><td align=\"center\">" . $userTorrentCounts->get($row->id, 0) . "</td><td align=\"center\">" . ($ranking + 1) . "</td></tr>");
        }
        print ("</table>");

        $userStatResult = \Nexus\Database\NexusDB::remember("index_top_uploader_recently", 60, function () use ($topUploaderBaseQuery) {
            return (clone $topUploaderBaseQuery)->where('added', '>=', \Carbon\Carbon::today()->subDays(30))->get();
        });
        $userTorrentCounts = $userStatResult->pluck('counts', 'owner');
        $uidArr = $userStatResult->pluck('owner')->toArray() ?: [0];
        $result = \App\Models\User::query()->whereIn('id', $uidArr)->orderByRaw(sprintf("field(id,%s)", implode(',', $uidArr)))->get(['id', 'username']);
        print ("<table class='top-uploader top-uploader-recently' width=\"100%\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\"><tr><td class=\"colhead\" width=\"\">".$lang_index['col_author']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_counts']."</td><td class=\"colhead\" align=\"center\">".$lang_index['col_ranking']."</td></tr>");
        foreach ($result as $ranking => $row)
        {
            print ("<tr><td>" . get_username($row->id) . "</td><td align=\"center\">" . $userTorrentCounts->get($row->id, 0) . "</td><td align=\"center\">" . ($ranking + 1) . "</td></tr>");
        }
        print ("</table>");
    }
}
// ------------- end: top uploader ------------------//

// ------------- start: polls ------------------//
if ($CURUSER && $showpolls_main == "yes")
{
		// Get current poll
		if (!$arr = $Cache->get_value('current_poll_content')){
			$res = sql_query("SELECT * FROM polls ORDER BY id DESC LIMIT 1") or sqlerr(__FILE__, __LINE__);
			$arr = mysql_fetch_array($res);
			$Cache->cache_value('current_poll_content', $arr, 7226);
		}
		if (!$arr)
			$pollexists = false;
		else $pollexists = true;

		print("<h2>".$lang_index['text_polls']);

			if (get_user_class() >= $pollmanage_class)
			{
				print("<font class=\"small\"> - [<a class=\"altlink\" href=\"makepoll.php?returnto=main\"><b>".$lang_index['text_new']."</b></a>]\n");
				if ($pollexists)
				{
					print(" - [<a class=\"altlink\" href=\"makepoll.php?action=edit&amp;pollid=".$arr['id']."&amp;returnto=main\"><b>".$lang_index['text_edit']."</b></a>]\n");
					print(" - [<a class=\"altlink\" href=\"log.php?action=poll&amp;do=delete&amp;pollid=".$arr['id']."&amp;returnto=main\"><b>".$lang_index['text_delete']."</b></a>]");
					print(" - [<a class=\"altlink\" href=\"polloverview.php?id=".$arr['id']."\"><b>".$lang_index['text_detail']."</b></a>]");
				}
				print("</font>");
			}
			print("</h2>");
		if ($pollexists)
		{
			$pollid = intval($arr["id"] ?? 0);

			$question = $arr["question"];
			$o = array($arr["option0"], $arr["option1"], $arr["option2"], $arr["option3"], $arr["option4"],
			$arr["option5"], $arr["option6"], $arr["option7"], $arr["option8"], $arr["option9"],
			$arr["option10"], $arr["option11"], $arr["option12"], $arr["option13"], $arr["option14"],
			$arr["option15"], $arr["option16"], $arr["option17"], $arr["option18"], $arr["option19"]);

			print("<table width=\"100%\"><tr><td class=\"text\" align=\"center\">\n");
			print("<table width=\"59%\" class=\"main\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\"><tr><td class=\"text\" align=\"left\">");
			print("<p align=\"center\"><b>".$question."</b></p>\n");

			// Check if user has already voted
			$res = sql_query("SELECT selection FROM pollanswers WHERE pollid=".sqlesc($pollid)." AND userid=".sqlesc($CURUSER["id"])) or sqlerr();
			$voted = mysql_fetch_assoc($res);
			if ($voted) //user has already voted
			{
				$uservote = $voted["selection"];
				$Cache->new_page('current_poll_result', 3652, true);
				if (!$Cache->get_page())
				{
				// we reserve 255 for blank vote.
				$res = sql_query("SELECT selection FROM pollanswers WHERE pollid=".sqlesc($pollid)." AND selection < 20") or sqlerr();

				$tvotes = mysql_num_rows($res);

				$vs = array();
				$os = array();

				// Count votes
                while ($arr2 = mysql_fetch_row($res)) {
                    if (!isset($vs[$arr2[0]])) {
                        $vs[$arr2[0]] = 0;
                    }
                    $vs[$arr2[0]] ++;
                }


				reset($o);
				for ($i = 0; $i < count($o); ++$i){
					if ($o[$i])
						$os[$i] = array($vs[$i] ?? 0, $o[$i], $i);//field 1: options vote count, field 2: option name, field 3: option index
				}

				function srt($a,$b)
				{
					if ($a[0] > $b[0]) return -1;
					if ($a[0] < $b[0]) return 1;
					return 0;
				}

				// now os is an array like this: array(array(123, "Option 1", 1), array(45, "Option 2", 2))
				$Cache->add_whole_row();
				print("<table class=\"main\" width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\">\n");
				$Cache->end_whole_row();
				$i = 0;
				while (isset($os[$i]))
				{
				    $a = $os[$i];
					if ($tvotes == 0)
						$p = 0;
					else
						$p = round($a[0] / $tvotes * 100);
					$Cache->add_row();
					$Cache->add_part();
					print("<tr><td width=\"1%\" class=\"embedded nowrap\">" . $a[1] . "&nbsp;&nbsp;</td><td width=\"99%\" class=\"embedded nowrap\"><img class=\"bar_end\" src=\"pic/trans.gif\" alt=\"\" /><img ");
					$Cache->end_part();
					$Cache->add_part();
					print(" src=\"pic/trans.gif\" style=\"width: " . ($p * 3) ."px;\" alt=\"\" /><img class=\"bar_end\" src=\"pic/trans.gif\" alt=\"\" /> $p%</td></tr>\n");
					$Cache->end_part();
					$Cache->end_row();
					++$i;
				}
				$Cache->break_loop();
				$Cache->add_whole_row();
				print("</table>\n");
				$tvotes = number_format($tvotes);
				print("<p align=\"center\">".$lang_index['text_votes']." ".$tvotes."</p>\n");
				$Cache->end_whole_row();
				$Cache->cache_page();
				}
				echo $Cache->next_row();
				$i = 0;
				while($Cache->next_row()){
					echo $Cache->next_part();
					if ($i == $uservote)
						echo "class=\"sltbar\"";
					else
						echo "class=\"unsltbar\"";
					echo $Cache->next_part();
					$i++;
				}
				echo $Cache->next_row();
			}
			else //user has not voted yet
			{
				print("<form method=\"post\" action=\"index.php\">\n");
				$i = 0;
				while ($a = $o[$i])
				{
					print("<input type=\"radio\" name=\"choice\" value=\"".$i."\">".$a."<br />\n");
					++$i;
				}
				print("<br />");
				print("<input type=\"radio\" name=\"choice\" value=\"255\">".$lang_index['radio_blank_vote']."<br />\n");
				print("<p align=\"center\"><input type=\"submit\" class=\"btn\" value=\"".$lang_index['submit_vote']."\" /></p>");
			}
			print("</td></tr></table>");

			if ($voted && get_user_class() >= $log_class)
				print("<p align=\"center\"><a href=\"log.php?action=poll\">".$lang_index['text_previous_polls']."</a></p>\n");
			print("</td></tr></table>");
		}
}
// ------------- end: polls ------------------//
// ------------- start: stats ------------------//
if ($showstats_main == "yes")
{
?>
<h2><?php echo $lang_index['text_tracker_statistics'] ?></h2>
<table width="100%"><tr><td class="text" align="center">
<table width="60%" class="main" border="1" cellspacing="0" cellpadding="10">
<?php
	$Cache->new_page('stats_users', 3000, true);
	if (!$Cache->get_page()){
	$Cache->add_whole_row();
	$registered = number_format(get_row_count("users"));
	$unverified = number_format(get_row_count("users", "WHERE status='pending' and enabled='yes'"));
	$totalonlinetoday = number_format(get_row_count("users","WHERE last_access >= ". sqlesc(date("Y-m-d H:i:s",(TIMENOW - 86400)))));
	$totalonlineweek = number_format(get_row_count("users","WHERE last_access >= ". sqlesc(date("Y-m-d H:i:s",(TIMENOW - 604800)))));
	$VIP = number_format(get_row_count("users", "WHERE class=".UC_VIP));
	$donated = number_format(get_row_count("users", "WHERE donor = 'yes'"));
	$warned = number_format(get_row_count("users", "WHERE warned='yes'"));
	$disabled = number_format(get_row_count("users", "WHERE enabled='no'"));
	$registered_male = number_format(get_row_count("users", "WHERE gender='Male'"));
	$registered_female = number_format(get_row_count("users", "WHERE gender='Female'"));
?>
<tr>
<?php
	twotd($lang_index['row_users_active_today'],$totalonlinetoday);
	twotd($lang_index['row_users_active_this_week'],$totalonlineweek);
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_registered_users'],$registered." / ".number_format($maxusers));
	twotd($lang_index['row_unconfirmed_users'],$unverified);
?>
</tr>
<tr>
<?php
	twotd(get_user_class_name(UC_VIP,false,false,true),$VIP);
	twotd($lang_index['row_donors']." <img class=\"star\" src=\"pic/trans.gif\" alt=\"Donor\" />",$donated);
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_warned_users']." <img class=\"warned\" src=\"pic/trans.gif\" alt=\"warned\" />",$warned);
	twotd($lang_index['row_banned_users']." <img class=\"disabled\" src=\"pic/trans.gif\" alt=\"disabled\" />",$disabled);
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_male_users'],$registered_male);
	twotd($lang_index['row_female_users'],$registered_female);
?>
</tr>
<?php
	$Cache->end_whole_row();
	$Cache->cache_page();
	}
	echo $Cache->next_row();
?>
<tr><td colspan="4" class="rowhead">&nbsp;</td></tr>
<?php
	$Cache->new_page('stats_torrents', 1800, true);
	if (!$Cache->get_page()){
	$Cache->add_whole_row();
	$torrents = number_format(get_row_count("torrents"));
	$dead = number_format(get_row_count("torrents", "WHERE visible='no'"));
	$seeders = get_row_count("peers", "WHERE seeder='yes'");
	$leechers = get_row_count("peers", "WHERE seeder='no'");
	if ($leechers == 0)
		$ratio = 0;
	else
		$ratio = round($seeders / $leechers * 100);
	$activewebusernow = get_row_count("users","WHERE last_access >= ".sqlesc(date("Y-m-d H:i:s",(TIMENOW - 900))));
	$activewebusernow=number_format($activewebusernow);
	$activetrackerusernow = number_format(get_single_value("peers","COUNT(DISTINCT(userid))"));
	$peers = number_format($seeders + $leechers);
	$seeders = number_format($seeders);
	$leechers = number_format($leechers);
	$totaltorrentssize = mksize(get_row_sum("torrents", "size"));
	$totaluploaded = get_row_sum("users","uploaded");
	$totaldownloaded = get_row_sum("users","downloaded");
	$totaldata = $totaldownloaded+$totaluploaded;
?>
<tr>
<?php
	twotd($lang_index['row_torrents'],$torrents);
	twotd($lang_index['row_dead_torrents'],$dead);
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_seeders'],$seeders);
	twotd($lang_index['row_leechers'],$leechers);
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_peers'],$peers);
	twotd($lang_index['row_seeder_leecher_ratio'],$ratio."%");
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_active_browsing_users'], $activewebusernow);
	twotd($lang_index['row_tracker_active_users'], $activetrackerusernow);
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_total_size_of_torrents'],$totaltorrentssize);
	twotd($lang_index['row_total_uploaded'],mksize($totaluploaded));
?>
</tr>
<tr>
<?php
	twotd($lang_index['row_total_downloaded'],mksize($totaldownloaded));
	twotd($lang_index['row_total_data'],mksize($totaldata));
?>
</tr>
<?php
	$Cache->end_whole_row();
	$Cache->cache_page();
	}
	echo $Cache->next_row();
?>
<tr><td colspan="4" class="rowhead">&nbsp;</td></tr>
<?php
	$Cache->new_page('stats_classes', 4535, true);
	if (!$Cache->get_page()){
	$Cache->add_whole_row();
	$peasants =  number_format(get_row_count("users", "WHERE class=".UC_PEASANT));
	$users = number_format(get_row_count("users", "WHERE class=".UC_USER));
	$powerusers = number_format(get_row_count("users", "WHERE class=".UC_POWER_USER));
	$eliteusers = number_format(get_row_count("users", "WHERE class=".UC_ELITE_USER));
	$crazyusers = number_format(get_row_count("users", "WHERE class=".UC_CRAZY_USER));
	$insaneusers = number_format(get_row_count("users", "WHERE class=".UC_INSANE_USER));
	$veteranusers = number_format(get_row_count("users", "WHERE class=".UC_VETERAN_USER));
	$extremeusers = number_format(get_row_count("users", "WHERE class=".UC_EXTREME_USER));
	$ultimateusers = number_format(get_row_count("users", "WHERE class=".UC_ULTIMATE_USER));
	$nexusmasters = number_format(get_row_count("users", "WHERE class=".UC_NEXUS_MASTER));
?>
<tr>
<?php
	twotd(get_user_class_name(UC_PEASANT,false,false,true)." <img class=\"leechwarned\" src=\"pic/trans.gif\" alt=\"leechwarned\" />",$peasants);
	twotd(get_user_class_name(UC_USER,false,false,true),$users);
?>
</tr>
<tr>
<?php
	twotd(get_user_class_name(UC_POWER_USER,false,false,true),$powerusers);
	twotd(get_user_class_name(UC_ELITE_USER,false,false,true),$eliteusers);
?>
</tr>
<tr>
<?php
	twotd(get_user_class_name(UC_CRAZY_USER,false,false,true),$crazyusers);
	twotd(get_user_class_name(UC_INSANE_USER,false,false,true),$insaneusers);
?>
</tr>
<tr>
<?php
	twotd(get_user_class_name(UC_VETERAN_USER,false,false,true),$veteranusers);
	twotd(get_user_class_name(UC_EXTREME_USER,false,false,true),$extremeusers);
?>
</tr>
<tr>
<?php
	twotd(get_user_class_name(UC_ULTIMATE_USER,false,false,true),$ultimateusers);
	twotd(get_user_class_name(UC_NEXUS_MASTER,false,false,true),$nexusmasters);
?>
</tr>
<?php
	$Cache->end_whole_row();
	$Cache->cache_page();
	}
	echo $Cache->next_row();
?>
</table>
</td></tr></table>
<?php
}
// ------------- end: stats ------------------//
// ------------- start: tracker load ------------------//
if ($showtrackerload == "yes") {
	$uptimeresult=exec('uptime');
	if ($uptimeresult){
?>
<h2><?php echo $lang_index['text_tracker_load'] ?></h2>
<table width="100%" border="1" cellspacing="0" cellpadding="10"><tr><td class="text" align="center">
<?php
	//uptime, work in *nix system
	print ("<div align=\"center\">" . trim($uptimeresult) . "</div>");
	print("</td></tr></table>");
	}
}
// ------------- end: tracker load ------------------//

// ------------- start: disclaimer ------------------//
?>
<h2><?php echo $lang_index['text_disclaimer'] ?></h2>
<table width="100%"><tr><td class="text">
  <?php echo $lang_index['text_disclaimer_content'] ?></td></tr></table>
<?php
// ------------- end: disclaimer ------------------//
// ------------- start: links ------------------//
	print("<h2>".$lang_index['text_links']);
	if (get_user_class() >= $applylink_class)
		print("<font class=\"small\"> - [<a class=\"altlink\" href=\"linksmanage.php?action=apply\"><b>".$lang_index['text_apply_for_link']."</b></a>]</font>");
	if (get_user_class() >= $linkmanage_class)
	{
		print("<font class=\"small\">");
		print(" - [<a class=\"altlink\" href=\"linksmanage.php\"><b>".$lang_index['text_manage_links']."</b></a>]\n");
		print("</font>");
	}
	print("</h2>");
	$Cache->new_page('links', 86400, false);
	if (!$Cache->get_page()){
	$Cache->add_whole_row();
	$res = sql_query("SELECT * FROM links ORDER BY id ASC") or sqlerr(__FILE__, __LINE__);
	if (mysql_num_rows($res) > 0)
	{
		$links = "";
		while($array = mysql_fetch_array($res))
		{
			$links .= "<a href=\"" . $array['url'] . "\" title=\"" . $array['title'] . "\" target=\"_blank\">" . $array['name'] . "</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
		}
		print("<table width=\"100%\"><tr><td class=\"text\">".trim($links)."</td></tr></table>");
	}
	$Cache->end_whole_row();
	$Cache->cache_page();
	}
	echo $Cache->next_row();
// ------------- end: links ------------------//
// ------------- start: browser, client and code note ------------------//
?>
<table width="100%" class="main" border="0" cellspacing="0" cellpadding="0"><tr><td class="embedded">
<div align="center"><br /><font class="medium"><?php echo $lang_index['text_browser_note'] ?></font></div>
<div align="center"><a href="<?php echo NEXUSPHPURL?>" title="<?php echo PROJECTNAME?>" target="_blank"><img src="pic/nexus.png" alt="<?php echo PROJECTNAME?>" /></a></div>
</td></tr></table>
<?php
// ------------- end: browser, client and code note ------------------//
if ($CURUSER)
	$USERUPDATESET[] = "last_home = ".sqlesc(date("Y-m-d H:i:s"));
$Cache->delete_value('user_'.$CURUSER["id"].'_unread_news_count');
end_main_frame();
stdfoot();
?>

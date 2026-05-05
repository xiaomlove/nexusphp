<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();
parked();

user_can('staffmem', true);

$count = \Nexus\Database\NexusDB::table('reports')->count();
if (!$count){
	stderr($lang_reports['std_oho'], $lang_reports['std_no_report']);
}
stdhead($lang_reports['head_reports']);
$perpage = 10;
list($pagertop, $pagerbottom, $limit, $start, $rpp) = pager($perpage, $count, "reports.php?");
begin_main_frame();
print("<h1 align=center>".$lang_reports['text_reports']."</h1>");
print("<table border=1 cellspacing=0 cellpadding=5 align=center>\n");
print("<tr><td class=colhead><nobr>".$lang_reports['col_added']."</nobr></td><td class=colhead>".$lang_reports['col_reporter']."</td><td class=colhead>".$lang_reports['col_reporting']."</td><td class=colhead><nobr>".$lang_reports['col_type']."</nobr></td><td class=colhead>".$lang_reports['col_reason']."</td><td class=colhead><nobr>".$lang_reports['col_dealt_with']."</nobr></td><td class=colhead><nobr>".$lang_reports['col_action']."</nobr></td>");

print("<form method=post action=takeupdate.php>");
$reportres = \Nexus\Database\NexusDB::table('reports')
	->orderBy('dealtwith', 'asc')
	->orderByDesc('id')
	->offset((int) $start)
	->limit((int) $rpp)
	->get();

foreach ($reportres as $row)
{
	$row = (array) $row;
	if ($row['dealtwith'])
		$dealtwith = "<font color=green>".$lang_reports['text_yes']."</font> - " . get_username($row['dealtby']);
	else
		$dealtwith = "<font color=red>".$lang_reports['text_no']."</font>";
	switch ($row['type'])
	{
		case "torrent":
		{
			$type = $lang_reports['text_torrent'];
			$arr = \Nexus\Database\NexusDB::table('torrents')
				->where('id', (int) $row['reportid'])
				->select(['id', 'name'])
				->first();
			$arr = $arr ? (array) $arr : null;
			if (!$arr)
				$reporting = $lang_reports['text_torrent_does_not_exist'];
			else
			{
				$reporting = "<a href=details.php?id=".$arr['id'].">".htmlspecialchars($arr['name'])."</a>";
			}
			break;
		}
		case "user":
		{
			$type = $lang_reports['text_user'];
			$arr = \Nexus\Database\NexusDB::table('users')
				->where('id', (int) $row['reportid'])
				->select(['id'])
				->first();
			$arr = $arr ? (array) $arr : null;
			if (!$arr)
				$reporting = $lang_reports['text_user_does_not_exist'];
			else
			{
				$reporting = get_username($arr['id']);
			}
			break;
		}
		case "offer":
		{
			$type = $lang_reports['text_offer'];
			$arr = \Nexus\Database\NexusDB::table('offers')
				->where('id', (int) $row['reportid'])
				->select(['id', 'name'])
				->first();
			$arr = $arr ? (array) $arr : null;
			if (!$arr)
				$reporting = $lang_reports['text_offer_does_not_exist'];
			else
			{
				$reporting = "<a href=\"offers.php?id=".$arr['id']."&off_details=1\">".htmlspecialchars($arr['name'])."</a>";
			}
			break;
		}
		case "post":
		{
			$type = $lang_reports['text_forum_post'];
			$arr = \Nexus\Database\NexusDB::table('topics')
				->leftJoin('posts', 'posts.topicid', '=', 'topics.id')
				->where('posts.id', (int) $row['reportid'])
				->select(['topics.id AS topicid', 'topics.subject AS subject', 'posts.userid AS postuserid'])
				->first();
			$arr = $arr ? (array) $arr : null;
			if (!$arr)
				$reporting = $lang_reports['text_post_does_not_exist'];
			else
			{
				$reporting = $lang_reports['text_post_id'].$row['reportid'].$lang_reports['text_of_topic']."<b><a href=\"forums.php?action=viewtopic&topicid=".$arr['topicid']."&page=p".htmlspecialchars($row['reportid'])."#pid".htmlspecialchars($row['reportid'])."\">".htmlspecialchars($arr['subject'])."</a></b>".$lang_reports['text_by'].get_username($arr['postuserid']);
			}
			break;
		}
		case "comment":
		{
			$type = $lang_reports['text_comment'];
			$arr = \Nexus\Database\NexusDB::table('comments')
				->where('id', (int) $row['reportid'])
				->select(['id', 'user', 'torrent', 'offer'])
				->first();
			$arr = $arr ? (array) $arr : null;
			if (!$arr)
				$reporting = $lang_reports['text_comment_does_not_exist'];
			else
			{
					if ($arr['torrent'])
					{
						$name = \Nexus\Database\NexusDB::table('torrents')->where('id', (int) $arr['torrent'])->value('name');
						$url = "details.php?id=".$arr['torrent']."#cid".$row['reportid'];
						$of = $lang_reports['text_of_torrent'];
					}
					elseif ($arr['offer'])
					{
						$name = \Nexus\Database\NexusDB::table('offers')->where('id', (int) $arr['offer'])->value('name');
						$url = "offers.php?id=".$arr['offer']."&off_details=1#cid".$row['reportid'];
						$of = $lang_reports['text_of_offer'];
					} else //Comment belongs to no one
						$of = "unknown";
					$reporting = $lang_reports['text_comment_id'].$row['reportid'].$of."<b><a href=\"".$url."\">".htmlspecialchars($name)."</a></b>".$lang_reports['text_by'].get_username($arr['user']);
			}
			break;
		}
		case "subtitle":
		{
			$type = $lang_reports['text_subtitle'];
			$arr = \Nexus\Database\NexusDB::table('subs')
				->where('id', (int) $row['reportid'])
				->select(['id', 'torrent_id', 'title'])
				->first();
			$arr = $arr ? (array) $arr : null;
			if (!$arr)
				$reporting = $lang_reports['text_subtitle_does_not_exist'];
			else
			{
				$reporting = "<a href=\"downloadsubs.php?torrentid=" . $arr['torrent_id'] ."&subid=" .$arr['id']."\">".htmlspecialchars($arr['title'])."</a>".$lang_reports['text_for_torrent_id']."<a href=\"details.php?id=" . $arr['torrent_id'] ."\">".$arr['torrent_id']."</a>";
			}
			break;
		}
		default:
		{
			break;
		}
	}

	print("<tr><td class=rowfollow><nobr>".gettime($row['added'])."</nobr></td><td class=rowfollow>" . get_username($row['addedby']) . "</td><td class=rowfollow>".$reporting."</td><td class=rowfollow><nobr>".$type."</nobr></td><td class=rowfollow>".htmlspecialchars($row['reason'])."</td><td class=rowfollow><nobr>".$dealtwith."</nobr></td><td class=rowfollow><input type=\"checkbox\" name=\"delreport[]\" value=\"" . $row['id'] . "\" /></td></tr>\n");
}
?>
<tr><td class="colhead" colspan="7" align="right"><input type="submit" name="setdealt" value="<?php echo $lang_reports['submit_set_dealt']?>" /><input type="submit" name="delete" value="<?php echo $lang_reports['submit_delete']?>" /></td></tr>
</form>
<?php
print("</table>");
print($pagerbottom);
end_main_frame();
stdfoot();

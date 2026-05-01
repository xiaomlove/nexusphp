<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path());
loggedinorreturn();

user_can('pollmanage', true);

$pollid = intval($_GET['id'] ?? 0);

if ($pollid)
{
	$poll = \Nexus\Database\NexusDB::table('polls')
		->where('id', (int) $pollid)
		->limit(1)
		->first();
	$poll = $poll ? (array) $poll : null;
	if (!$poll)
		stderr($lang_polloverview['std_error'], $lang_polloverview['text_no_poll_id']);
	stdhead($lang_polloverview['head_poll_overview']);
	print("<h1 align=\"center\">".$lang_polloverview['text_polls_overview']."</h1>\n");

	print("<table width=737 border=1 cellspacing=0 cellpadding=5><tr>\n" .
 "<td class=colhead align=center><nobr>".$lang_polloverview['col_id']."</nobr></td><td class=colhead><nobr>".$lang_polloverview['col_added']."</nobr></td><td class=colhead><nobr>".$lang_polloverview['col_question']."</nobr></td></tr>\n");

	$o = array($poll["option0"], $poll["option1"], $poll["option2"], $poll["option3"], $poll["option4"], $poll["option5"], $poll["option6"], $poll["option7"], $poll["option8"], $poll["option9"], $poll["option10"], $poll["option11"], $poll["option12"], $poll["option13"], $poll["option14"], $poll["option15"], $poll["option16"], $poll["option17"], $poll["option18"], $poll["option19"]);

	$added = gettime($poll['added']);
	print("<tr><td align=center><a href=\"polloverview.php?id=".$poll['id']."\">".$poll['id']."</a></td><td>".$added."</td><td><a href=\"polloverview.php?id=".$poll['id']."\">".$poll['question']."</a></td></tr>\n");
	print("</table>\n");

	print("<h1 align=\"center\">".$lang_polloverview['text_poll_question']."</h1><br />\n");
	print("<table width=737 border=1 cellspacing=0 cellpadding=5><tr><td class=colhead>".$lang_polloverview['col_option_no']."</td><td class=colhead>".$lang_polloverview['col_options']."</td></tr>\n");
	foreach($o as $key=>$value) {
		if($value != "")
			print("<tr><td>".$key."</td><td>".$value."</td></tr>\n");
	}
 	print("</table>\n");
	$count = \Nexus\Database\NexusDB::table('pollanswers')
		->where('pollid', (int) $pollid)
		->where('selection', '<', 20)
		->count();

	print("<h1 align=\"center\">".$lang_polloverview['text_polls_user_overview']."</h1>\n");

	if ($count == 0) {
		print("<p align=\"center\">".$lang_polloverview['text_no_users_voted']."</p>");
	}
	else{
		$perpage = 100;
		list($pagertop, $pagerbottom, $limit, $start, $rpp) = pager($perpage, $count, "?id=".$pollid."&");
		$voterRows = \Nexus\Database\NexusDB::table('pollanswers')
			->leftJoin('users', 'pollanswers.userid', '=', 'users.id')
			->where('pollanswers.pollid', (int) $pollid)
			->where('pollanswers.selection', '<', 20)
			->orderBy('users.username')
			->offset((int) $start)
			->limit((int) $rpp)
			->select(['pollanswers.*', 'users.username'])
			->get();
		print($pagertop);
 		print("<table width=737 border=1 cellspacing=0 cellpadding=5>");
		print("<tr><td class=colhead align=center><nobr>".$lang_polloverview['col_username']."</nobr></td><td class=colhead align=center><nobr>".$lang_polloverview['col_selection']."<nobr></td></tr>\n");
		foreach ($voterRows as $useras)
		{
			$useras = (array) $useras;
			$username = get_username($useras['userid']);
  			print("<tr><td>".$username."</td><td>".$o[$useras['selection']]."</td></tr>\n");
 		}
		print("</table>\n");
		print($pagerbottom);
	}
	stdfoot();
}
else
{
	$pollRows = \Nexus\Database\NexusDB::table('polls')
		->select(['id', 'added', 'question'])
		->orderByDesc('id')
		->get();
 	if (count($pollRows) == 0)
  		stderr($lang_polloverview['std_error'], $lang_polloverview['text_no_users_voted']);
	stdhead($lang_polloverview['head_poll_overview']);
	print("<h1 align=\"center\">".$lang_polloverview['text_polls_overview']."</h1>\n");

	print("<table width=737 border=1 cellspacing=0 cellpadding=5><tr>\n" .
 "<td class=colhead align=center><nobr>".$lang_polloverview['col_id']."</nobr></td><td class=colhead>".$lang_polloverview['col_added']."</td><td class=colhead><nobr>".$lang_polloverview['col_question']."</nobr></td></tr>\n");
	foreach ($pollRows as $poll)
	{
		$poll = (array) $poll;
		$added = gettime($poll['added']);
		print("<tr><td align=center><a href=\"polloverview.php?id=".$poll['id']."\">".$poll['id']."</a></td><td>".$added."</td><td><a href=\"polloverview.php?id=".$poll['id']."\">".$poll['question']."</a></td></tr>\n");
	}
	print("</table>\n");
	stdfoot();
}
?>

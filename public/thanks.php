<?php
require_once("../include/bittorrent.php");
dbconn();
loggedinorreturn();


if (isset($_GET['id']))
	stderr("Party is over!", "This trick doesn't work anymore. You need to click the button!");
$userid = $CURUSER["id"];
$torrentid = $_POST["id"];
$torrentowner = \Nexus\Database\NexusDB::table('torrents')->where('id', (int) $torrentid)->value('owner');
if ($torrentowner === null)
	stderr("Error", "Invalid torrent id!");
$t_ab = (int) \Nexus\Database\NexusDB::table('thanks')->where('torrentid', (int) $torrentid)->where('userid', (int) $userid)->count();
if ($t_ab != 0)
	stderr("Error", "You already said thanks!");
if (isset($userid) && isset($torrentid))
{
\Nexus\Database\NexusDB::insert('thanks', [
	'torrentid' => (int) $torrentid,
	'userid' => (int) $userid,
]);
KPS("+",$saythanks_bonus,$CURUSER['id']);//User gets bonus for saying thanks
KPS("+",$receivethanks_bonus,$torrentowner);//Thanks receiver get bonus
}

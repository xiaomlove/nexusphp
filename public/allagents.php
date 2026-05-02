<?php

use Nexus\Database\NexusDB;

require '../include/bittorrent.php';
dbconn();
loggedinorreturn();
if (get_user_class() < UC_MODERATOR) {
    stderr('Error', 'Permission denied.');
}
$rows2 = NexusDB::table('peers')
    ->groupBy('agent')
    ->orderBy('agent')
    ->selectRaw('agent, count(*) as counts')
    ->get();
stdhead('All Clients');
echo "<table align=center border=3 cellspacing=0 cellpadding=5>\n";
echo "<tr><td class=colhead>Client</td><td class=colhead>Counts</td></tr>\n";
foreach ($rows2 as $arr2) {
    $arr2 = (array) $arr2;
    echo "</a></td><td align=left>{$arr2['agent']}</td><td align=left>{$arr2['counts']}</td></tr>\n";
}
echo "</table>\n";
stdfoot();

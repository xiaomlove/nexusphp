<?php

use Nexus\Database\NexusDB;

require '../include/bittorrent.php';
dbconn();
if (isset($_GET['q']) && $_GET['q'] != '') {
    $searchstr = trim($_GET['q']);

    $suggestRows = NexusDB::table('suggest')
        ->where('keywords', 'like', $searchstr.'%')
        ->groupBy('keywords')
        ->orderByDesc(NexusDB::raw('COUNT(*)'))
        ->orderByDesc('keywords')
        ->limit(10)
        ->selectRaw('keywords AS suggest, COUNT(*) AS count')
        ->get();
    $result = [htmlspecialchars($searchstr), [], []];
    foreach ($suggestRows as $suggest) {
        $suggest = (array) $suggest;
        if (strlen($suggest['suggest']) > 25) {
            continue;
        }
        $result[1][] = $suggest['suggest'];
        $result[2][] = $suggest['count'].' times';
        $i++;
        if ($i >= 5) {
            break;
        }
    }
    echo json_encode($result);
}

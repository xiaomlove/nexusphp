<?php

namespace Nexus\Torrent;

use Nexus\Database\DB;

class Torrent
{
    /**
     * get torrent seeching or downloading status, download progress for someone
     *
     * @param int $uid
     * @param array $torrentIdArr
     * @return array
     * @throws \Nexus\Database\DatabaseException
     */
    public function listLeechingSeedingStatus(int $uid, array $torrentIdArr)
    {
        $torrentIdStr = implode(',', $torrentIdArr);
        //seeding or leeching, from peers
        $whereStr = sprintf("userid = %s and torrent in (%s)", sqlesc($uid), $torrentIdStr);
        $peerList = DB::getAll('peers', $whereStr, 'torrent, to_go');
        $peerList = array_column($peerList,'to_go', 'torrent');
        //download progress, from snatched
        $sql = sprintf(
            "select snatched.finished, snatched.to_go, snatched.torrentid, torrents.size from snatched inner join torrents on snatched.torrentid = torrents.id where snatched.userid = %s and snatched.torrentid in (%s)",
            sqlesc($uid), $torrentIdStr
        );
        $snatchedList = [];
        $res = sql_query($sql);
        while ($row = mysql_fetch_assoc($res)) {
            $id = $row['torrentid'];
            $activeStatus = 'none';
            if (isset($peerList[$id])) {
                if ($peerList[$id]['to_go'] == 0) {
                    $activeStatus = 'seeding';
                } else {
                    $activeStatus = 'leeching';
                }
            }
            $realDownloaded = $row['size'] - $row['to_go'];
            $progress = sprintf('%.4f', $realDownloaded / $row['size']);
            $snatchedList[$id] = [
                'finished' => $row['to_go'] == 0 ? 'yes' : 'no',
                'progress' => $progress,
                'active_status' => $activeStatus,
            ];
        }
        return $snatchedList;
    }
}
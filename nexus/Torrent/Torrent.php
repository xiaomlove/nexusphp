<?php

namespace Nexus\Torrent;

use App\Models\Setting;
use Nexus\Database\NexusDB;
use Nexus\Imdb\Imdb;
use Nexus\PTGen\PTGen;

class Torrent
{
    /**
     * get torrent seeding or leeching status, download progress of someone
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
        $peerList = NexusDB::getAll('peers', $whereStr, 'torrent, to_go');
        $peerList = array_column($peerList,'to_go', 'torrent');
        //download progress, from snatched
        $sql = sprintf(
            "select snatched.to_go, snatched.torrentid, torrents.size from snatched inner join torrents on snatched.torrentid = torrents.id where snatched.userid = %s and snatched.torrentid in (%s)",
            sqlesc($uid), $torrentIdStr
        );
        $snatchedList = [];
        $res = sql_query($sql);
        while ($row = mysql_fetch_assoc($res)) {
            $id = $row['torrentid'];
            $activeStatus = 'inactivity';
            if (isset($peerList[$id])) {
                if ($peerList[$id] == 0) {
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

    public function renderProgressBar($activeStatus, $progress): string
    {
        $color = '#aaa';
        if ($activeStatus == 'seeding') {
            $color = 'green';
        } elseif ($activeStatus == 'leeching') {
            $color = 'blue';
        }
        $progress = ($progress * 100) . '%';
        $result = sprintf(
            '<div style="padding: 1px;margin-top: 2px;border: 1px solid #838383" title="%s"><div style="width: %s;background-color: %s;height: 2px"></div></div>',
            $activeStatus . " $progress", $progress, $color
        );
        return $result;
    }

    public function renderTorrentsPageAverageRating(array $torrentInfo): string
    {
        static $ptGen;
        if (is_null($ptGen)) {
            $ptGen = new PTGen();
        }
        $ptGenInfo = $torrentInfo['pt_gen'];
        if (!is_array($torrentInfo['pt_gen'])) {
            $ptGenInfo = json_decode($ptGenInfo, true);
        }

        $log = "torrent: " . $torrentInfo['id'];
        $siteIdAndRating = $ptGen->listRatings($ptGenInfo ?? [], $torrentInfo['url']);
        $log .= "siteIdAndRating: " . json_encode($siteIdAndRating);
        do_log($log);
        return $ptGen->buildRatingSpan($siteIdAndRating);
    }

}

<?php

namespace Nexus\Torrent;

use Nexus\Database\DatabaseException;
use Nexus\Database\NexusDB;
use Nexus\PTGen\PTGen;

class Torrent
{
    /**
     * get torrent seeding or leeching status, download progress of someone
     *
     * @return array
     *
     * @throws DatabaseException
     */
    public function listLeechingSeedingStatus(int $uid, array $torrentIdArr)
    {
        if (empty($torrentIdArr)) {
            return [];
        }
        $torrentIdStr = implode(',', $torrentIdArr);
        // seeding or leeching, from peers
        $whereStr = sprintf('userid = %s and torrent in (%s)', $uid, $torrentIdStr);
        $peerList = NexusDB::getAll('peers', $whereStr, 'torrent, to_go');
        $peerList = array_column($peerList, 'to_go', 'torrent');
        // download progress, from snatched
        $sql = sprintf(
            'select snatched.to_go, snatched.torrentid, torrents.size from snatched inner join torrents on snatched.torrentid = torrents.id where snatched.userid = %s and snatched.torrentid in (%s)',
            $uid, $torrentIdStr
        );
        $snatchedList = [];
        $res = NexusDB::select($sql);
        foreach ($res as $row) {
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
                'progress' => floatval($progress),
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
        $progress = ($progress * 100).'%';
        $result = sprintf(
            '<div style="padding: 1px;margin-top: 2px;border: 1px solid #838383" title="%s"><div style="width: %s;background-color: %s;height: 2px"></div></div>',
            $activeStatus." $progress", $progress, $color
        );

        return $result;
    }

    public function renderTorrentsPageAverageRating(array $torrentInfo, array|string $ptGenInfo): string
    {
        static $ptGen;
        if (is_null($ptGen)) {
            $ptGen = new PTGen;
        }
        $log = 'torrent: '.$torrentInfo['id'];
        $siteIdAndRating = $ptGen->listRatings(is_array($ptGenInfo) && count($ptGenInfo) ? $ptGenInfo : [], $torrentInfo['url']);
        $log .= ', siteIdAndRating: '.json_encode($siteIdAndRating);
        do_log($log);

        return $ptGen->buildRatingSpan($siteIdAndRating);
    }
}

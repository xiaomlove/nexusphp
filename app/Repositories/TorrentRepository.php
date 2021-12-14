<?php

namespace App\Repositories;

use App\Exceptions\NexusException;
use App\Models\AudioCodec;
use App\Models\Category;
use App\Models\Codec;
use App\Models\HitAndRun;
use App\Models\Media;
use App\Models\Message;
use App\Models\Peer;
use App\Models\Processing;
use App\Models\SearchBox;
use App\Models\Setting;
use App\Models\Snatch;
use App\Models\Source;
use App\Models\Standard;
use App\Models\Team;
use App\Models\Torrent;
use App\Models\TorrentSecret;
use App\Models\User;
use Carbon\Carbon;
use Hashids\Hashids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class TorrentRepository extends BaseRepository
{
    /**
     *  fetch torrent list
     *
     * @param array $params
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getList(array $params)
    {
        $query = Torrent::query();
        if (!empty($params['category'])) {
            $query->where('category', $params['category']);
        }
        if (!empty($params['source'])) {
            $query->where('source', $params['source']);
        }
        if (!empty($params['medium'])) {
            $query->where('medium', $params['medium']);
        }
        if (!empty($params['codec'])) {
            $query->where('codec', $params['codec']);
        }
        if (!empty($params['audio_codec'])) {
            $query->where('audiocodec', $params['audio_codec']);
        }
        if (!empty($params['standard'])) {
            $query->where('standard', $params['standard']);
        }
        if (!empty($params['processing'])) {
            $query->where('processing', $params['processing']);
        }
        if (!empty($params['team'])) {
            $query->where('team', $params['team']);
        }
        if (!empty($params['owner'])) {
            $query->where('owner', $params['owner']);
        }
        if (!empty($params['visible'])) {
            $query->where('visible', $params['visible']);
        }

        if (!empty($params['query'])) {
            $query->where(function (Builder $query) use ($params) {
                $query->where('name', 'like', "%{$params['query']}%")
                    ->orWhere('small_descr', 'like', "%{$params['query']}%");
            });
        }

        list($sortField, $sortType) = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);

        $with = ['user'];
        $torrents = $query->with($with)->paginate();
        return $torrents;
    }

    public function getSearchBox($id = null)
    {
        if (is_null($id)) {
            $id = Setting::get('main.browsecat');
        }
        $searchBox = SearchBox::query()->findOrFail($id);
        $category = $searchBox->categories()->orderBy('sort_index')->orderBy('id')->get();
        $modalRows = [];
        $modalRows[] = $categoryFormatted = $this->formatRow(Category::getLabelName(), $category, 'category');
        if ($searchBox->showsubcat) {
            if ($searchBox->showsource) {
                $source = Source::query()->orderBy('sort_index')->orderBy('id')->get();
                $modalRows[] = $this->formatRow(Source::getLabelName(), $source, 'source');
            }
            if ($searchBox->showmedia) {
                $media = Media::query()->orderBy('sort_index')->orderBy('id')->get();
                $modalRows[] = $this->formatRow(Media::getLabelName(), $media, 'medium');
            }
            if ($searchBox->showcodec) {
                $codec = Codec::query()->orderBy('sort_index')->orderBy('id')->get();
                $modalRows[] = $this->formatRow(Codec::getLabelName(), $codec, 'codec');
            }
            if ($searchBox->showstandard) {
                $standard = Standard::query()->orderBy('sort_index')->orderBy('id')->get();
                $modalRows[] = $this->formatRow(Standard::getLabelName(), $standard, 'standard');
            }
            if ($searchBox->showprocessing) {
                $processing = Processing::query()->orderBy('sort_index')->orderBy('id')->get();
                $modalRows[] = $this->formatRow(Processing::getLabelName(), $processing, 'processing');
            }
            if ($searchBox->showteam) {
                $team = Team::query()->orderBy('sort_index')->orderBy('id')->get();
                $modalRows[] = $this->formatRow(Team::getLabelName(), $team, 'team');
            }
            if ($searchBox->showaudiocodec) {
                $audioCodec = AudioCodec::query()->orderBy('sort_index')->orderBy('id')->get();
                $modalRows[] = $this->formatRow(AudioCodec::getLabelName(), $audioCodec, 'audio_codec');
            }
        }
        $results = [];
        $categories = $categoryFormatted['rows'];
        $categories[0]['active'] = 1;
        $results['categories'] = $categories;
        $results['modal_rows'] = $modalRows;
        return $results;
    }

    private function formatRow($header, $items, $name)
    {
        $result['header'] = $header;
        $result['rows'][] = [
            'label' => 'All',
            'value' => 0,
            'name' => $name,
            'active' => 1,
        ];
        foreach ($items as $value) {
            $item = [
                'label' => $value->name,
                'value' => $value->id,
                'name' => $name,
                'active' => 0,
            ];
            $result['rows'][] = $item;
        }
        return $result;
    }

    public function listPeers($torrentId)
    {
        $seederList = $leecherList = collect();
        $peers = Peer::query()->where('torrent', $torrentId)->with(['user', 'relative_torrent'])->get()->groupBy('seeder');
        if ($peers->has(Peer::SEEDER_YES)) {
            $seederList = $peers->get(Peer::SEEDER_YES)->sort(function ($a, $b) {
                $x = $a->uploaded;
                $y = $b->uploaded;
                if ($x == $y)
                    return 0;
                if ($x < $y)
                    return 1;
                return -1;
            });
            $seederList = $this->formatPeers($seederList);
        }
        if ($peers->has(Peer::SEEDER_NO)) {
            $leecherList = $peers->get(Peer::SEEDER_NO)->sort(function ($a, $b) {
                $x = $a->to_go;
                $y = $b->to_go;
                if ($x == $y)
                    return 0;
                if ($x < $y)
                    return -1;
                return 1;
            });
            $leecherList = $this->formatPeers($leecherList);
        }

        return [
            'seeder_list' => $seederList,
            'leecher_list' => $leecherList,
        ];

    }

    public function getPeerUploadSpeed($peer): string
    {
        $diff = $peer->uploaded - $peer->uploadoffset;
        $seconds = max(1, $peer->started->diffInSeconds($peer->last_action));
        return mksize($diff / $seconds) . '/s';
    }

    public function getPeerDownloadSpeed($peer): string
    {
        $diff = $peer->downloaded - $peer->downloadoffset;
        if ($peer->isSeeder()) {
            $seconds = max(1, $peer->started->diffInSeconds($peer->finishedat));
        } else {
            $seconds = max(1, $peer->started->diffInSeconds($peer->last_action));
        }
        return mksize($diff / $seconds) . '/s';
    }

    public function getDownloadProgress($peer): string
    {
        return sprintf("%.2f%%", 100 * (1 - ($peer->to_go / $peer->relative_torrent->size)));
    }

    public function getShareRatio($peer)
    {
        if ($peer->downloaded) {
            $ratio = floor(($peer->uploaded / $peer->downloaded) * 1000) / 1000;
        } elseif ($peer->uploaded) {
            //@todo 读语言文件
            $ratio = '无限';
        } else {
            $ratio = '---';
        }
        return $ratio;
    }

    private function formatPeers($peers)
    {
        foreach ($peers as &$item) {
            $item->upload_text = sprintf('%s@%s', mksize($item->uploaded), $this->getPeerUploadSpeed($item));
            $item->download_text = sprintf('%s@%s', mksize($item->downloaded), $this->getPeerDownloadSpeed($item));
            $item->download_progress = $this->getDownloadProgress($item);
            $item->share_ratio = $this->getShareRatio($item);
            $item->connect_time_total = $item->started->diffForHumans();
            $item->last_action_human = $item->last_action->diffForHumans();
            $item->agent_human = htmlspecialchars(get_agent($item->peer_id, $item->agent));
        }
        return $peers;
    }


    public function listSnatches($torrentId)
    {
        $snatches = Snatch::query()
            ->where('torrentid', $torrentId)
            ->where('finished', Snatch::FINISHED_YES)
            ->with(['user'])
            ->orderBy('completedat', 'desc')
            ->paginate();
        foreach ($snatches as &$snatch) {
            $snatch->upload_text = sprintf('%s@%s', mksize($snatch->uploaded), $this->getSnatchUploadSpeed($snatch));
            $snatch->download_text = sprintf('%s@%s', mksize($snatch->downloaded), $this->getSnatchDownloadSpeed($snatch));
            $snatch->share_ratio = $this->getShareRatio($snatch);
            $snatch->seed_time = mkprettytime($snatch->seedtime);
            $snatch->leech_time = mkprettytime($snatch->leechtime);
            $snatch->completed_at_human = $snatch->completedat->diffForHumans();
            $snatch->last_action_human =  $snatch->last_action->diffForHumans();
        }
        return $snatches;
    }

    public function getSnatchUploadSpeed($snatch)
    {
        if ($snatch->seedtime <= 0) {
            $speed =  mksize(0);
        } else {
            $speed = mksize($snatch->uploaded / ($snatch->seedtime + $snatch->leechtime));
        }
        return "$speed/s";
    }

    public function getSnatchDownloadSpeed($snatch)
    {
        if ($snatch->leechtime <= 0) {
            $speed = mksize(0);
        } else {
            $speed = mksize($snatch->downloaded / $snatch->leechtime);
        }
        return "$speed/s";
    }

    public function encryptDownHash($id, $user): string
    {
        $key = $this->getEncryptDownHashKey($user);
        return (new Hashids($key))->encode($id);
    }

    public function decryptDownHash($downHash, $user)
    {
        $key = $this->getEncryptDownHashKey($user);
        return (new Hashids($key))->decode($downHash);
    }

    private function getEncryptDownHashKey($user)
    {
        if (!is_array($user) || empty($user['passkey']) || empty($user['id'])) {
            $user = User::query()->findOrFail(intval($user), ['id', 'passkey'])->toArray();
        }
        //down hash is relative to user passkey
        return md5($user['passkey'] . date('Ymd') . $user['id']);
    }

    public function getTrackerReportAuthKey($id, $uid, $initializeIfNotExists = false): string
    {
        $key = $this->getTrackerReportAuthKeySecret($id, $uid, $initializeIfNotExists);
        $hash = (new Hashids($key))->encode(date('Ymd'));
        return sprintf('%s|%s|%s', $id, $uid, $hash);
    }

    /**
     * check tracker report authkey
     * if valid, the result will be the date the key generate, else if will be empty string
     *
     * @date 2021/6/3
     * @time 20:29
     * @param $authKey
     * @return array
     * @throws NexusException
     */
    public function checkTrackerReportAuthKey($authKey)
    {
        $arr = explode('|', $authKey);
        if (count($arr) != 3) {
            throw new NexusException('Invalid authkey');
        }
        $id = $arr[0];
        $uid = $arr[1];
        $hash = $arr[2];
        $key = $this->getTrackerReportAuthKeySecret($id, $uid);
        return (new Hashids($key))->decode($hash);
    }

    private function getTrackerReportAuthKeySecret($id, $uid, $initializeIfNotExists = false)
    {
        $secret = TorrentSecret::query()
            ->where('uid', $uid)
            ->whereIn('torrent_id', [0, $id])
            ->orderBy('torrent_id', 'desc')
            ->orderBy('id', 'desc')
            ->first();
        if ($secret) {
            return $secret->secret;
        }
        if ($initializeIfNotExists) {
            $insert = [
                'uid' => $uid,
                'torrent_id' => 0,
                'secret' => Str::random(),
            ];
            TorrentSecret::query()->insert($insert);
            return $insert['secret'];
        }
        throw new NexusException('No valid report secret, please re-download this torrent.');
    }

    /**
     * reset user tracker report authkey secret
     *
     * @param $uid
     * @param int $torrentId
     * @return string
     * @todo wrap with transaction
     *
     * @date 2021/6/3
     * @time 20:15
     */
    public function resetTrackerReportAuthKeySecret($uid, $torrentId = 0): string
    {
        $insert = [
            'uid' => $uid,
            'secret' => Str::random(),
            'torrent_id' => $torrentId,
        ];
        if ($torrentId > 0) {
            return TorrentSecret::query()->insert($insert);
        }

        TorrentSecret::query()->where('uid', $uid)->delete();
        TorrentSecret::query()->insert($insert);
        return $insert['secret'];

    }

    public function getStickyStatus($torrent)
    {
        if (!$torrent instanceof Torrent) {
            $torrent = Torrent::query()->findOrFail((int)$torrent, ['id', 'pos_state']);
        }
    }


}

<?php

namespace App\Repositories;

use App\Exceptions\InsufficientPermissionException;
use App\Exceptions\NexusException;
use App\Models\AudioCodec;
use App\Models\Category;
use App\Models\Claim;
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
use App\Models\StaffMessage;
use App\Models\Standard;
use App\Models\Team;
use App\Models\Torrent;
use App\Models\TorrentOperationLog;
use App\Models\TorrentSecret;
use App\Models\TorrentTag;
use App\Models\User;
use Carbon\Carbon;
use Hashids\Hashids;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Nexus\Database\NexusDB;

class TorrentRepository extends BaseRepository
{
    /**
     *  fetch torrent list
     *
     * @param array $params
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getList(array $params, User $user)
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

        if (!empty($params['category_mode'])) {
            $query->whereHas('basic_category', function (Builder $query) use ($params) {
                $query->where('mode', $params['category_mode']);
            });
        }

        $query = $this->handleGetListSort($query, $params);

        $with = ['user', 'tags'];
        $torrents = $query->with($with)->paginate();
        $userArr = $user->toArray();
        foreach ($torrents as &$item) {
            $item->download_url = $this->getDownloadUrl($item->id, $userArr);
        }
        return $torrents;
    }

    public function getDetail($id, User $user)
    {
        $with = [
            'user', 'basic_audio_codec', 'basic_category', 'basic_codec', 'basic_media', 'basic_source', 'basic_standard', 'basic_team',
            'thanks' => function ($query) use ($user) {
                $query->where('userid', $user->id);
            },
            'reward_logs' => function ($query) use ($user) {
                $query->where('userid', $user->id);
            },
        ];
        $result = Torrent::query()->with($with)->withCount(['peers', 'thank_users', 'reward_logs'])->visible()->findOrFail($id);
        $result->download_url = $this->getDownloadUrl($id, $user->toArray());
        return $result;
    }

    private function getDownloadUrl($id, array $user): string
    {
        return sprintf(
            '%s/download.php?downhash=%s|%s',
            getSchemeAndHttpHost(), $user['id'], $this->encryptDownHash($id, $user)
        );
    }

    private function handleGetListSort(Builder $query, array $params)
    {
        if (empty($params['sort_field']) && empty($params['sort_type'])) {
            //the default torrent list sort
            return $query->orderBy('pos_state', 'desc')->orderBy('id', 'desc');
        }
        list($sortField, $sortType) = $this->getSortFieldAndType($params);
        return $query->orderBy($sortField, $sortType);
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
        $peers = Peer::query()
            ->where('torrent', $torrentId)
            ->groupBy('peer_id')
            ->with(['user', 'relative_torrent'])
            ->get()
            ->groupBy('seeder');
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
        return $snatches;
    }

    public function getSnatchUploadSpeed($snatch)
    {
        if ($snatch->seedtime <= 0) {
            $speed = mksize(0);
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
        if ($user instanceof User) {
            $user = $user->toArray();
        }
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
            do_log("[INSERT_TORRENT_SECRET] " . json_encode($insert));
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

    public function buildApprovalModal($user, $torrentId)
    {
        $user = $this->getUser($user);
        if ($user->class < Setting::get('authority.torrentmanage')) {
            throw new \RuntimeException("No permission !");
        }
        $torrent = Torrent::query()->findOrFail($torrentId, ['id', 'approval_status', 'banned']);
        $radios = [];
        foreach (Torrent::$approvalStatus as $key => $value) {
            if ($torrent->approval_status == $key) {
                $checked = " checked";
            } else {
                $checked = "";
            }
            $radios[] = sprintf(
                '<label><input type="radio" name="params[approval_status]" value="%s"%s>%s</label>',
                $key, $checked, nexus_trans("torrent.approval.status_text.$key")
            );
        }
        $id = "torrent-approval";
        $rows = [];
        $rowStyle = "display: flex; padding: 10px; align-items: center";
        $labelStyle = "width: 80px";
        $formId = "$id-form";
        $rows[] = sprintf(
            '<div class="%s-row" style="%s"><div style="%s">%s: </div><div>%s</div></div>',
            $id, $rowStyle, $labelStyle, nexus_trans('torrent.approval.status_label'), implode('', $radios)
        );
        $rows[] = sprintf(
            '<div class="%s-row" style="%s"><div style="%s">%s: </div><div><textarea name="params[comment]" rows="4" cols="40"></textarea></div></div>',
            $id, $rowStyle, $labelStyle, nexus_trans('torrent.approval.comment_label')
        );
        $rows[] = sprintf('<input type="hidden" name="params[torrent_id]" value="%s" />', $torrent->id);

        $html = sprintf('<div id="%s-box" style="padding: 15px 30px"><form id="%s">%s</form></div>', $id, $formId, implode('', $rows));

        return [
            'id' => $id,
            'form_id' => $formId,
            'title' => nexus_trans('torrent.approval.modal_title'),
            'content' => $html,
        ];

    }

    public function approval($user, array $params): array
    {
        $user = $this->getUser($user);
        $torrent = Torrent::query()->findOrFail($params['torrent_id'], ['id', 'banned', 'approval_status', 'visible', 'owner']);
        $lastLog = TorrentOperationLog::query()
            ->where('torrent_id', $params['torrent_id'])
            ->where('uid', $user->id)
            ->orderBy('id', 'desc')
            ->first();
        if ($torrent->approval_status == $params['approval_status'] && $lastLog && $lastLog->comment == $params['comment']) {
            //No change
            return $params;
        }
        $torrentUpdate = $torrentOperationLog = [];
        $torrentUpdate['approval_status'] = $params['approval_status'];
        $notifyUser = false;
        if ($params['approval_status'] == Torrent::APPROVAL_STATUS_ALLOW) {
            $torrentUpdate['banned'] = 'no';
            $torrentUpdate['visible'] = 'yes';
            if ($torrent->approval_status != $params['approval_status']) {
                $torrentOperationLog['action_type'] = TorrentOperationLog::ACTION_TYPE_APPROVAL_ALLOW;
            }
            if ($torrent->approval_status == Torrent::APPROVAL_STATUS_DENY) {
                $notifyUser = true;
            }
        } elseif ($params['approval_status'] == Torrent::APPROVAL_STATUS_DENY) {
            $torrentUpdate['banned'] = 'yes';
            $torrentUpdate['visible'] = 'no';
            //Deny, record and notify all the time
            $torrentOperationLog['action_type'] = TorrentOperationLog::ACTION_TYPE_APPROVAL_DENY;
            $notifyUser = true;
        } elseif ($params['approval_status'] == Torrent::APPROVAL_STATUS_NONE) {
            $torrentUpdate['banned'] = 'no';
            $torrentUpdate['visible'] = 'yes';
            if ($torrent->approval_status != $params['approval_status']) {
                $torrentOperationLog['action_type'] = TorrentOperationLog::ACTION_TYPE_APPROVAL_NONE;
            }
            if ($torrent->approval_status == Torrent::APPROVAL_STATUS_DENY) {
                $notifyUser = true;
            }
        } else {
            throw new \InvalidArgumentException("Invalid approval_status: " . $params['approval_status']);
        }
        if (isset($torrentOperationLog['action_type'])) {
            $torrentOperationLog['uid'] = $user->id;
            $torrentOperationLog['torrent_id'] = $torrent->id;
            $torrentOperationLog['comment'] = $params['comment'] ?? '';
        }

        NexusDB::transaction(function () use ($torrent, $torrentOperationLog, $torrentUpdate, $notifyUser) {
            $log = "torrent: " . $torrent->id;
            if (!empty($torrentUpdate)) {
                $log .= ", [UPDATE_TORRENT]: " . nexus_json_encode($torrentUpdate);
                $torrent->update($torrentUpdate);
            }
            if (!empty($torrentOperationLog)) {
                $log .= ", [ADD_TORRENT_OPERATION_LOG]: " . nexus_json_encode($torrentOperationLog);
                TorrentOperationLog::add($torrentOperationLog, $notifyUser);
            }
            do_log($log);
        });

        return $params;

    }

    public function renderApprovalStatus($approvalStatus, $show = null): string
    {
        if ($show === null) {
            $show = $this->shouldShowApprovalStatusIcon($approvalStatus);
        }
        if ($show) {
            return sprintf(
                '<span style="margin-left: 6px" title="%s">%s</span>',
                nexus_trans("torrent.approval.status_text.$approvalStatus"),
                \App\Models\Torrent::$approvalStatus[$approvalStatus]['icon']
            );
        }
        return '';
    }

    public function shouldShowApprovalStatusIcon($approvalStatus): bool
    {
        if (get_setting('torrent.approval_status_icon_enabled') == 'yes') {
            //启用审核状态图标，肯定显示
            return true;
        }
        if (
            $approvalStatus != \App\Models\Torrent::APPROVAL_STATUS_ALLOW
            && get_setting('torrent.approval_status_none_visible') == 'no'
        ) {
            //不启用审核状态图标，尽量不显示。在种子不是审核通过状态，而审核不通过又不能被用户看到时，显示
            return true;
        }
        return false;
    }

    public function syncTags($id, array $tagIdArr = [])
    {
        if (Auth::user()->class < Setting::get('authority.torrentmanage')) {
            throw new InsufficientPermissionException();
        }
        $idArr = Arr::wrap($id);
        return NexusDB::transaction(function () use ($idArr, $tagIdArr) {
            $insert = [];
            $time = now()->toDateTimeString();
            foreach ($idArr as $torrentId) {
                foreach ($tagIdArr as $tagId) {
                    $insert[] = [
                        'torrent_id' => $torrentId,
                        'tag_id' => $tagId,
                        'created_at' => $time,
                        'updated_at' => $time,
                    ];
                }
            }
            TorrentTag::query()->whereIn('torrent_id', $idArr)->delete();
            if (!empty($insert)) {
                TorrentTag::query()->insert($insert);
            }
            return count($insert);
        });

    }

    public function setPosState($id, $posState): int
    {
        if (Auth::user()->class < Setting::get('authority.torrentsticky')) {
            throw new InsufficientPermissionException();
        }
        $idArr = Arr::wrap($id);
        return Torrent::query()->whereIn('id', $idArr)->update(['pos_state' => $posState]);
    }


}

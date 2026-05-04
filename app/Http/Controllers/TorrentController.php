<?php

namespace App\Http\Controllers;

use App\Http\Resources\TorrentOperationLogResource;
use App\Http\Resources\TorrentResource;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\TorrentDenyReason;
use App\Models\TorrentOperationLog;
use App\Models\User;
use App\Repositories\TorrentRepository;
use App\Repositories\UploadRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class TorrentController extends Controller
{
    private $repository;

    public function __construct(TorrentRepository $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request, ?string $section = null)
    {
        do_log('controller torrent index entry');
        $result = $this->repository->getList($request, Auth::user(), $section);
        do_log('controller torrent index getList');
        $resource = TorrentResource::collection($result);
        do_log('controller torrent index prepare resource');

        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return array
     */
    public function store(Request $request)
    {
        $uploadRep = new UploadRepository;
        $newTorrent = $uploadRep->upload($request);
        $resource = new JsonResource(['id' => $newTorrent->id]);

        return $this->success($resource);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return array
     */
    public function show($id)
    {
        do_log('controller torrent show entry');
        /**
         * @var User
         */
        $user = Auth::user();
        $torrent = $this->repository->getDetail($id, $user);
        do_log('controller torrent show getDetail');
        $resource = new TorrentResource($torrent);
        $additional = [];
        if ($this->hasExtraField('bonus_reward_values')) {
            $additional['bonus_reward_values'] = Setting::getBonusRewardOptions();
        }
        $extraSettingsNames = ['torrent.claim_torrent_user_counts_up_limit'];
        $this->appendExtraSettings($additional, $extraSettingsNames);
        $resource->additional($additional);
        do_log('controller torrent show prepare resource');

        return $this->success($resource);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     */
    public function destroy($id)
    {
        //
    }

    public function searchBox()
    {
        $result = $this->repository->getSearchBox();

        return $this->success($result);
    }

    public function approvalPage(Request $request)
    {
        user_can('torrent-approval', true);
        $request->validate(['torrent_id' => 'required']);
        $torrentId = $request->torrent_id;
        $torrent = Torrent::query()->findOrFail($torrentId, Torrent::$commentFields);
        $denyReasons = TorrentDenyReason::query()->orderBy('priority', 'desc')->get();

        return view('torrent/approval', compact('torrent', 'denyReasons'));
    }

    public function approvalLogs(Request $request)
    {
        user_can('torrent-approval', true);
        $request->validate(['torrent_id' => 'required']);
        $torrentId = $request->torrent_id;
        $actionTypes = [
            TorrentOperationLog::ACTION_TYPE_APPROVAL_NONE,
            TorrentOperationLog::ACTION_TYPE_APPROVAL_ALLOW,
            TorrentOperationLog::ACTION_TYPE_APPROVAL_DENY,
        ];
        $records = TorrentOperationLog::query()
            ->with(['user'])
            ->where('torrent_id', $torrentId)
            ->whereIn('action_type', $actionTypes)
            ->orderBy('id', 'desc')
            ->paginate($request->limit);

        $resource = TorrentOperationLogResource::collection($records);

        return $this->success($resource);
    }

    public function approval(Request $request)
    {
        user_can('torrent-approval', true);
        $request->validate([
            'torrent_id' => 'required',
            'approval_status' => 'required',
        ]);
        $params = $request->all();
        $this->repository->approval(Auth::user(), $params);

        return $this->success($params);
    }

    public function queryByPiecesHash(Request $request)
    {
        $request->validate([
            'pieces_hash' => 'required|array',
        ]);
        $result = $this->repository->getPiecesHashCache($request->pieces_hash);

        return $this->success($result ?: (object) []);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Resources\RewardResource;
use App\Http\Resources\PeerResource;
use App\Http\Resources\SnatchResource;
use App\Models\Peer;
use App\Models\Snatch;
use App\Repositories\RewardRepository;
use App\Repositories\TorrentRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RewardController extends Controller
{
    private $repository;

    public function __construct(RewardRepository $repository)
    {
        $this->repository = $repository;
    }
    /**
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        $request->validate([
            'torrent_id' => 'required',
        ]);
        $result = $this->repository->getList($request->all());
        $resource = RewardResource::collection($result);
        $resource->additional([
            'page_title' => nexus_trans('reward.index.page_title'),
        ]);

        return $this->success($resource);
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $request->validate([
            'torrent_id' => 'required',
            'value' => 'required',
        ]);
        $result = $this->repository->store($request->torrent_id, $request->value, Auth::user());
        $resource = new RewardResource($result);
        return $this->success($resource, '赠魔成功！');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}

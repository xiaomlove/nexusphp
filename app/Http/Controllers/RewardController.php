<?php

namespace App\Http\Controllers;

use App\Http\Resources\RewardResource;
use App\Repositories\RewardRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class RewardController extends Controller
{
    private $repository;

    public function __construct(RewardRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
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
     * @return Response
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
     */
    public function show($id)
    {
        //
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
}

<?php

namespace App\Http\Controllers;

use App\Http\Resources\PeerResource;
use App\Http\Resources\SnatchResource;
use App\Models\Peer;
use App\Models\Snatch;
use App\Repositories\TorrentRepository;
use Illuminate\Http\Request;

class SnatchController extends Controller
{
    private $repository;

    public function __construct(TorrentRepository $repository)
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
        $snatches = $this->repository->listSnatches($request->torrent_id);
        $resource = SnatchResource::collection($snatches);
        $resource->additional([
            'card_titles' => Snatch::$cardTitles,
            'page_title' => nexus_trans('snatch.index.page_title'),
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
        //
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

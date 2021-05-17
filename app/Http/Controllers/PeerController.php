<?php

namespace App\Http\Controllers;

use App\Http\Resources\PeerResource;
use App\Models\Peer;
use App\Models\Torrent;
use App\Repositories\TorrentRepository;
use Illuminate\Http\Request;

class PeerController extends Controller
{
    private $repository;

    public function __construct(TorrentRepository $repository)
    {
        $this->repository = $repository;
    }
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $request->validate([
            'torrent_id' => 'required',
        ]);

        $response = [
            'seeder_list' => [],
            'leecher_list' => [],
            'card_titles' => Peer::$cardTitles,
            'page_title' => nexus_trans('peer.index.page_title'),
        ];
        $result = $this->repository->listPeers($request->torrent_id);
        if ($result['seeder_list']->isNotEmpty()) {
            $response['seeder_list'] = PeerResource::collection($result['seeder_list']);
        }
        if ($result['leecher_list']->isNotEmpty()) {
            $response['leecher_list'] = PeerResource::collection($result['leecher_list']);
        }

        return $this->success($response);

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

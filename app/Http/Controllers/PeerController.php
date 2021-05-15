<?php

namespace App\Http\Controllers;

use App\Http\Resources\PeerResource;
use App\Models\Peer;
use App\Models\Torrent;
use Illuminate\Http\Request;

class PeerController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $torrentId = $request->torrent_id;
        $peers = Peer::query()->where('torrent', $torrentId)->with(['user', 'relative_torrent'])->get()->groupBy('seeder');
        $seederResource = [];
        $leecherResource = [];
        if ($peers->has(Peer::SEEDER_YES)) {
            $seederResource = PeerResource::collection($peers->get(Peer::SEEDER_YES));
        }
        if ($peers->has(Peer::SEEDER_NO)) {
            $leecherResource = PeerResource::collection($peers->get(Peer::SEEDER_NO));
        }

        $response = [
            'seeder_list' => $seederResource,
            'leecher_list' => $leecherResource,
            'card_titles' => Peer::$cardTitles,
        ];

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

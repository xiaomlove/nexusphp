<?php

namespace App\Http\Controllers;

use App\Http\Resources\PeerResource;
use App\Http\Resources\SnatchResource;
use App\Models\Peer;
use App\Models\Snatch;
use Illuminate\Http\Request;

class SnatchController extends Controller
{
    /**
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        $torrentId = $request->torrent_id;
        $snatches = Snatch::query()->where('torrentid', $torrentId)->with(['user'])->paginate();
        $resource = SnatchResource::collection($snatches);
        $resource->additional(['card_titles' => Snatch::$cardTitles]);

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

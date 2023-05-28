<?php

namespace App\Http\Controllers;

use App\Http\Resources\TorrentResource;
use App\Models\Torrent;
use App\Repositories\BookmarkRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BookmarkController extends Controller
{
    private $repository;

    public function __construct(BookmarkRepository $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
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
            'torrent_id' => 'required|integer',
        ]);
        $result = $this->repository->add(Auth::user(), $request->torrent_id);
        return $this->success($result->toArray(), nexus_trans('bookmark.actions.store_success'));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
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
        $result = $this->repository->remove(Auth::user(), $id);
        return $this->success($result, nexus_trans('bookmark.actions.delete_success'));
    }

}

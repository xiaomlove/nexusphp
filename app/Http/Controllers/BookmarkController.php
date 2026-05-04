<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookmarkResource;
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

    public function index(Request $request) {}

    /**
     * Store a newly created resource in storage.
     *
     * @return array
     */
    public function store(Request $request)
    {
        $request->validate([
            'torrent_id' => 'required|integer',
        ]);
        $result = $this->repository->add(Auth::user(), $request->torrent_id);
        $resource = new BookmarkResource($result);

        return $this->success($resource, nexus_trans('bookmark.actions.store_success'));
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     */
    public function show($id) {}

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
     * @return array
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'torrent_id' => 'required|integer',
        ]);
        $result = $this->repository->remove(Auth::user(), $request->torrent_id);

        return $this->success(true, nexus_trans('bookmark.actions.delete_success'));
    }
}

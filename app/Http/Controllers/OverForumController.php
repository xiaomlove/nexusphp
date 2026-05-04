<?php

namespace App\Http\Controllers;

use App\Http\Resources\OverForumResource;
use App\Models\OverForum;
use Illuminate\Http\Request;

class OverForumController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return array
     */
    public function index()
    {
        $list = OverForum::query()->orderBy('sort', 'asc')->get();
        $resource = OverForumResource::collection($list);

        return $this->success($resource);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(OverForum $overForum)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(OverForum $overForum)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, OverForum $overForum)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(OverForum $overForum)
    {
        //
    }
}

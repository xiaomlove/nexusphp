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
        $list = OverForum::query()->orderBy("sort", "asc")->get();
        $resource = OverForumResource::collection($list);
        return $this->success($resource);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
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
     * @param  \App\Models\OverForum  $overForum
     * @return \Illuminate\Http\Response
     */
    public function show(OverForum $overForum)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\OverForum  $overForum
     * @return \Illuminate\Http\Response
     */
    public function edit(OverForum $overForum)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\OverForum  $overForum
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, OverForum $overForum)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\OverForum  $overForum
     * @return \Illuminate\Http\Response
     */
    public function destroy(OverForum $overForum)
    {
        //
    }
}

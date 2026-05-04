<?php

namespace App\Http\Controllers;

use App\Http\Resources\TopicResource;
use App\Models\OverForum;
use App\Models\Topic;
use Illuminate\Http\Request;

class TopicController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return array
     */
    public function index(Request $request)
    {
        $forumId = $request->forum_id;
        $query = Topic::query()
            ->orderBy('sticky', 'desc')
            ->with('user', 'firstPost', 'lastPost');
        if ($forumId) {
            $query->where('forumid', $forumId);
        }
        $list = $query->get();
        $resource = TopicResource::collection($list);

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

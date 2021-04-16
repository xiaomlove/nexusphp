<?php

namespace App\Http\Controllers;

use App\Http\Resources\AgentAllowResource;
use App\Models\AgentAllow;
use Illuminate\Http\Request;

class AgentAllowController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return array
     */
    public function index()
    {
        $result = AgentAllow::query()->orderBy('id', 'desc')->paginate();
        $resource = AgentAllowResource::collection($result);
        return success('agent allow list', $resource);
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
     * @return array
     */
    public function show($id)
    {
        $result = AgentAllow::query()->findOrFail($id);
        $resource = new AgentAllowResource($result);
        return success('agent allow detail', $resource);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return array
     */
    public function update(Request $request, $id)
    {
        $result = AgentAllow::query()->findOrFail($id);
        $result->update($request->all());
        $resource = new AgentAllowResource($result);
        return success('agent allow update', $resource);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return array
     */
    public function destroy($id)
    {
        $result = AgentAllow::query()->findOrFail($id);
        $deleted = $result->delete();
        return success('agent allow delete', [$deleted]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\AgentAllowRequest;
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
        $result = AgentAllow::query()->paginate();
        $resource = AgentAllowResource::collection($result);
        return success('agent allow list', $resource);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function store(AgentAllowRequest $request)
    {
        $result = AgentAllow::query()->create($request->all());
        $resource = new AgentAllowResource($result);
        return success('agent allow store', $resource);
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
        $success = $result->delete();
        return success('agent allow delete', $success);
    }
}

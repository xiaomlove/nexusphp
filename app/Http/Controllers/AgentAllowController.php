<?php

namespace App\Http\Controllers;

use App\Http\Resources\AgentAllowResource;
use App\Models\AgentAllow;
use App\Repositories\AgentAllowRepository;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AgentAllowController extends Controller
{
    private $repository;

    public function __construct(AgentAllowRepository $repository)
    {
        $this->repository = $repository;
    }

    private function getRules(): array
    {
        return [
            'family' => 'required|string',
            'start_name' => 'required|string',

            'peer_id_pattern' => 'required|string',
            'peer_id_match_num' => 'required|numeric',
            'peer_id_matchtype' => ['required', Rule::in(array_keys(AgentAllow::$matchTypes))],
            'peer_id_start' => 'required|string',

            'agent_pattern' => 'required|string',
            'agent_match_num' => 'required|numeric',
            'agent_matchtype' => ['required', Rule::in(array_keys(AgentAllow::$matchTypes))],
            'agent_start' => 'required|string',

            'exception' => ['required', Rule::in(['yes', 'no'])],
            'allowhttps' => ['required', Rule::in(['yes', 'no'])],
        ];
    }
    /**
     * Display a listing of the resource.
     *
     * @return array
     */
    public function index(Request $request)
    {
        $result = $this->repository->getList($request->all());
        $resource = AgentAllowResource::collection($result);
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
        $request->validate($this->getRules());
        $result = $this->repository->store($request->all());
        $resource = new AgentAllowResource($result);
        return $this->success($resource);
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
        return $this->success($resource);
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
        $request->validate($this->getRules());
        $result = $this->repository->update($request->all(), $id);
        $resource = new AgentAllowResource($result);
        return $this->success($resource);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return array
     */
    public function destroy($id)
    {
        $result = $this->repository->delete($id);
        return $this->success($result);
    }

    public function all()
    {
        $result = AgentAllow::query()->orderBy('id', 'desc')->get();
        $resource = AgentAllowResource::collection($result);
        return $this->success($resource);
    }

    public function check(Request $request)
    {
        $request->validate([
            'peer_id' => 'required|string',
            'agent' => 'required|string',
        ]);
        $result = $this->repository->checkClient($request->peer_id, $request->agent, true);
        return $this->success($result->toArray(), sprintf("Congratulations! the client is allowed by ID: %s", $result->id));
    }
}

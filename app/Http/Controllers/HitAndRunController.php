<?php

namespace App\Http\Controllers;

use App\Http\Resources\HitAndRunResource;
use App\Models\HitAndRun;
use App\Repositories\HitAndRunRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class HitAndRunController extends Controller
{
    private $repository;

    public function __construct(HitAndRunRepository $repository)
    {
        $this->repository = $repository;
    }

    private function getRules(): array
    {
        return [
            'family_id' => 'required|numeric',
            'name' => 'required|string',
            'peer_id' => 'required|string',
            'agent' => 'required|string',
            'comment' => 'required|string',

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
        $resource = HitAndRunResource::collection($result);
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
        $resource = new HitAndRunResource($result);
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
        $result = $this->repository->getDetail($id);
        $resource = new HitAndRunResource($result);
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
        $resource = new HitAndRunResource($result);
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

    public function listStatus(): array
    {
        $result = $this->repository->listStatus();
        return $this->success($result);
    }

    public function pardon($id): array
    {
        $result = $this->repository->pardon($id, Auth::user());
        return $this->success($result);
    }

    public function bulkPardon(Request $request): array
    {
        $result = $this->repository->bulkPardon($request->all(), Auth::user());
        return $this->success(['result' => $result],"Affected: " . intval($result));
    }

    public function bulkDelete(Request $request): array
    {
        $result = $this->repository->bulkDelete($request->all(), Auth::user());
        return $this->success(['result' => $result],"Affected: " . intval($result));
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Resources\MedalResource;
use App\Repositories\MedalRepository;
use Illuminate\Http\Request;

class MedalController extends Controller
{
    private $repository;

    public function __construct(MedalRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        $result = $this->repository->getList($request->all());
        $resource = MedalResource::collection($result);
        $resource->additional([
            'page_title' => nexus_trans('medal.admin.list.page_title'),
        ]);
        return $this->success($resource);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string',
            'price' => 'required|integer|min:1',
            'image_large' => 'required|url',
            'image_small' => 'required|url',
            'duration' => 'nullable|integer|min:-1',
        ];
        $request->validate($rules);
        $result = $this->repository->store($request->all());
        $resource = new MedalResource($result);
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
        $resource = new MedalResource($result);
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
        $rules = [
            'name' => 'required|string',
            'price' => 'required|integer|min:1',
            'image_large' => 'required|url',
            'image_small' => 'required|url',
            'duration' => 'nullable|integer|min:-1',
        ];
        $request->validate($rules);
        $result = $this->repository->update($request->all(), $id);
        $resource = new MedalResource($result);
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
        return $this->success($result, 'Delete medal success!');
    }


}

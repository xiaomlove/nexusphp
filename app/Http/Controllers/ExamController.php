<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExamResource;
use App\Http\Resources\UserResource;
use App\Repositories\ExamRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    private $repository;

    public function __construct(ExamRepository $repository)
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
        $resource = ExamResource::collection($result);
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
            'begin' => 'required|date_format:Y-m-d H:i:s',
            'end' => 'required|date_format:Y-m-d H:i:s',
            'requires' => 'required|array|min:1',
            'filters' => 'required|array|min:1',
        ];
        $request->validate($rules);
        $result = $this->repository->store($request->all());
        $resource = new ExamResource($result);
        return $this->success($resource);
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
        $rules = [
            'name' => 'required|string',
            'begin' => 'required|date_format:Y-m-d H:i:s',
            'end' => 'required|date_format:Y-m-d H:i:s',
            'requires' => 'required|array|min:1',
            'filters' => 'required|array|min:1',
        ];
        $request->validate($rules);
        $result = $this->repository->update($request->all(), $id);
        $resource = new ExamResource($result);
        return $this->success($resource);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    public function indexes()
    {
        $result = $this->repository->listIndexes();
        return $this->success($result);
    }

}

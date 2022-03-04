<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExamResource;
use App\Http\Resources\ExamUserResource;
use App\Http\Resources\UserResource;
use App\Models\Exam;
use App\Repositories\ExamRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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
        $resource->additional([
            'page_title' => nexus_trans('exam.admin.list.page_title'),
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
            'indexes' => 'required|array|min:1',
            'indexes.*.index' => ['required', Rule::in(array_keys(Exam::$indexes))],
            'indexes.*.require_value' => 'nullable|numeric',
            'status' => 'required|in:0,1',
            'duration' => 'nullable|numeric'
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
     * @return array
     */
    public function show($id)
    {
        $result = $this->repository->getDetail($id);
        $resource = new ExamResource($result);
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
            'indexes' => 'required|array|min:1',
            'indexes.*.index' => ['required', Rule::in(array_keys(Exam::$indexes))],
            'indexes.*.require_value' => 'nullable|numeric',
            'status' => 'required|in:0,1',
            'duration' => 'nullable|numeric'
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
     * @return array
     */
    public function destroy($id)
    {
        $result = $this->repository->delete($id);
        return $this->success($result, 'Delete exam success!');
    }

    public function indexes()
    {
        $result = $this->repository->listIndexes();
        return $this->success($result);
    }

    public function all()
    {
        $result = Exam::query()->orderBy('id', 'desc')->get();
        $resource = ExamResource::collection($result);
        return $this->success($resource);
    }

}

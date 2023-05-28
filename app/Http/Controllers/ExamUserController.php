<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExamResource;
use App\Http\Resources\ExamUserResource;
use App\Http\Resources\UserResource;
use App\Repositories\ExamRepository;
use App\Repositories\UserRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExamUserController extends Controller
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
        $result = $this->repository->listUser($request->all());
        $resource = ExamUserResource::collection($result);
        $resource->additional([
            'page_title' => nexus_trans('exam-user.admin.list.page_title'),
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
            'uid' => 'required',
        ];
        $request->validate($rules);
        $timeRange = $request->get('time_range', []);
        $begin = isset($timeRange[0]) ? Carbon::parse($timeRange[0])->toDateTimeString() : null;
        $end = isset($timeRange[1])? Carbon::parse($timeRange[1])->toDateTimeString() : null;

        $result = $this->repository->assignToUser($request->uid, $request->exam_id, $begin, $end);
        $resource = new ExamUserResource($result);
        return $this->success($resource, 'Assign exam success!');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return array
     */
    public function show($id)
    {

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

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return array
     */
    public function destroy($id)
    {
        $result = $this->repository->removeExamUser($id);
        return $this->success($result, 'Remove user exam success!');
    }

    public function avoid(Request $request)
    {
        $request->validate(['id' => 'required']);
        $result = $this->repository->avoidExamUser($request->id);
        return $this->success($result, 'Avoid user exam success!');
    }

    public function recover(Request $request)
    {
        $request->validate(['id' => 'required']);
        $result = $this->repository->recoverExamUser($request->id);
        return $this->success($result, 'Recover user exam success!');
    }

    public function bulkAvoid(Request $request): array
    {
        $result = $this->repository->avoidExamUserBulk($request->all(), Auth::user());
        return $this->success(['result' => $result],"Affected: " . intval($result));
    }

    public function bulkDelete(Request $request): array
    {
        $result = $this->repository->removeExamUserBulk($request->all(), Auth::user());
        return $this->success(['result' => $result],"Affected: " . intval($result));
    }

}

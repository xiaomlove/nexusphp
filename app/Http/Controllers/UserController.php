<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExamResource;
use App\Http\Resources\UserResource;
use App\Repositories\ExamRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    private $repository;

    public function __construct(UserRepository $repository)
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
        $resource = UserResource::collection($result);
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
            'username' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:6|max:40',
            'password_confirmation' => 'required|string|same:password'
        ];
        $request->validate($rules);
        $result = $this->repository->store($request->all());
        $resource = new UserResource($result);
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
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
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

    public function resetPassword(Request $request)
    {
        $rules = [
            'username' => 'required|string|exists:users',
            'password' => 'required|string|min:6|max:40',
            'password_confirmation' => 'required|same:password',
        ];
        $request->validate($rules);
        $result = $this->repository->resetPassword($request->repositoryname, $request->password, $request->password_confirmation);
        $resource = new UserResource($result);
        return $this->success($resource);
    }

    public function classes()
    {
        $result = $this->repository->listClass();
        return $this->success($result);
    }

    public function base()
    {
        $id = Auth::id();
        $result = $this->repository->getBase($id);
        $resource = new UserResource($result);
        return $this->success($resource);
    }

    public function exams()
    {
        $id = Auth::id();
        $examRepository = new ExamRepository();
        $result = $examRepository->listMatchExam($id);
        $resource = ExamResource::collection($result);
        return $this->success($resource);
    }
}

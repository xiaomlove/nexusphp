<?php

namespace App\Http\Controllers;

use App\Http\Resources\NewsResource;
use App\Models\News;
use App\Repositories\NewsRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Nexus\Database\NexusDB;

class NewsController extends Controller
{
    private $repository;

    public function __construct(NewsRepository $repository)
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
        $resource = NewsResource::collection($result);
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
        $resource = new NewsResource($result);
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
        $result = News::query()->findOrFail($id);
        $resource = new NewsResource($result);
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
        $resource = new NewsResource($result);
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

    /**
     * @return array
     */
    public function latest()
    {
        $user = Auth::user();
        $result = News::query()->orderBy('id', 'desc')->first();
        if ($result) {
            $resource = new NewsResource($result);
        } else {
            $resource = new JsonResource(null);
        }
        $resource->additional([
            'site_info' => site_info(),
        ]);

        /**
         * Visiting the home page is the same as viewing the latest news
         * @see functions.php line 2590
         */
        $user->update(['last_home' => Carbon::now()]);
        return $this->success($resource);
    }

}

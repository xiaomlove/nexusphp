<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExamResource;
use App\Http\Resources\InviteResource;
use App\Http\Resources\TorrentResource;
use App\Http\Resources\UserResource;
use App\Models\Peer;
use App\Models\Snatch;
use App\Models\User;
use App\Repositories\ExamRepository;
use App\Repositories\TorrentRepository;
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
     * @return array
     */
    public function show($id)
    {
        $result = $this->repository->getDetail($id);
        return $this->success($result);
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
            'uid' => 'required',
            'password' => 'required|string|min:6|max:40',
            'password_confirmation' => 'required|same:password',
        ];
        $request->validate($rules);
        $result = $this->repository->resetPassword($request->uid, $request->password, $request->password_confirmation);
        return $this->success($result, 'Reset password success!');
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

    public function matchExams(Request $request)
    {
        $request->validate([
            'uid' => 'required',
        ]);
        $examRepository = new ExamRepository();
        $result = $examRepository->listMatchExam($request->uid);
        $resource = ExamResource::collection($result);
        return $this->success($resource);
    }

    public function disable(Request $request)
    {
        $request->validate([
            'uid' => 'required',
            'reason' => 'required',
        ]);
        $result = $this->repository->disableUser(Auth::user(), $request->uid, $request->reason);
        return $this->success($result, 'Disable user success!');
    }

    public function enable(Request $request)
    {
        $request->validate([
            'uid' => 'required',
        ]);
        $result = $this->repository->enableUser(Auth::user(), $request->uid);
        return $this->success($result, 'Enable user success!');
    }

    public function inviteInfo(Request $request)
    {
        $request->validate([
            'uid' => 'required',
        ]);
        $result = $this->repository->getInviteInfo($request->uid);
        $resource = $result ? (new InviteResource($result)) : null;
        return $this->success($resource);
    }

    public function modComment(Request $request)
    {
        $request->validate([
            'uid' => 'required',
        ]);
        $result = $this->repository->getModComment($request->uid);
        return $this->success($result);
    }

    public function me()
    {
        $user = Auth::user();

        $resource = $this->getUserProfile($user->id);

        $rows = [
            [
                ['icon' => 'icon-user', 'label' => '种子评论', 'name' => 'comments_count'],
                ['icon' => 'icon-user', 'label' => '论坛帖子', 'name' => 'posts_count'],
            ],[
                ['icon' => 'icon-user', 'label' => '发布种子', 'name' => 'torrents_count'],
                ['icon' => 'icon-user', 'label' => '当前做种', 'name' => 'seeding_torrents_count'],
                ['icon' => 'icon-user', 'label' => '当前下载', 'name' => 'leeching_torrents_count'],
                ['icon' => 'icon-user', 'label' => '完成种子', 'name' => 'completed_torrents_count'],
                ['icon' => 'icon-user', 'label' => '未完成种子', 'name' => 'incomplete_torrents_count'],
            ]
        ];
        $resource->additional([
            'card_titles' => User::$cardTitles,
            'rows' => $rows
        ]);

        return $this->success($resource);
    }

    private function getUserProfile($id)
    {
        $user = User::query()->withCount([
            'comments', 'posts', 'seeding_torrents', 'leeching_torrents',
            'torrents' => function ($query) use ($id) {$query->whereHas('snatches');},
            'completed_torrents' => function ($query) use ($id) {$query->where('torrents.owner', '!=', $id);},
            'incomplete_torrents' => function ($query) use ($id) {$query->where('torrents.owner', '!=', $id);},
        ])->findOrFail($id);
        $resource = new UserResource($user);
        return $resource;
    }

    public function publishTorrent(Request $request)
    {
        $user = Auth::user();

        $result = $user->torrents()->orderBy('id', 'desc')->paginate();

        $resource = TorrentResource::collection($result);

        return $resource;

    }

    public function seedingTorrent(Request $request)
    {
        $user = Auth::user();

        $result = $user->peers_torrents()->where('seeder', Peer::SEEDER_YES)->orderBy('torrent', 'desc')->paginate();

        $resource = TorrentResource::collection($result);

        return $resource;

    }

    public function LeechingTorrent(Request $request)
    {
        $user = Auth::user();

        $result = $user->peers_torrents()->where('seeder', Peer::SEEDER_NO)->orderBy('torrent', 'desc')->paginate();

        $resource = TorrentResource::collection($result);

        return $resource;

    }

    public function finishedTorrent(Request $request)
    {
        $user = Auth::user();

        $result = $user->snatched_torrents()
            ->where('owner', '<>', $user->id)
            ->where('finished', Snatch::FINISHED_YES)
            ->orderBy('torrentid', 'desc')
            ->paginate();

        $resource = TorrentResource::collection($result);

        return $resource;

    }

    public function notFinishedTorrent(Request $request)
    {
        $user = Auth::user();

        $result = $user->snatched_torrents()
            ->where('owner', '<>', $user->id)
            ->where('finished', Snatch::FINISHED_NO)
            ->orderBy('torrentid', 'desc')
            ->paginate();

        $resource = TorrentResource::collection($result);

        return $resource;

    }

    public function incrementDecrement(Request $request): array
    {
        $user = Auth::user();
        $request->validate([
            'uid' => 'required',
            'action' => 'required',
            'field' => 'required',
            'value' => 'required|numeric',
        ]);
        $result = $this->repository->incrementDecrement($user, $request->uid, $request->action, $request->field, $request->value, $request->reason);
        return $this->success(['success' => $result]);
    }

    public function removeTwoStepAuthentication(Request $request): array
    {
        $user = Auth::user();
        $request->validate([
            'uid' => 'required',
        ]);
        $result = $this->repository->removeTwoStepAuthentication($user, $request->uid, );
        return $this->success(['success' => $result]);
    }

}

<?php

namespace App\Http\Controllers;

use App\Http\Resources\RewardResource;
use App\Http\Resources\TorrentResource;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\TorrentRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TorrentController extends Controller
{
    private $repository;

    public function __construct(TorrentRepository $repository)
    {
        $this->repository = $repository;
    }

    public function index(Request $request)
    {
        $params = $request->all();
        $params['visible'] = Torrent::VISIBLE_YES;
        $params['category_mode'] = Setting::get('main.browsecat');
        $result = $this->repository->getList($params, Auth::user());
        $resource = TorrentResource::collection($result);
        $resource->additional([
            'page_title' => nexus_trans('torrent.index.page_title'),
        ]);

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
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        /**
         * @var User
         */
        $user = Auth::user();
        $result = $this->repository->getDetail($id, $user);
        $isBookmarked = $user->bookmarks()->where('torrentid', $id)->exists();

        $resource = new TorrentResource($result);
        $resource->additional([
            'page_title' => nexus_trans('torrent.show.page_title'),
            'field_labels' => Torrent::getFieldLabels(),
            'is_bookmarked' => (int)$isBookmarked,
            'bonus_reward_values' => Torrent::BONUS_REWARD_VALUES,
        ]);

        return $this->success($resource);
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

    public function searchBox()
    {
        $result = $this->repository->getSearchBox();

        return $this->success($result);
    }
}

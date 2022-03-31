<?php

namespace App\Http\Controllers;

use App\Http\Resources\ThankResource;
use App\Models\Setting;
use App\Models\Thank;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ThankController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $torrentId = $request->torrent_id;
        $thanks = Thank::query()
            ->where('torrentid', $torrentId)
            ->whereHas('user')
            ->with(['user'])
            ->paginate();
        $resource = ThankResource::collection($thanks);
        $resource->additional([
            'page_title' => nexus_trans('thank.index.page_title'),
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
        $user = Auth::user();
        $request->validate(['torrent_id' => 'required']);
        $torrentId = $request->torrent_id;
        $torrent = Torrent::query()->findOrFail($torrentId, Torrent::$commentFields);
        $torrent->checkIsNormal();
        $torrentOwner = User::query()->findOrFail($torrent->owner);
        if ($user->id == $torrentOwner->id) {
            throw new \LogicException("you can't thank to yourself");
        }
        $torrentOwner->checkIsNormal();
        if ($user->thank_torrent_logs()->where('torrentid', $torrentId)->exists()) {
            throw new \LogicException("you already thank this torrent");
        }

        $result = DB::transaction(function () use ($user, $torrentOwner, $torrent) {
            $thank = $user->thank_torrent_logs()->create(['torrentid' => $torrent->id]);
            $sayThanksBonus = Setting::get('bonus.saythanks');
            $receiveThanksBonus = Setting::get('bonus.receivethanks');
            if ($sayThanksBonus > 0) {
                $affectedRows = User::query()
                    ->where('id', $user->id)
                    ->where('seedbonus', $user->seedbonus)
                    ->increment('seedbonus', $sayThanksBonus);
                if ($affectedRows != 1) {
                    do_log("affectedRows: $affectedRows, query: " . last_query(), 'error');
                    throw new \RuntimeException("increment user bonus fail.");
                }
            }
            if ($receiveThanksBonus > 0) {
                $affectedRows = User::query()
                    ->where('id', $torrentOwner->id)
                    ->where('seedbonus', $torrentOwner->seedbonus)
                    ->increment('seedbonus', $receiveThanksBonus);
                if ($affectedRows != 1) {
                    do_log("affectedRows: $affectedRows, query: " . last_query(), 'error');
                    throw new \RuntimeException("increment owner bonus fail.");
                }
            }
            return $thank;
        });
        $resource = new ThankResource($result);
        return $this->success($resource, '说谢谢成功！');
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
}

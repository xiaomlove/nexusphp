<?php

namespace App\Http\Controllers;

use App\Http\Resources\MessageResource;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    /**
     * message list
     *
     * @param Request $request
     * @return array
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = $user->receive_messages()
            ->with(['send_user'])
            ->orderBy('id', 'desc');

        if ($request->unread) {
            $query->where('unread', 'yes');
        }
        $messages = $query->paginate();
        $resource = MessageResource::collection($messages);
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
        $message = Message::query()->with(['send_user'])->findOrFail($id);
        $message->update(['unread' => 'no']);
        $resource = new MessageResource($message);
        $resource->additional([
            'page_title' => nexus_trans('message.show.page_title'),
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

    public function listUnread(Request $request): array
    {
        $user = Auth::user();
        $query = $user->receive_messages()
            ->with(['send_user'])
            ->orderBy('id', 'desc')
            ->where('unread', 'yes');

        $messages = $query->paginate();
        $resource = MessageResource::collection($messages);
        $resource->additional([
            'site_info' => site_info(),
        ]);
        return $this->success($resource);
    }

    public function countUnread()
    {
        $user = Auth::user();
        $count = $user->receive_messages()->where('unread', 'yes')->count();
        return $this->success(['unread' => $count]);
    }
}

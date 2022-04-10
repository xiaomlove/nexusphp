<?php

namespace App\Http\Controllers;

use App\Http\Resources\PollResource;
use App\Models\Poll;
use App\Repositories\PollRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class PollController extends Controller
{
    private $repository;

    public function __construct()
    {

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
        $resource = PollResource::collection($result);
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
        $resource = new PollResource($result);
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
        $result = Poll::query()->findOrFail($id);
        $resource = new PollResource($result);
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
        $resource = new PollResource($result);
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
        $poll = Poll::query()->orderBy('id', 'desc')->first();
        $selection = null;
        $answerStats = [];
        if ($poll) {
            $baseAnswerQuery = $poll->answers()->where('selection', '<=', Poll::MAX_OPTION_INDEX);
            $poll->answers_count = (clone $baseAnswerQuery)->count();
            $answer = $poll->answers()->where('userid', $user->id)->first();
            $options = [];
            for ($i = 0; $i <= Poll::MAX_OPTION_INDEX; $i++) {
                $field = "option{$i}";
                $value = $poll->{$field};
                if ($value !== '') {
                    $options[$i] = $value;
                }
            }
            if ($answer) {
                $selection = $answer->selection;
            } else {
                $options["255"] = "弃权(我想偷看结果！)";
            }
            $poll->options = $options;

            $answerStats = (clone $baseAnswerQuery)
                ->selectRaw("selection, count(*) as count")->groupBy("selection")
                ->get()->pluck('count', 'selection')->toArray();
            foreach ($answerStats as $index => &$value) {
                $value = number_format(($value / $poll->answers_count) * 100, 1) . '%';
            }
            $resource = new PollResource($poll);
        } else {
            $resource = new JsonResource(null);
        }

        $resource->additional([
            'selection' => $selection,
            'answer_stats' => $answerStats,
            'site_info' => site_info(),
        ]);
        return $this->success($resource);
    }

    public function vote(Request $request)
    {
        $request->validate([
            'poll_id' => 'required',
            'selection' => 'required|integer|min:0|max:255',
        ]);
        $pollId = $request->poll_id;
        $selection = $request->selection;
        $user = Auth::user();
        $poll = Poll::query()->findOrFail($pollId);
        $data = [
            'userid' => $user->id,
            'selection' => $selection,
        ];
        $answer = $poll->answers()->create($data);
        return $this->success($answer->toArray());
    }

}

<?php

namespace App\Http\Controllers;

use App\Models\HitAndRun;
use App\Repositories\SettingRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    private $repository;

    public function __construct(SettingRepository $repository)
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
        return $this->success($result);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function store(Request $request)
    {
        $data = $request->all();
        $prefix = Arr::first(array_keys($data));
        $request->validate($this->getRules($prefix));
        $result = $this->repository->store($data);
        return $this->success($result, 'Save setting success!');
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

    }

    private function getRules($prefix): array
    {
        $allRules = [
            'hr' => [
                'ban_user_when_counts_reach' => 'required|integer|min:1',
                'ignore_when_ratio_reach' => 'required|numeric',
                'inspect_time' => 'required|integer|min:1',
                'seed_time_minimum' => 'required|integer|lt:hr.inspect_time',
                'mode' => ['required', Rule::in(array_keys(HitAndRun::$modes))],
            ],
        ];

        $result = [];
        foreach ($allRules as $rulePrefix => $rules) {
            if ($rulePrefix != $prefix) {
                continue;
            }
            foreach ($rules as $key => $value) {
                $result["$prefix.$key"] = $value;
            }
        }
        return $result;
    }

}

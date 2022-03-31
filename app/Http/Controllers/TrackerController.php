<?php

namespace App\Http\Controllers;

use App\Repositories\TrackerRepository;
use Illuminate\Http\Request;

class TrackerController extends Controller
{
    private TrackerRepository $repository;

    public function __construct(TrackerRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function announce(Request $request): \Illuminate\Http\Response
    {
        return $this->repository->announce($request);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function scrape(Request $request): \Illuminate\Http\Response
    {
        return $this->repository->scrape($request);
    }
}

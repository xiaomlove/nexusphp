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
     * @deprecated
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function announce(Request $request): \Illuminate\Http\Response
    {
        throw new \RuntimeException("Deprecated! Reference to: https://nexusphp.org/2022/07/18/tracker-url-recommend-to-use-old-announce-php/");
        return $this->repository->announce($request);
    }

    /**
     * @deprecated
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function scrape(Request $request): \Illuminate\Http\Response
    {
        throw new \RuntimeException("Deprecated! Reference to: https://nexusphp.org/2022/07/18/tracker-url-recommend-to-use-old-announce-php/");
        return $this->repository->scrape($request);
    }
}

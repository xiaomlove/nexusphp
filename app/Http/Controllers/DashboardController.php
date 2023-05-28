<?php

namespace App\Http\Controllers;

use App\Http\Resources\TorrentResource;
use App\Http\Resources\UserResource;
use App\Repositories\DashboardRepository;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    private $repository;

    public function __construct(DashboardRepository $repository)
    {
        $this->repository = $repository;
    }

    public function systemInfo()
    {
        $result = $this->repository->getSystemInfo();
        return $this->success($result);
    }

    public function statData()
    {
        $result = $this->repository->getStatData();
        return $this->success($result);
    }

    public function latestUser()
    {
        $result = $this->repository->latestUser();
        $resource = UserResource::collection($result);
        $resource->additional([
            'page_title' => nexus_trans('dashboard.latest_user.page_title'),
        ]);
        return $this->success($resource);
    }

    public function latestTorrent()
    {
        $result = $this->repository->latestTorrent();
        $resource = TorrentResource::collection($result);
        $resource->additional([
            'page_title' => nexus_trans('dashboard.latest_torrent.page_title'),
        ]);
        return $this->success($resource);
    }
}

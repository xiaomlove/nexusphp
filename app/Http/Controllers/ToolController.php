<?php

namespace App\Http\Controllers;

use App\Repositories\ToolRepository;
use Illuminate\Http\Request;

class ToolController extends Controller
{
    private $repository;

    public function __construct(ToolRepository $repository)
    {
        $this->repository = $repository;
    }

    public function systemInfo()
    {
        $result = $this->repository->getSystemInfo();
        return $this->success($result);
    }
}

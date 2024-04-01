<?php

namespace App\Http\Controllers;

use App\Repositories\ToolRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Telegram\Bot\Api;
use Telegram\Bot\Commands\HelpCommand;

class ToolController extends Controller
{
    private $repository;

    public function __construct(ToolRepository $repository)
    {
        $this->repository = $repository;
    }

    public function notifications(): array
    {
        $user = Auth::user();
        $result = $this->repository->getNotificationCount($user);
        return $this->success($result);
    }


    public function test(Request $request)
    {

    }

}

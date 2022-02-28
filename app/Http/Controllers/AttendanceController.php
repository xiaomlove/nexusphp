<?php

namespace App\Http\Controllers;

use App\Repositories\AttendanceRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AttendanceController extends Controller
{
    private $repository;

    public function __construct(AttendanceRepository $repository)
    {
        $this->repository = $repository;
    }

    public function attend()
    {
        $uid = Auth::id();
        $attendance = $this->repository->attend($uid);
        return $this->success($attendance->toArray());
    }


}

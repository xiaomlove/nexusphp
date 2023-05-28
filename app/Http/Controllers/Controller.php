<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientPermissionException;
use App\Models\Setting;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function success($data, $msg = null)
    {
        if (is_null($msg)) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = $backtrace[1];
            $msg = $this->getReturnMsg($caller);
        }
        return success($msg, $data);
    }

    public function fail($data, $msg = null)
    {
        if (is_null($msg)) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $caller = $backtrace[1];
            $msg = $this->getReturnMsg($caller);
        }
        return fail($msg, $data);
    }

    protected function getReturnMsg(array $backtrace)
    {
        $title = $this->title ?? '';
        if (empty($title)) {
            $title = $backtrace['class'];
            $pos = strripos($title, '\\');
            $title = substr($title, $pos + 1);
            $title = str_replace('Controller', '', $title);
        }
        $action = $backtrace['function'];
        $map = [
            'index' => 'list',
            'show' => 'detail',
            'update' => 'update',
            'destroy' => 'delete',
        ];
        if (isset($map[$action])) {
            $action = $map[$action];
        }
        return Str::slug("$title.$action", '.');
    }

    protected function getPaginationParameters(): array
    {
        $request = request();
        $format = $request->__format;
        if ($format == 'data-table') {
            $perPage = $request->length;
            $page = intval($request->start / $perPage) + 1;
        } else {
            $perPage = $request->limit;
            $page = $request->page;
        }
        return [$perPage, ['*'], 'page', $page];
    }



}

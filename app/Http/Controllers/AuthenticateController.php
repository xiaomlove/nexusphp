<?php

namespace App\Http\Controllers;

use App\Http\Resources\ExamResource;
use App\Http\Resources\UserResource;
use App\Models\LoginLog;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\AuthenticateRepository;
use App\Repositories\UserRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

class AuthenticateController extends Controller
{
    private $repository;

    public function __construct(AuthenticateRepository $repository)
    {
        $this->repository = $repository;
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);
        $result = $this->repository->login($request->username, $request->password);
        $includes = explode(',', $request->get('include', ''));
        if (in_array('site_info', $includes)) {
            $basic = Setting::get('basic');
            $result['site_info'] = [
                'site_name' => $basic['SITENAME'],
            ];
        }
        return $this->success($result);
    }

    public function logout(Request $request)
    {
        $result = $this->repository->logout(Auth::id());
        return $this->success($result);
    }

    public function passkeyLogin($passkey)
    {
        $deadline = Setting::get('security.login_secret_deadline');
        if ($deadline && $deadline > now()->toDateTimeString()) {
            $user = User::query()->where('passkey', $passkey)->first(['id', 'passhash']);
            if ($user) {
                $ip = getip();
                /**
                 * Not IP related
                 * @since 1.8.0
                 */
//                $passhash = md5($user->passhash . $ip);
                $passhash = md5($user->passhash);
                do_log(sprintf('passhash: %s, ip: %s, md5: %s', $user->passhash, $ip, $passhash));
                logincookie($user->id, $passhash,false, get_setting('system.cookie_valid_days', 365) * 86400, true, true, true);
                $user->last_login = now();
                $user->save();
                $userRep = new UserRepository();
                $userRep->saveLoginLog($user->id, $ip, 'Passkey', false);
            }
        }
        return redirect('index.php');
    }

    public function nasToolsApprove(Request $request)
    {
        $request->validate([
            'data' => 'required|string'
        ]);
        try {
            $user = $this->repository->nasToolsApprove($request->data);
            $resource = new UserResource($user);
            return $this->success($resource);
        } catch (\Exception $exception) {
            $msg = $exception->getMessage();
            $params = $request->all();
            do_log(sprintf("nasToolsApprove fail: %s, params: %s", $msg, nexus_json_encode($params)));
            return $this->fail($params, $msg);
        }
    }

    public function iyuuApprove(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required|string',
                'id' => 'required|integer',
                'verity' => 'required|string',
                'provider' => ["required", "string", Rule::in("iyuu")],
            ]);
            $this->repository->iyuuApprove($request->token, $request->id, $request->verity);
            return response()->json(["success" => true]);
        } catch (\Exception $exception) {
            return response()->json(["success" => false, "msg" => $exception->getMessage()]);
        }
    }
}

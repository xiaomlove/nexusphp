<?php

namespace App\Providers;

use App\Auth\NexusWebGuard;
use App\Auth\NexusWebUserProvider;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Auth::viaRequest('nexus-cookie', function (Request $request) {
            return $this->getUserByCookie($request->cookie());
        });

        Auth::extend('nexus-web', function ($app, $name, array $config) {
            // 返回 Illuminate\Contracts\Auth\Guard 的实例 ...
            return new NexusWebGuard($app['request'], new NexusWebUserProvider());
        });

//        Bouncer::useAbilityModel(Permission::class);
//        Bouncer::useRoleModel(Role::class);
//        Bouncer::useUserModel(User::class);
    }

    private function getUserByCookie($cookie)
    {
        if (empty($cookie["c_secure_pass"]) || empty($cookie["c_secure_uid"]) || empty($cookie["c_secure_login"])) {
            return null;
        }
        $b_id = base64($cookie["c_secure_uid"],false);
        $id = intval($b_id ?? 0);
        if (!$id || !is_valid_id($id) || strlen($cookie["c_secure_pass"]) != 32) {
            return null;
        }
        $user = User::query()->find($id);
        if (!$user) {
            return null;
        }
        if ($cookie["c_secure_login"] == base64("yeah")) {
            if ($cookie["c_secure_pass"] != md5($user->passhash . $_SERVER["REMOTE_ADDR"])) {
                return null;
            }
        } else {
            if ($cookie["c_secure_pass"] !== md5($user->passhash)) {
                return null;
            }
        }
        return $user;
    }
}

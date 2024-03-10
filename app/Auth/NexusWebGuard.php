<?php
namespace App\Auth;

use Carbon\Carbon;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class NexusWebGuard implements StatefulGuard
{
    use GuardHelpers;

    /**
     * The request instance.
     *
     * @var \Illuminate\Http\Request
     */
    protected $request;

    /**
     * Create a new authentication guard.
     *
     * @param  callable  $callback
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Contracts\Auth\UserProvider|null  $provider
     * @return void
     */
    public function __construct(Request $request, UserProvider $provider = null)
    {
        $this->request = $request;
        $this->provider = $provider;
    }

    /**
     * Get the currently authenticated user.
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function user()
    {
        if (! is_null($this->user)) {
            return $this->user;
        }
        $credentials = $this->request->cookie();
        if ($this->validate($credentials)) {
            $user = $this->user;
            if ($this->provider->validateCredentials($user, $credentials)) {
                return $user;
            }
        }
    }


    /**
     * Validate a user's credentials.
     *
     * @param  array  $credentials
     * @return bool
     */
    public function validate(array $credentials = [])
    {
        $required = ['c_secure_pass', 'c_secure_uid', 'c_secure_login'];
        foreach ($required as $value) {
            if (empty($credentials[$value])) {
                return false;
            }
        }
        $b_id = base64($credentials["c_secure_uid"],false);
        $id = intval($b_id ?? 0);
        if (!$id || !is_valid_id($id) || strlen($credentials["c_secure_pass"]) != 32) {
            return false;
        }
        $user = $this->provider->retrieveById($id);
        if (!$user) {
            return false;
        }
        try {
            $user->checkIsNormal();
            $this->user = $user;
            return true;
        } catch (\Throwable $e) {
            do_log($e->getMessage());
            return false;
        }
    }

    public function logout()
    {
        logoutcookie();
        return nexus_redirect('login.php');
    }


    public function attempt(array $credentials = [], $remember = false)
    {
        // TODO: Implement attempt() method.
    }

    public function once(array $credentials = [])
    {
        // TODO: Implement once() method.
    }

    public function login(Authenticatable $user, $remember = false)
    {
        // TODO: Implement login() method.
    }

    public function loginUsingId($id, $remember = false)
    {
        // TODO: Implement loginUsingId() method.
    }

    public function onceUsingId($id)
    {
        // TODO: Implement onceUsingId() method.
    }

    public function viaRemember()
    {
        // TODO: Implement viaRemember() method.
    }
}

<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;

class NexusWebGuard implements StatefulGuard
{
    use GuardHelpers;

    /**
     * The request instance.
     *
     * @var Request
     */
    protected $request;

    /**
     * Create a new authentication guard.
     *
     * @param  callable  $callback
     * @return void
     */
    public function __construct(Request $request, ?UserProvider $provider = null)
    {
        $this->request = $request;
        $this->provider = $provider;
    }

    /**
     * Get the currently authenticated user.
     *
     * @return Authenticatable|null
     */
    public function user()
    {
        if (! is_null($this->user)) {
            return $this->user;
        }
        $credentials = $this->request->cookie();
        if ($this->validate($credentials)) {
            /**
             * @var User $user
             */
            $user = $this->provider->retrieveByCredentials($credentials);
            if (empty($user)) {
                return null;
            }
            if ($this->provider->validateCredentials($user, $credentials)) {
                $user->checkIsNormal();

                return $this->user = $user;
            }
        }

        return null;
    }

    /**
     * Validate a user's credentials.
     *
     * @return bool
     */
    public function validate(array $credentials = [])
    {
        $required = ['c_secure_pass'];
        foreach ($required as $value) {
            if (empty($credentials[$value])) {
                return false;
            }
        }

        return true;
    }

    public function logout()
    {
        logoutcookie();

        return nexus_redirect('login.php');
    }

    public function attempt(array $credentials = [], $remember = false)
    {
        throw new \BadMethodCallException(
            __METHOD__.' is not implemented for '.static::class
        );
    }

    public function once(array $credentials = [])
    {
        throw new \BadMethodCallException(
            __METHOD__.' is not implemented for '.static::class
        );
    }

    public function login(Authenticatable $user, $remember = false)
    {
        throw new \BadMethodCallException(
            __METHOD__.' is not implemented for '.static::class
        );
    }

    public function loginUsingId($id, $remember = false)
    {
        throw new \BadMethodCallException(
            __METHOD__.' is not implemented for '.static::class
        );
    }

    public function onceUsingId($id)
    {
        throw new \BadMethodCallException(
            __METHOD__.' is not implemented for '.static::class
        );
    }

    public function viaRemember()
    {
        throw new \BadMethodCallException(
            __METHOD__.' is not implemented for '.static::class
        );
    }
}

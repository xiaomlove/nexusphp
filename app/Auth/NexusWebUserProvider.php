<?php
namespace App\Auth;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

class NexusWebUserProvider implements UserProvider
{
    /**
     * @var \Illuminate\Database\Eloquent\Builder
     */
    protected $query;

    public function __construct()
    {
        $this->query = User::query();
    }
    /**
     * Retrieve a user by their unique identifier.
     *
     * @param  mixed  $identifier
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveById($identifier)
    {
        return $this->query->find($identifier);
    }


    /**
     * Retrieve a user by their unique identifier and "remember me" token.
     *
     * @param  mixed  $identifier
     * @param  string  $token
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByToken($identifier, $token)
    {

    }

    /**
     * Update the "remember me" token for the given user in storage.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  string  $token
     * @return void
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {

    }


    /**
     * Retrieve a user by the given credentials.
     *
     * @param  array  $credentials
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials)
    {
        if (!empty($credentials['c_secure_uid'])) {
            $b_id = base64($credentials["c_secure_uid"],false);
            return $this->query->find($b_id);
        }
    }

    /**
     * Validate a user against the given credentials.
     *
     * @param  \Illuminate\Contracts\Auth\Authenticatable  $user
     * @param  array  $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        if ($credentials["c_secure_login"] == base64("yeah")) {
            if ($credentials["c_secure_pass"] != md5($user->passhash . $_SERVER["REMOTE_ADDR"])) {
                return false;
            }
        } else {
            if ($credentials["c_secure_pass"] !== md5($user->passhash)) {
                return false;
            }
        }
        return true;
    }

}

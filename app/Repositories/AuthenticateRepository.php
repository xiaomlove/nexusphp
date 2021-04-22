<?php
namespace App\Repositories;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuthenticateRepository extends BaseRepository
{
    public function login($username, $password)
    {
        $user = User::query()
            ->where('username', $username)
            ->firstOrFail(['id', 'secret', 'passhash']);
        if (md5($user->secret . $password . $user->secret) != $user->passhash) {
            throw new \InvalidArgumentException('username or password invalid');
        }
        $token = DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $tokenResult = $user->createToken(__CLASS__ . __FUNCTION__ . __LINE__);
            return $tokenResult->plainTextToken;
        });
        $result = $user->toArray();
        $result['token'] = $token;
        return $result;
    }

    public function logout($id)
    {
        $user = User::query()->findOrFail($id, ['id']);
        $result = $user->tokens()->delete();
        return $result;
    }
}

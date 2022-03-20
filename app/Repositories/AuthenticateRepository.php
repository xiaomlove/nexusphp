<?php
namespace App\Repositories;

use App\Http\Resources\UserResource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\UnauthorizedException;

class AuthenticateRepository extends BaseRepository
{
    public function login($username, $password)
    {
        $user = User::query()
            ->where('username', $username)
            ->first(array_merge(User::$commonFields, ['class', 'secret', 'passhash']));
        if (!$user || md5($user->secret . $password . $user->secret) != $user->passhash) {
            throw new \InvalidArgumentException('Username or password invalid.');
        }
        if (nexus()->isPlatformAdmin() && !$user->canAccessAdmin()) {
            throw new UnauthorizedException('Unauthorized!');
        }
        $user->checkIsNormal();
        $tokenName = __METHOD__ . __LINE__;
        $token = DB::transaction(function () use ($user, $tokenName) {
            $user->update(['last_login' => Carbon::now()]);
            $tokenResult = $user->createToken($tokenName);
            return $tokenResult->plainTextToken;
        });
        $result = (new UserResource($user))->response()->getData(true)['data'];
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

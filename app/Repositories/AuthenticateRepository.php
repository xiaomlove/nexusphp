<?php
namespace App\Repositories;

use App\Http\Resources\UserResource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Encryption\Encrypter;
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
//        if (nexus()->isPlatformAdmin() && !$user->canAccessAdmin()) {
//            throw new UnauthorizedException('Unauthorized!');
//        }
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

    public function nasToolsApprove(string $json)
    {
        $key = env('NAS_TOOLS_KEY');
        $encrypter = new Encrypter($key);
        $decrypted = $encrypter->decryptString($json);
        $data = json_decode($decrypted, true);
        if (!is_array($data) || !isset($data['uid'], $data['passkey'])) {
            throw new \InvalidArgumentException("Invalid data format.");
        }
        $user = User::query()
            ->where('id', $data['uid'])
            ->where('passkey', $data['passkey'])
            ->first()
        ;
        if (!$user) {
            throw new \InvalidArgumentException("Invalid uid or passkey.");
        }
        $user->checkIsNormal();
        return $user;
    }

    public function iyuuApprove($token, $id, $verity)
    {
        $secret = env('IYUU_SECRET');
        $user = User::query()->findOrFail($id, User::$commonFields);
        $user->checkIsNormal();
        $encryptedResult = md5($token . $id . sha1($user->passkey) . $secret);
        if ($encryptedResult != $verity) {
            throw new \InvalidArgumentException("Invalid uid or passkey");
        }
        return true;
    }
}

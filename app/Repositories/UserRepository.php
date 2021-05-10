<?php
namespace App\Repositories;

use App\Http\Resources\ExamUserResource;
use App\Http\Resources\UserResource;
use App\Models\ExamUser;
use App\Models\Setting;
use App\Models\User;

class UserRepository extends BaseRepository
{
    public function getList(array $params)
    {
        $query = User::query();
        list($sortField, $sortType) = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);
        return $query->paginate();
    }

    public function getBase($id)
    {
        $user = User::query()->findOrFail($id, ['id', 'username', 'email', 'avatar']);
        return $user;
    }

    public function getDetail($id)
    {
        $user = User::query()->with(['invitee_code'])->findOrFail($id, User::$commonFields);
        $userResource = new UserResource($user);
        $baseInfo = $userResource->response()->getData(true)['data'];

        $examRep = new ExamRepository();
        $examProgress = $examRep->getUserExamProgress($id, ExamUser::STATUS_NORMAL, ['exam']);
        if ($examProgress) {
            $examResource = new ExamUserResource($examProgress);
            $examInfo = $examResource->response()->getData(true)['data'];
        } else {
            $examInfo = null;
        }

        return [
            'base_info' => $baseInfo,
            'exam_info' => $examInfo,
        ];
    }

    public function store(array $params)
    {
        $password = $params['password'];
        if ($password != $params['password_confirmation']) {
            throw new \InvalidArgumentException("password confirmation != password");
        }

        $setting = Setting::get('main');
        $secret = mksecret();
        $passhash = md5($secret . $password . $secret);
        $data = [
            'username' => $params['username'],
            'email' => $params['email'],
            'secret' => $secret,
            'editsecret' => '',
            'passhash' => $passhash,
            'stylesheet' => $setting['defstylesheet'],
            'added' => now()->toDateTimeString(),
            'status' => User::STATUS_CONFIRMED,
        ];
        $user = User::query()->create($data);
        return $user;
    }

    public function resetPassword($username, $password, $passwordConfirmation)
    {
        if ($password != $passwordConfirmation) {
            throw new \InvalidArgumentException("password confirmation != password");
        }
        $user = User::query()->where('username', $username)->firstOrFail(['id', 'username']);
        $secret = mksecret();
        $passhash = md5($secret . $password . $secret);
        $update = [
            'secret' => $secret,
            'passhash' => $passhash,
        ];
        $user->update($update);
        return $user;
    }

    public function listClass()
    {
        $out = [];
        foreach(User::$classes as $key => $value) {
            $out[(string)$key] = $value['text'];
        }
        return $out;
    }
}

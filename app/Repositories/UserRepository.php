<?php
namespace App\Repositories;

use App\Exceptions\NexusException;
use App\Http\Resources\ExamUserResource;
use App\Http\Resources\UserResource;
use App\Models\ExamUser;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserBanLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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
        $with = [
            'inviter' => function ($query) {return $query->select(User::$commonFields);},
            'valid_medals'
        ];
        $user = User::query()->with($with)->findOrFail($id, User::$commonFields);
        $userResource = new UserResource($user);
        $baseInfo = $userResource->response()->getData(true)['data'];

        $examRep = new ExamRepository();
        $examProgress = $examRep->getUserExamProgress($id, null);
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

    public function resetPassword($id, $password, $passwordConfirmation)
    {
        if ($password != $passwordConfirmation) {
            throw new \InvalidArgumentException("password confirmation != password");
        }
        $user = User::query()->findOrFail($id, ['id', 'username']);
        $secret = mksecret();
        $passhash = md5($secret . $password . $secret);
        $update = [
            'secret' => $secret,
            'passhash' => $passhash,
        ];
        $user->update($update);
        return true;
    }

    public function listClass()
    {
        $out = [];
        foreach(User::$classes as $key => $value) {
            $out[(string)$key] = $value['text'];
        }
        return $out;
    }

    public function disableUser(User $operator, $uid, $reason)
    {
        $targetUser = User::query()->findOrFail($uid, ['id', 'enabled', 'username']);
        if ($targetUser->enabled == User::ENABLED_NO) {
            throw new NexusException('Already disabled!');
        }
        $banLog = [
            'uid' => $uid,
            'username' => $targetUser->username,
            'reason' => $reason,
            'operator' => $operator->id,
        ];
        $modCommentText = sprintf("Disable by %s, reason: %s.", $operator->username, $reason);
        DB::transaction(function () use ($targetUser, $banLog, $modCommentText) {
            $targetUser->updateWithModComment(['enabled' => User::ENABLED_NO], $modCommentText);
            UserBanLog::query()->insert($banLog);
        });
        do_log("user: $uid, $modCommentText");
        return true;
    }

    public function enableUser(User $operator, $uid)
    {
        $targetUser = User::query()->findOrFail($uid, ['id', 'enabled', 'username', 'class']);
        if ($targetUser->enabled == User::ENABLED_YES) {
            throw new NexusException('Already enabled!');
        }
        $update = [
            'enabled' => User::ENABLED_YES
        ];
        if ($targetUser->class == User::CLASS_PEASANT) {
            // warn users until 30 days
            $until = now()->addDays(30)->toDateTimeString();
            $update['leechwarn'] = 'yes';
            $update['leechwarnuntil'] = $until;
        } else {
            $update['leechwarn'] = 'no';
            $update['leechwarnuntil'] = null;
        }
        $modCommentText = sprintf("Enable by %s.", $operator->username);
        $targetUser->updateWithModComment($update, $modCommentText);
        do_log("user: $uid, $modCommentText, update: " . nexus_json_encode($update));
        return true;
    }

    public function getInviteInfo($id)
    {
        $user = User::query()->findOrFail($id, ['id']);
        return $user->invitee_code()->with('inviter_user')->first();
    }

    public function getModComment($id)
    {
        $user = User::query()->findOrFail($id, ['modcomment']);
        return $user->modcomment;
    }



}

<?php
namespace App\Repositories;

use App\Exceptions\InsufficientPermissionException;
use App\Exceptions\NexusException;
use App\Http\Resources\ExamUserResource;
use App\Http\Resources\UserResource;
use App\Models\ExamUser;
use App\Models\Message;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserBanLog;
use App\Models\UserMeta;
use App\Models\UsernameChangeLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;

class UserRepository extends BaseRepository
{
    public function getList(array $params)
    {
        $query = User::query();
        if (!empty($params['id'])) {
            $query->where('id', $params['id']);
        }
        if (!empty($params['username'])) {
            $query->where('username', 'like',"%{$params['username']}%");
        }
        if (!empty($params['email'])) {
            $query->where('email', 'like',"%{$params['email']}%");
        }
        if (isset($params['class']) && $params['class'] !== '') {
            $query->where('class', $params['class']);
        }
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
        $user = User::query()->with($with)->findOrFail($id);
        $userResource = new UserResource($user);
        $baseInfo = $userResource->response()->getData(true)['data'];

        $examRep = new ExamRepository();
        $examProgress = $examRep->getUserExamProgress($id, ExamUser::STATUS_NORMAL);
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

    /**
     * create user
     *
     * @param array $params must: username, email, password, password_confirmation. optional: id, class
     * @return User
     */
    public function store(array $params)
    {
        $password = $params['password'];
        if ($password != $params['password_confirmation']) {
            throw new \InvalidArgumentException("password confirmation != password");
        }
        $username = $params['username'];
        if (!validusername($username)) {
            throw new \InvalidArgumentException("Invalid username: $username");
        }
        $email = htmlspecialchars(trim($params['email']));
        $email = safe_email($email);
        if (!check_email($email)) {
            throw new \InvalidArgumentException("Invalid email: $email");
        }
        if (User::query()->where('email', $email)->exists()) {
            throw new \InvalidArgumentException("The email address: $email is already in use");
        }
        if (User::query()->where('username', $username)->exists()) {
            throw new \InvalidArgumentException("The username: $username is already in use");
        }
        if (mb_strlen($password) < 6 || mb_strlen($password) > 40) {
            throw new \InvalidArgumentException("Invalid password: $password, it should be more than 6 character and less than 40 character");
        }
        $class = !empty($params['class']) ? intval($params['class']) : User::CLASS_USER;
        if (!isset(User::$classes[$class])) {
            throw new \InvalidArgumentException("Invalid user class: $class");
        }
        $setting = Setting::get('main');
        $secret = mksecret();
        $passhash = md5($secret . $password . $secret);
        $data = [
            'username' => $username,
            'email' => $email,
            'secret' => $secret,
            'editsecret' => '',
            'passhash' => $passhash,
            'stylesheet' => $setting['defstylesheet'],
            'added' => now()->toDateTimeString(),
            'status' => User::STATUS_CONFIRMED,
            'class' => $class
        ];
        $user = new User($data);
        if (!empty($params['id'])) {
            if (User::query()->where('id', $params['id'])->exists()) {
                throw new \InvalidArgumentException("uid: {$params['id']} already exists.");
            }
            do_log("[CREATE_USER], specific id: " . $params['id']);
            $user->id = $params['id'];
        }
        $user->save();

        return $user;
    }

    public function resetPassword($id, $password, $passwordConfirmation)
    {
        if ($password != $passwordConfirmation) {
            throw new \InvalidArgumentException("password confirmation != password");
        }
        $user = User::query()->findOrFail($id, ['id', 'username', 'class']);
        $operator = Auth::user();
        if ($operator) {
            $this->checkPermission($operator, $user);
        }
        $secret = mksecret();
        $passhash = md5($secret . $password . $secret);
        $update = [
            'secret' => $secret,
            'passhash' => $passhash,
        ];
        $user->update($update);
        return true;
    }

    /**
     * @deprecated  use User::listClass() instead !
     *
     * @return array
     */
    public function listClass()
    {
        return User::listClass();
    }

    public function disableUser(User $operator, $uid, $reason)
    {
        $targetUser = User::query()->findOrFail($uid, ['id', 'enabled', 'username', 'class']);
        if ($targetUser->enabled == User::ENABLED_NO) {
            throw new NexusException('Already disabled !');
        }
        $this->checkPermission($operator, $targetUser);
        $banLog = [
            'uid' => $uid,
            'username' => $targetUser->username,
            'reason' => $reason,
            'operator' => $operator->id,
        ];
        $modCommentText = sprintf("%s - Disable by %s, reason: %s.", now()->format('Y-m-d'), $operator->username, $reason);
        DB::transaction(function () use ($targetUser, $banLog, $modCommentText) {
            $targetUser->updateWithModComment(['enabled' => User::ENABLED_NO], $modCommentText);
            UserBanLog::query()->insert($banLog);
        });
        do_log("user: $uid, $modCommentText");
        $this->clearCache($targetUser);
        return true;
    }

    public function enableUser(User $operator, $uid, $reason = '')
    {
        $targetUser = User::query()->findOrFail($uid, ['id', 'enabled', 'username', 'class']);
        if ($targetUser->enabled == User::ENABLED_YES) {
            throw new NexusException('Already enabled !');
        }
        $this->checkPermission($operator, $targetUser);
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
        $modCommentText = sprintf("%s - Enable by %s, reason: %s", now()->format('Y-m-d'), $operator->username, $reason);
        $targetUser->updateWithModComment($update, $modCommentText);
        do_log("user: $uid, $modCommentText, update: " . nexus_json_encode($update));
        $this->clearCache($targetUser);
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

    public function incrementDecrement(User $operator, $uid, $action, $field, $value, $reason = ''): bool
    {
        $fieldMap = [
            'uploaded' => 'uploaded',
            'downloaded' => 'downloaded',
            'seedbonus' => 'seedbonus',
            'invites' => 'invites',
            'attendance_card' => 'attendance_card',
        ];
        if (!isset($fieldMap[$field])) {
            throw new \InvalidArgumentException("Invalid field: $field, only support: " . implode(', ', array_keys($fieldMap)));
        }
        $sourceField = $fieldMap[$field];
        $targetUser = User::query()->findOrFail($uid, User::$commonFields);
        $this->checkPermission($operator, $targetUser);
        $old = $targetUser->{$sourceField};
        $valueAtomic = $value;
        $formatSize = false;
        if (in_array($field, ['uploaded', 'downloaded'])) {
            //Frontend unit: GB
            $valueAtomic = $value * 1024 * 1024 * 1024;
            $formatSize = true;
        }
        if ($action == 'Increment') {
            $new = $old + abs($valueAtomic);
        } elseif ($action == 'Decrement') {
            $new = $old - abs($valueAtomic);
        } else {
            throw new \InvalidArgumentException("Invalid action: $action.");
        }
        if ($new < 0) {
            throw new NexusException("New value($new) lte 0");
        }
        //for administrator, use english
        $modCommentText = nexus_trans('message.field_value_change_message_body', [
            'field' => nexus_trans("user.labels.$sourceField", [], 'en'),
            'operator' => $operator->username,
            'old' => $formatSize ? mksize($old) : $old,
            'new' => $formatSize ?  mksize($new) : $new,
            'reason' => $reason,
        ], 'en');
        $modCommentText = date('Y-m-d') . " - $modCommentText";
        do_log("user: $uid, $modCommentText", 'alert');
        $update = [
            $sourceField => $new,
            'modcomment' => NexusDB::raw("if(modcomment = '', '$modCommentText', concat_ws('\n', '$modCommentText', modcomment))"),
        ];

        $locale = $targetUser->locale;
        $fieldLabel = nexus_trans("user.labels.$sourceField", [], $locale);
        $msg = nexus_trans('message.field_value_change_message_body', [
            'field' => $fieldLabel,
            'operator' => $operator->username,
            'old' => $formatSize ? mksize($old) : $old,
            'new' => $formatSize ?  mksize($new) : $new,
            'reason' => $reason,
        ], $locale);
        $message = [
            'sender' => 0,
            'receiver' => $targetUser->id,
            'subject' => nexus_trans("message.field_value_change_message_subject", ['field' =>  $fieldLabel], $locale),
            'msg' => $msg,
            'added' => Carbon::now(),
        ];
        NexusDB::transaction(function () use ($uid, $sourceField, $old, $new, $update, $message) {
            $affectedRows = User::query()
                ->where('id', $uid)
                ->where($sourceField, $old)
                ->update($update)
            ;
            if ($affectedRows != 1) {
                throw new \RuntimeException("Change fail, affected rows != 1($affectedRows)");
            }
            Message::query()->insert($message);
        });
        $this->clearCache($targetUser);
        return true;
    }

    public function removeLeechWarn($operator, $uid): bool
    {
        $operator = $this->getUser($operator);
        $user = User::query()->findOrFail($uid, User::$commonFields);
        $this->checkPermission($operator, $user);
        $this->clearCache($user);
        $user->leechwarn = 'no';
        $user->leechwarnuntil = null;
        return $user->save();
    }

    public function removeTwoStepAuthentication($operator, $uid): bool
    {
        if (!$operator->canAccessAdmin()) {
            throw new \RuntimeException("No permission.");
        }
        $user = User::query()->findOrFail($uid, User::$commonFields);
        $this->checkPermission($operator, $user);
        $this->clearCache($user);
        $user->two_step_secret = '';
        return $user->save();
    }


    public function updateDownloadPrivileges($operator, $user, $status)
    {
        if (!in_array($status, ['yes', 'no'])) {
            throw new \InvalidArgumentException("Invalid status: $status");
        }
        $targetUser = $this->getUser($user);
        $operator = $this->getUser($operator);
        $operatorUsername = 'System';
        if ($operator) {
            $operatorUsername = $operator->username;
            $this->checkPermission($operator, $targetUser);
        }
        $message = [
            'added' => now(),
            'receiver' => $targetUser->id,
        ];
        if ($status == 'no') {
            $update = ['downloadpos' => 'no'];
            $modComment = date('Y-m-d') . " - Download disable by " . $operatorUsername;
            $message['subject'] = nexus_trans('message.download_disable.subject', [], $targetUser->locale);
            $message['msg'] = nexus_trans('message.download_disable.body', ['operator' => $operatorUsername], $targetUser->locale);
        } else {
            $update = ['downloadpos' => 'yes'];
            $modComment = date('Y-m-d') . " - Download enable by " . $operatorUsername;
            $message['subject'] = nexus_trans('message.download_enable.subject', [], $targetUser->locale);
            $message['msg'] = nexus_trans('message.download_enable.body', ['operator' => $operatorUsername], $targetUser->locale);
        }
        return NexusDB::transaction(function () use ($targetUser, $update, $modComment, $message) {
            Message::add($message);
            $this->clearCache($targetUser);
            return $targetUser->updateWithModComment($update, $modComment);
        });
    }


    private function checkPermission($operator, User $user, $minAuthClass = 'authority.prfmanage')
    {
        $operator = $this->getUser($operator);
        if ($operator->id == $user->id) {
            return;
        }
        $classRequire = Setting::get($minAuthClass);
        if ($operator->class < $classRequire || $operator->class <= $user->class) {
            throw new InsufficientPermissionException();
        }
    }

    private function clearCache(User $user)
    {
        clear_user_cache($user->id, $user->passkey);
    }

    public function listMetas($uid, $metaKeys = [], $valid = true)
    {
        $query = UserMeta::query()->where('uid', $uid);
        if (!empty($metaKeys)) {
            $query->whereIn('meta_key', Arr::wrap($metaKeys));
        }
        if ($valid) {
            $query->where('status', 0)->where(function (Builder $query) {
                $query->whereNull('deadline')->orWhere('deadline', '>=', now());
            });
        }
        return $query->get()->groupBy('meta_key');
    }

    public function consumeBenefit($uid, array $params): bool
    {
        $metaKey = $params['meta_key'];
        $records = $this->listMetas($uid, $metaKey);
        if (!$records->has($metaKey)) {
            throw new \RuntimeException("User do not has this metaKey: $metaKey");
        }
        /** @var UserMeta $meta */
        $meta = $records->get($metaKey)->first();
        $user = User::query()->findOrFail($uid, User::$commonFields);
        if ($metaKey == UserMeta::META_KEY_CHANGE_USERNAME) {
            $changeLog = $user->usernameChangeLogs()->orderBy('id', 'desc')->first();
            if ($changeLog) {
                $miniDays = Setting::get('system.change_username_min_interval_in_days', 365);
                if ($changeLog->created_at->diffInDays() <= $miniDays) {
                    $msg = nexus_trans('user.change_username_lte_min_interval', ['last_change_time' => $changeLog->created_at, 'interval' => $miniDays]);
                    throw new \RuntimeException($msg);
                }
            }
            NexusDB::transaction(function () use ($user, $meta, $params) {
                $this->changeUsername(
                    $user, UsernameChangeLog::CHANGE_TYPE_USER, $user, $params['username'],
                    Setting::get('system.change_username_card_allow_characters_outside_the_alphabets') == 'yes'
                );
                $meta->delete();
                clear_user_cache($user->id, $user->passkey);
            });
            return true;
        }

        throw new \InvalidArgumentException("Invalid meta_key: $metaKey");
    }

    private function changeUsername($operator, $changeType, $targetUser, $newUsername, $allowOutsideAlphabets = false): bool
    {
        $operator = $this->getUser($operator);
        $targetUser = $this->getUser($targetUser);
        $this->checkPermission($operator, $targetUser);
        if ($targetUser->username == $newUsername) {
            throw new \RuntimeException("New username can not be the same with current username !");
        }
        $strWidth = mb_strwidth($newUsername);
        if ($strWidth < 4 || $strWidth > 20) {
            throw new \InvalidArgumentException("Invalid username, maybe too long or too short");
        }
        if (!$allowOutsideAlphabets && !validusername($newUsername)) {
            throw new \InvalidArgumentException("Invalid username, only support alphabets");
        }
        if (User::query()->where('username', $newUsername)->where('id', '!=', $targetUser->id)->exists()) {
            throw new \RuntimeException("Username: $newUsername already exists !");
        }
        $changeLog = [
            'uid' => $targetUser->id,
            'operator' => $operator->username,
            'change_type' => $changeType,
            'username_old' => $targetUser->username,
            'username_new' => $newUsername
        ];
        NexusDB::transaction(function () use ($operator, $changeType,$targetUser, $changeLog) {
            $targetUser->usernameChangeLogs()->create($changeLog);
            $targetUser->username = $changeLog['username_new'];
            $targetUser->save();
        });
        return true;
    }

    public function addMeta($user, array $metaData, array $keyExistsUpdates = [])
    {
        $user = $this->getUser($user);
        $metaKey = $metaData['meta_key'];
        $allowMultiple = UserMeta::$metaKeys[$metaKey]['multiple'];
        if ($allowMultiple) {
            //Allow multiple, just insert
            $result = $user->metas()->create($metaData);
        } else {
            $metaExists = $user->metas()->where('meta_key', $metaKey)->first();
            if (!$metaExists) {
                $result = $user->metas()->create($metaData);
            } else {
                if (empty($keyExistsUpdates)) {
                    $keyExistsUpdates = ['updated_at' => now()];
                }
                $result = $metaExists->update($keyExistsUpdates);
            }
        }
        if ($result) {
            clear_user_cache($user->id, $user->passkey);
        }
        return $result;
    }

}

<?php

namespace App\Repositories;

use App\Models\Setting;
use App\Models\Torrent;
use App\Models\User;
use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BaseRepository
{
    protected function getSortFieldAndType(array $params): array
    {
        $field = !empty($params['sort_field']) ? $params['sort_field'] : 'id';
        $type = 'desc';
        if (!empty($params['sort_type']) && Str::startsWith($params['sort_type'], 'asc')) {
            $type = 'asc';
        }
        return [$field, $type];
    }

    protected function getPerPageFromRequest(Request $request)
    {
        $perPage =  $request->get('per_page');
        if ($perPage && $perPage > 100) {
            do_log("per_page: $perPage > 100", "warning");
            $perPage = 100;
        }
        return $perPage;
    }

    protected function handleAnonymous($username, $user, User $authenticator, ?Torrent $torrent = null)
    {
        if (!$user) {
            return "";
        }
        if($user->privacy == "strong" || ($torrent && $torrent->anonymous == 'yes' && $user->id == $torrent->owner)) {
            //用户强私密，或者种子作者匿名而当前项作者刚好为种子作者
            $anonymousText = nexus_trans('label.anonymous');
            if(user_can('viewanonymous', false, $authenticator->id) || $user->id == $authenticator->id) {
                //但当前用户权限可以查看匿名者，或当前用户查看自己的数据，显示个匿名，后边加真实用户名
                return sprintf('%s(%s)', $anonymousText, $username);
            } else {
                return $anonymousText;
            }
        } else {
            return $username;
        }
    }

    /**
     * @param $user
     * @param null $fields
     * @return User|null
     */
    protected function getUser($user, $fields = null): User|null
    {
        if ($user === null) {
            return null;
        }
        if ($user instanceof User) {
            return $user;
        }
        if ($fields === null) {
            $fields = User::$commonFields;
        }
        return User::query()->findOrFail(intval($user), $fields);
    }

    protected function executeCommand($command, $format = 'string'): string|array
    {
        return executeCommand($command, $format);
    }

}

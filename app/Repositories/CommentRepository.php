<?php

namespace App\Repositories;

use App\Models\Comment;
use App\Models\Message;
use App\Models\NexusModel;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;

class CommentRepository extends BaseRepository
{
    public function getList(Request $request, Authenticatable $user)
    {
        $query = Comment::query()->with(['create_user', 'update_user']);
        if (! empty($request->torrent_id)) {
            $query->where('torrent', $request->torrent_id);
        }
        if (! empty($request->offer_id)) {
            $query->where('offer', $request->offer_id);
        }
        if (! empty($request->request_id)) {
            $query->where('request', $request->request_id);
        }
        $query->orderBy('id', 'asc');

        return $query->paginate($this->getPerPageFromRequest($request));
    }

    public function store(array $params, User $user)
    {
        $type = $params['type'];
        $modelName = Comment::TYPE_MAPS[$params['type']]['model'];
        /**
         * @var NexusModel $model
         */
        $model = new $modelName;
        // @phpstan-ignore-next-line larastan.relationExistence
        $target = $model->newQuery()->with('user')->find($params[$type]);

        return DB::transaction(function () use ($params, $user, $target) {
            $params['added'] = Carbon::now();
            $comment = $user->comments()->create($params);
            $commentCount = Comment::query()->type($params['type'], $params[$params['type']])->count();
            $target->comments = $commentCount;
            $target->save();

            $userUpdate = [
                'seedbonus' => NexusDB::raw('seedbonus + '.Setting::get('bonus.addcomment')),
                'last_comment' => Carbon::now(),
            ];
            $user->update($userUpdate);

            // message
            if ($target->user->commentpm == 'yes' && $user->id != $target->user->id) {
                $messageInfo = $this->getNoticeMessage($target, $params['type']);
                $insert = [
                    'sender' => 0,
                    'receiver' => $target->user->id,
                    'subject' => $messageInfo['subject'],
                    'msg' => $messageInfo['body'],
                    'added' => $params['added'],
                ];
                Message::query()->insert($insert);
                NexusDB::cache_del('user_'.$target->user->id.'_unread_message_count');
                NexusDB::cache_del('user_'.$target->user->id.'_inbox_count');
            }

            return $comment;
        });
    }

    public function update(array $params, $id)
    {
        $model = Comment::query()->findOrFail($id);
        $model->update($params);

        return $model;
    }

    public function getDetail($id)
    {
        $model = Comment::query()->findOrFail($id);

        return $model;
    }

    public function delete($id)
    {
        $model = Comment::query()->findOrFail($id);
        $result = $model->delete();

        return $result;
    }

    private function getNoticeMessage($target, $type): array
    {
        $allTrans = require_once base_path('lang/_target/lang_comment.php');
        $lang = $target->user->language->site_lang_folder ?? 'en';
        $trans = $allTrans[$lang];
        $subject = $trans['msg_new_comment'];
        $targetScript = Comment::TYPE_MAPS[$type]['target_script'];
        $targetNameField = Comment::TYPE_MAPS[$type]['target_name_field'];
        $body = sprintf(
            '%s [url=%s]%s[/url]',
            $trans['msg_torrent_receive_comment'],
            sprintf($targetScript, $target->id),
            $target->{$targetNameField}
        );

        return compact('subject', 'body');
    }
}

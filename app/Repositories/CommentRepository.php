<?php
namespace App\Repositories;

use App\Models\Comment;
use App\Models\Message;
use App\Models\NexusModel;
use App\Models\Setting;
use App\Models\Torrent;
use App\Models\User;
use Carbon\Carbon;
use Hamcrest\Core\Set;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;

class CommentRepository extends BaseRepository
{
    public function getList(array $params)
    {
        $query = Comment::query()->with(['create_user', 'update_user']);
        if (!empty($params['torrent_id'])) {
            $query->where('torrent', $params['torrent_id']);
        }
        if (!empty($params['offer_id'])) {
            $query->where('offer', $params['offer_id']);
        }
        if (!empty($params['request_id'])) {
            $query->where('request', $params['request_id']);
        }
        list($sortField, $sortType) = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);
        return $query->paginate();
    }

    public function store(array $params, User $user)
    {
        $type = $params['type'];
        $modelName = Comment::TYPE_MAPS[$params['type']]['model'];
        /**
         * @var NexusModel $model
         */
        $model = new $modelName;
        $target = $model->newQuery()->with('user')->find($params[$type]);
        return DB::transaction(function () use ($params, $user, $target) {
            $comment = $user->comments()->create($params);
            $commentCount = Comment::query()->type($params['type'])->count();
            $target->comments = $commentCount;
            $target->save();

            $userUpdate = [
                'seedbonus' => DB::raw('seedbonus + ' . Setting::get('bonus.addcomment')),
                'last_comment' => Carbon::now(),
            ];
            $user->update($userUpdate);

            //message
            if ($target->user->commentpm == 'yes' && $user->id != $target->user->id) {
                $messageInfo = $this->getNoticeMessage($target, $params['type']);
                $insert = [
                    'sender' => 0,
                    'receiver' => $target->user->id,
                    'subject' => $messageInfo['subject'],
                    'msg' => $messageInfo['body'],
                    'added' => Carbon::now()
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
            '%s [url="%s/%s"]%s[/url]',
            $trans[$type]['msg_torrent_receive_comment'],
            getSchemeAndHttpHost(),
            sprintf($targetScript, $target->id),
            $target->{$targetNameField}
        );
        return compact('subject', 'body');
    }
}

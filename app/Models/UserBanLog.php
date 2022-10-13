<?php

namespace App\Models;

class UserBanLog extends NexusModel
{
    protected $table = 'user_ban_logs';

    protected $fillable = ['uid', 'username', 'operator', 'reason'];

    public static function clearUserBanLogDuplicate()
    {
        $lists = UserBanLog::query()
            ->selectRaw("min(id) as id, uid, count(*) as counts")
            ->groupBy('uid')
            ->having("counts", ">", 1)
            ->get();
        if ($lists->isEmpty()) {
            do_log("sql: " . last_query() . ", no data to delete");
            return;
        }
        $idArr = $lists->pluck("id")->toArray();
        $uidArr = $lists->pluck('uid')->toArray();
        $result = UserBanLog::query()->whereIn("uid", $uidArr)->whereNotIn("id", $idArr)->delete();
        do_log("sql: " . last_query() . ", result: $result");
    }


}

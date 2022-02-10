<?php
namespace App\Repositories;

use App\Models\BonusLogs;
use App\Models\HitAndRun;
use App\Models\Medal;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Nexus\Database\NexusDB;

class BonusRepository extends BaseRepository
{
    public function consumeToCancelHitAndRun($uid, $hitAndRunId): bool
    {
        if (!HitAndRun::getIsEnabled()) {
            throw new \LogicException("H&R not enabled.");
        }
        $user = User::query()->findOrFail($uid);
        $hitAndRun = HitAndRun::query()->findOrFail($hitAndRunId);
        if ($hitAndRun->uid != $uid) {
            throw new \LogicException("H&R: $hitAndRunId not belongs to user: $uid.");
        }
        $requireBonus = BonusLogs::getBonusForCancelHitAndRun();
        NexusDB::transaction(function () use ($user, $hitAndRun, $requireBonus) {
            $comment = nexus_trans('hr.bonus_cancel_comment', [
                'now' => Carbon::now()->toDateTimeString(),
                'bonus' => $requireBonus,
            ], $user->locale);
            $comment = addslashes($comment);
            do_log("comment: $comment");

            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_CANCEL_HIT_AND_RUN, "$comment(H&R ID: {$hitAndRun->id})");

            $hitAndRun->update([
                'status' => HitAndRun::STATUS_PARDONED,
                'comment' => NexusDB::raw("concat(comment, '\n$comment')"),
            ]);
        });

        return true;

    }


    public function consumeToBuyMedal($uid, $medalId): bool
    {
        $user = User::query()->findOrFail($uid);
        $medal = Medal::query()->findOrFail($medalId);
        $exists = $user->valid_medals()->where('medal_id', $medalId)->exists();
        do_log(last_query());
        if ($exists) {
            throw new \LogicException("user: $uid already own this medal: $medalId.");
        }
        $requireBonus = $medal->price;
        NexusDB::transaction(function () use ($user, $medal, $requireBonus) {
            $comment = nexus_trans('bonus.comment_buy_medal', [
                'bonus' => $requireBonus,
                'medal_name' => $medal->name,
            ], $user->locale);
            $comment = addslashes($comment);
            do_log("comment: $comment");
            $this->consumeUserBonus($user, $requireBonus, BonusLogs::BUSINESS_TYPE_BUY_MEDAL, "$comment(medal ID: {$medal->id})");
            $expireAt = null;
            if ($medal->duration > 0) {
                $expireAt = Carbon::now()->addDays($medal->duration)->toDateTimeString();
            }
            $user->medals()->attach([$medal->id => ['expire_at' => $expireAt]]);

        });

        return true;

    }

    private function consumeUserBonus($user, $requireBonus, $logBusinessType, $logComment = '')
    {
        if ($user->seedbonus < $requireBonus) {
            do_log("user: {$user->id}, bonus: {$user->seedbonus} < requireBonus: $requireBonus", 'error');
            throw new \LogicException("User bonus point not enough.");
        }
        NexusDB::transaction(function () use ($user, $requireBonus, $logBusinessType, $logComment) {
            $oldUserBonus = $user->seedbonus;
            $newUserBonus = bcsub($oldUserBonus, $requireBonus);
            $log = "user: {$user->id}, requireBonus: $requireBonus, oldUserBonus: $oldUserBonus, newUserBonus: $newUserBonus, logBusinessType: $logBusinessType, logComment: $logComment";
            do_log($log);
            $affectedRows = NexusDB::table($user->getTable())
                ->where('id', $user->id)
                ->where('seedbonus', $oldUserBonus)
                ->update(['seedbonus' => $newUserBonus]);
            if ($affectedRows != 1) {
                do_log("update user seedbonus affected rows != 1, query: " . last_query(), 'error');
                throw new \RuntimeException("Update user seedbonus fail.");
            }
            $bonusLog = [
                'business_type' => $logBusinessType,
                'uid' => $user->id,
                'old_total_value' => $oldUserBonus,
                'value' => $requireBonus,
                'new_total_value' => $newUserBonus,
                'comment' => $logComment,
            ];
            BonusLogs::query()->insert($bonusLog);
            do_log("bonusLog: " . nexus_json_encode($bonusLog));
        });
    }


}

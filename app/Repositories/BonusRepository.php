<?php
namespace App\Repositories;

use App\Models\BonusLogs;
use App\Models\HitAndRun;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Query\Expression;
use Nexus\Database\NexusDB;

class BonusRepository extends BaseRepository
{
    public function consumeToCancelHitAndRun($uid, $hitAndRunId)
    {
        $hitAndRunMode = Setting::get('hr.mode');
        if ($hitAndRunMode == HitAndRun::MODE_DISABLED) {
            throw new \LogicException("H&R not enabled.");
        }
        $user = User::query()->findOrFail($uid);
        $hitAndRun = HitAndRun::query()->findOrFail($hitAndRunId);
        if ($hitAndRun->uid != $uid) {
            throw new \LogicException("H&R: $hitAndRunId not belongs to user: $uid.");
        }
        $requireBonus = BonusLogs::getBonusForCancelHitAndRun();
        if ($user->seedbonus < $requireBonus) {
            do_log("user: $uid, bonus: {$user->seedbonus} < requireBonus: $requireBonus", 'error');
            throw new \LogicException("User bonus point not enough.");
        }
        $result = NexusDB::transaction(function () use ($user, $hitAndRun, $requireBonus) {
            $oldUserBonus = $user->seedbonus;
            $newUserBonus = bcsub($oldUserBonus, $requireBonus);
            $log = "user: {$user->id}, hitAndRun: {$hitAndRun->id}, requireBonus: $requireBonus, oldUserBonus: $oldUserBonus, newUserBonus: $newUserBonus";
            do_log($log);
            $affectedRows = NexusDB::table($user->getTable())
                ->where('id', $user->id)
                ->where('seedbonus', $oldUserBonus)
                ->update(['seedbonus' => $newUserBonus]);
            if ($affectedRows !=  1) {
                do_log("update user seedbonus affected rows != 1, query: " . last_query(), 'error');
                throw new \RuntimeException("Update user seedbonus fail.");
            }
            $comment = nexus_trans('hr.bonus_cancel_comment', [
                'now' => Carbon::now()->toDateTimeString(),
                'bonus' => $requireBonus,
            ], $user->locale);
            do_log("comment: $comment");
            $hitAndRun->update([
                'status' => HitAndRun::STATUS_PARDONED,
                'comment' => new Expression("concat(comment, '\n$comment')"),
            ]);
            $bonusLog = [
                'business_type' => BonusLogs::BUSINESS_TYPE_CANCEL_HIT_AND_RUN,
                'uid' => $user->id,
                'old_total_value' => $oldUserBonus,
                'value' => $requireBonus,
                'new_total_value' => $newUserBonus,
                'comment' => "$comment(H&R ID: {$hitAndRun->id})",
            ];
            BonusLogs::query()->insert($bonusLog);
            do_log("bonusLog: " . json_encode($bonusLog));
            return true;
        });

        return $result;

    }
}

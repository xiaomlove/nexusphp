<?php

namespace App\Models;


class BonusLogs extends NexusModel
{
    protected $table = 'bonus_logs';

    protected $fillable = ['uid', 'business_type', 'old_total_value', 'value', 'new_total_value', 'comment'];

    const DEFAULT_BONUS_CANCEL_ONE_HIT_AND_RUN = 10000;

    const BUSINESS_TYPE_CANCEL_HIT_AND_RUN = 1;
    const BUSINESS_TYPE_BUY_MEDAL = 2;

    public static $businessTypes = [
        self::BUSINESS_TYPE_CANCEL_HIT_AND_RUN => ['text' => 'Cancel H&R'],
        self::BUSINESS_TYPE_BUY_MEDAL => ['text' => 'Buy medal'],
    ];

    public static function getBonusForCancelHitAndRun()
    {
        $result = Setting::get('bonus.cancel_hr');
        return $result ?? self::DEFAULT_BONUS_CANCEL_ONE_HIT_AND_RUN;
    }


}

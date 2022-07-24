<?php

namespace App\Models;


class BonusLogs extends NexusModel
{
    protected $table = 'bonus_logs';

    protected $fillable = ['uid', 'business_type', 'old_total_value', 'value', 'new_total_value', 'comment'];

    const DEFAULT_BONUS_CANCEL_ONE_HIT_AND_RUN = 10000;
    const DEFAULT_BONUS_BUY_ATTENDANCE_CARD = 1000;

    const BUSINESS_TYPE_CANCEL_HIT_AND_RUN = 1;
    const BUSINESS_TYPE_BUY_MEDAL = 2;
    const BUSINESS_TYPE_BUY_ATTENDANCE_CARD = 3;
    const BUSINESS_TYPE_STICKY_PROMOTION = 4;
    const BUSINESS_TYPE_POST_REWARD = 5;
    const BUSINESS_TYPE_EXCHANGE_UPLOAD = 6;
    const BUSINESS_TYPE_EXCHANGE_INVITE = 7;
    const BUSINESS_TYPE_CUSTOM_TITLE = 8;
    const BUSINESS_TYPE_BUY_VIP = 9;
    const BUSINESS_TYPE_GIFT_TO_SOMEONE = 10;
    const BUSINESS_TYPE_NO_AD = 11;
    const BUSINESS_TYPE_GIFT_TO_LOW_SHARE_RATIO = 12;

    public static array $businessTypes = [
        self::BUSINESS_TYPE_CANCEL_HIT_AND_RUN => ['text' => 'Cancel H&R'],
        self::BUSINESS_TYPE_BUY_MEDAL => ['text' => 'Buy medal'],
        self::BUSINESS_TYPE_BUY_ATTENDANCE_CARD => ['text' => 'Buy attendance card'],
        self::BUSINESS_TYPE_STICKY_PROMOTION => ['text' => 'Buy torrent sticky promotion'],
        self::BUSINESS_TYPE_POST_REWARD => ['text' => 'Reward post'],
        self::BUSINESS_TYPE_EXCHANGE_UPLOAD => ['text' => 'Exchange upload'],
        self::BUSINESS_TYPE_EXCHANGE_INVITE => ['text' => 'Exchange invite'],
        self::BUSINESS_TYPE_CUSTOM_TITLE => ['text' => 'Custom title'],
        self::BUSINESS_TYPE_BUY_VIP => ['text' => 'Buy VIP'],
        self::BUSINESS_TYPE_GIFT_TO_SOMEONE => ['text' => 'Gift to someone'],
        self::BUSINESS_TYPE_NO_AD => ['text' => 'No ad'],
        self::BUSINESS_TYPE_GIFT_TO_LOW_SHARE_RATIO => ['text' => 'Gift to low share ratio'],
    ];

    public static function getBonusForCancelHitAndRun()
    {
        $result = Setting::get('bonus.cancel_hr');
        return $result ?? self::DEFAULT_BONUS_CANCEL_ONE_HIT_AND_RUN;
    }

    public static function getBonusForBuyAttendanceCard()
    {
        $result = Setting::get('bonus.attendance_card');
        return $result ?? self::DEFAULT_BONUS_BUY_ATTENDANCE_CARD;
    }


}

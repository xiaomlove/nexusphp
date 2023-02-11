<?php

namespace App\Models;


class BonusLogs extends NexusModel
{
    protected $table = 'bonus_logs';

    protected $fillable = ['uid', 'business_type', 'old_total_value', 'value', 'new_total_value', 'comment'];

    public $timestamps = true;

    const DEFAULT_BONUS_CANCEL_ONE_HIT_AND_RUN = 10000;
    const DEFAULT_BONUS_BUY_ATTENDANCE_CARD = 1000;
    const DEFAULT_BONUS_BUY_TEMPORARY_INVITE = 500;
    const DEFAULT_BONUS_BUY_RAINBOW_ID = 5000;
    const DEFAULT_BONUS_BUY_CHANGE_USERNAME_CARD = 100000;

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
    const BUSINESS_TYPE_LUCKY_DRAW = 13;
    const BUSINESS_TYPE_EXCHANGE_DOWNLOAD = 14;
    const BUSINESS_TYPE_BUY_TEMPORARY_INVITE = 15;
    const BUSINESS_TYPE_BUY_RAINBOW_ID = 16;
    const BUSINESS_TYPE_BUY_CHANGE_USERNAME_CARD = 17;
    const BUSINESS_TYPE_GIFT_MEDAL = 18;
    const BUSINESS_TYPE_BUY_TORRENT = 19;

    const BUSINESS_TYPE_ROLE_WORK_SALARY = 1000;
    const BUSINESS_TYPE_TORRENT_BE_DOWNLOADED = 1001;

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
        self::BUSINESS_TYPE_LUCKY_DRAW => ['text' => 'Lucky draw'],
        self::BUSINESS_TYPE_EXCHANGE_DOWNLOAD => ['text' => 'Exchange download'],
        self::BUSINESS_TYPE_BUY_TEMPORARY_INVITE => ['text' => 'Buy temporary invite'],
        self::BUSINESS_TYPE_BUY_RAINBOW_ID => ['text' => 'Buy rainbow ID'],
        self::BUSINESS_TYPE_BUY_CHANGE_USERNAME_CARD => ['text' => 'Buy change username card'],
        self::BUSINESS_TYPE_GIFT_MEDAL => ['text' => 'Gift medal to someone'],
        self::BUSINESS_TYPE_BUY_TORRENT => ['text' => 'Buy torrent'],

        self::BUSINESS_TYPE_ROLE_WORK_SALARY => ['text' => 'Role work salary'],
        self::BUSINESS_TYPE_TORRENT_BE_DOWNLOADED => ['text' => 'Torrent be downloaded'],
    ];

    public function getBusinessTypeTextAttribute()
    {
        return nexus_trans('bonus-log.business_types.' . $this->business_type);
    }

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

    public static function getBonusForBuyTemporaryInvite()
    {
        $result = Setting::get('bonus.one_tmp_invite');
        return $result ?? self::DEFAULT_BONUS_BUY_TEMPORARY_INVITE;
    }

    public static function getBonusForBuyRainbowId()
    {
        $result = Setting::get('bonus.rainbow_id');
        return $result ?? self::DEFAULT_BONUS_BUY_RAINBOW_ID;
    }

    public static function getBonusForBuyChangeUsernameCard()
    {
        $result = Setting::get('bonus.change_username_card');
        return $result ?? self::DEFAULT_BONUS_BUY_CHANGE_USERNAME_CARD;
    }


}

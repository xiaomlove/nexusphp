<?php

namespace App\Models;


class Claim extends NexusModel
{
    protected $fillable = ['uid', 'torrent_id', 'snatched_id', 'seed_time_begin', 'uploaded_begin', 'last_settle_at'];

    public $timestamps = true;

    const TORRENT_TTL = 30;
    const USER_UP_LIMIT = 10;
    const TORRENT_UP_LIMIT = 1000;
    const REMOVE_DEDUCT = 600;
    const GIVE_UP_DEDUCT = 400;
    const BONUS_MULTIPLIER = 1;
    const STANDARD_SEED_TIME_HOURS = 300;
    const STANDARD_UPLOADED_TIMES = 2;

    protected $casts = [
        'last_settle_at' => 'datetime',
    ];

    public function getSeedTimeThisMonthAttribute()
    {
        return mkprettytime($this->snatch->seedtime - $this->seed_time_begin);
    }

    public function getUploadedThisMonthAttribute()
    {
        return mksize($this->snatch->uploaded - $this->uploaded_begin);
    }

    public function getIsReachedThisMonthAttribute(): bool
    {
        $seedTimeRequiredHours = self::getConfigStandardSeedTimeHours();
        $uploadedRequiredTimes = self::getConfigStandardUploadedTimes();
        if (
            bcsub($this->snatch->seedtime, $this->seed_time_begin) >= $seedTimeRequiredHours * 3600
            || bcsub($this->snatch->uploaded, $this->uploaded_begin) >= $uploadedRequiredTimes * $this->torrent->size
        ) {
            return true;
        } else {
            return false;
        }
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'uid');
    }

    public function torrent()
    {
        return $this->belongsTo(Torrent::class, 'torrent_id');
    }

    public function snatch()
    {
        return $this->belongsTo(Snatch::class, 'snatched_id');
    }

    public static function getConfigIsEnabled(): bool
    {
        return Setting::get('torrent.claim_enabled', 'no') == 'yes';
    }

    public static function getConfigTorrentTTL()
    {
        return Setting::get('torrent.claim_torrent_ttl', self::TORRENT_TTL);
    }

    public static function getConfigUserUpLimit()
    {
        return Setting::get('torrent.claim_torrent_user_counts_up_limit', self::USER_UP_LIMIT);
    }

    public static function getConfigTorrentUpLimit()
    {
        return Setting::get('torrent.claim_user_torrent_counts_up_limit', self::TORRENT_UP_LIMIT);
    }

    public static function getConfigRemoveDeductBonus()
    {
        return Setting::get('torrent.claim_remove_deduct_user_bonus', self::REMOVE_DEDUCT);
    }

    public static function getConfigGiveUpDeductBonus()
    {
        return Setting::get('torrent.claim_give_up_deduct_user_bonus', self::GIVE_UP_DEDUCT);
    }

    public static function getConfigBonusMultiplier()
    {
        return Setting::get('torrent.claim_bonus_multiplier', self::BONUS_MULTIPLIER);
    }

    public static function getConfigStandardSeedTimeHours()
    {
        return Setting::get('torrent.claim_reach_standard_seed_time', self::STANDARD_SEED_TIME_HOURS);
    }

    public static function getConfigStandardUploadedTimes()
    {
        return Setting::get('torrent.claim_reach_standard_uploaded', self::STANDARD_UPLOADED_TIMES);
    }
}

<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Builder;

class Snatch extends NexusModel
{
    protected $table = 'snatched';

    protected $casts = [
        'last_action' => 'datetime',
        'startdat' => 'datetime',
        'completedat' => 'datetime',
    ];

    public static $cardTitles = [
        'upload_text' => '上传',
        'download_text' => '下载',
        'share_ratio' => '分享率',
        'seed_time' => '做种时间',
        'leech_time' => '下载时间',
        'completed_at_human' => '完成',
    ];

    const FINISHED_YES = 'yes';

    const FINISHED_NO = 'no';

    public function scopeIsFinished(Builder $builder)
    {
        return $builder->where('finished', self::FINISHED_YES);
    }

    public function scopeIsNotFinished(Builder $builder)
    {
        return $builder->where('finished', self::FINISHED_NO);
    }

    public function torrent()
    {
        return $this->belongsTo(Torrent::class, 'torrentid');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userid');
    }
}

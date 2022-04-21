<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class Peer extends NexusModel
{
    protected $fillable = [
        'torrent', 'peer_id', 'ip', 'port', 'uploaded', 'downloaded', 'to_go', 'seeder', 'started', 'last_action',
        'prev_action', 'connectable', 'userid', 'agent', 'finishedat', 'downloadoffset', 'uploadedoffset', 'passkey',
        'ipv4', 'ipv6',
    ];

    const CONNECTABLE_YES = 'yes';

    const CONNECTABLE_NO = 'no';

    protected $casts = [
        'started' => 'datetime',
        'last_action' => 'datetime',
        'prev_action' => 'datetime',
        'finishedat' => 'datetime:U',
    ];

    public static $connectableText = [
        self::CONNECTABLE_YES => '是',
        self::CONNECTABLE_NO => '否',
    ];

    const SEEDER_YES = 'yes';

    const SEEDER_NO = 'no';

    public static $cardTitles = [
        'upload_text' => '上传',
        'download_text' => '下载',
        'share_ratio' => '分享率',
        'agent_human' => '客户端',
        'connect_time_total' => '连接时间',
        'download_progress' => '完成进度',

    ];

    public function getConnectableTextAttribute()
    {
        return self::$connectableText[$this->connectable] ?? '';
    }

    public function scopeIsSeeder(Builder $builder)
    {
        return $builder->where('seeder', self::SEEDER_YES);
    }

    public function scopeIsNotSeeder(Builder $builder)
    {
        return $builder->where('seeder', self::SEEDER_NO);
    }

    public function isSeeder()
    {
        return $this->seeder == self::SEEDER_YES;
    }

    public function isNotSeeder()
    {
        return $this->seeder == self::SEEDER_NO;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'userid');
    }

    public function relative_torrent()
    {
        return $this->belongsTo(Torrent::class, 'torrent');
    }
}

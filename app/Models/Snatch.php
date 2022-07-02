<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use JetBrains\PhpStorm\Pure;

class Snatch extends NexusModel
{
    protected $table = 'snatched';

    protected $fillable = [
        'torrentid', 'userid', 'ip', 'port', 'uploaded', 'downloaded', 'to_go', 'seedtime', 'leechtime',
        'last_action', 'startdat', 'completedat', 'finished'
    ];

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

    /**
     * @deprecated Use uploadedText instead
     * @return Attribute
     */
    protected function uploadText(): Attribute
    {
        return new Attribute(
            get: fn($value, $attributes) => sprintf('%s@%s', mksize($attributes['uploaded']), $this->getUploadSpeed())
        );
    }

    /**
     * @deprecated Use downloadedText instead
     * @return Attribute
     */
    protected function downloadText(): Attribute
    {
        return new Attribute(
            get: fn($value, $attributes) => sprintf('%s@%s', mksize($attributes['downloaded']), $this->getDownloadSpeed())
        );
    }

    protected function uploadedText(): Attribute
    {
        return new Attribute(
            get: fn($value, $attributes) => sprintf('%s@%s', mksize($attributes['uploaded']), $this->getUploadSpeed())
        );
    }

    protected function downloadedText(): Attribute
    {
        return new Attribute(
            get: fn($value, $attributes) => sprintf('%s@%s', mksize($attributes['downloaded']), $this->getDownloadSpeed())
        );
    }

    protected function shareRatio(): Attribute
    {
        return new Attribute(
            get: fn($value, $attributes) => $this->getShareRatio()
        );
    }

    public function getUploadSpeed(): string
    {
        if ($this->seedtime <= 0) {
            $speed =  mksize(0);
        } else {
            $speed = mksize($this->uploaded / ($this->seedtime + $this->leechtime));
        }
        return "$speed/s";
    }

    public function getDownloadSpeed(): string
    {
        if ($this->leechtime <= 0) {
            $speed = mksize(0);
        } else {
            $speed = mksize($this->downloaded / $this->leechtime);
        }
        return "$speed/s";
    }

    public function getShareRatio()
    {
        if ($this->downloaded) {
            $ratio = floor(($this->uploaded / $this->downloaded) * 1000) / 1000;
        } elseif ($this->uploaded) {
            $ratio = nexus_trans('snatch.share_ratio_infinity');
        } else {
            $ratio = '---';
        }
        return $ratio;
    }

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

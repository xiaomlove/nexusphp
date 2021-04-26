<?php

namespace App\Models;

class ExamUser extends NexusModel
{
    protected $fillable = ['exam_id', 'uid', 'status', 'progress'];

    public $timestamps = true;

    const STATUS_NORMAL = 0;
    const STATUS_FINISHED = 1;

    public static $status = [
        self::STATUS_NORMAL => ['text' => 'Normal'],
        self::STATUS_FINISHED => ['text' => 'Finished'],
    ];

    protected $casts = [
        'progress' => 'json'
    ];

    public function getStatusTextAttribute()
    {
        return self::$status[$this->status]['text'] ?? '';
    }

    public function exam(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Exam::class, 'exam_id');
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'uid');
    }

    public function progresses()
    {
        return $this->hasMany(ExamProgress::class, 'exam_user_id');
    }


}

<?php

namespace App\Models;

class ExamUser extends NexusModel
{
    protected $fillable = ['exam_id', 'uid', 'status', 'progress', 'begin', 'end', 'is_done'];

    public $timestamps = true;

    const STATUS_NORMAL = 0;
    const STATUS_FINISHED = 1;

    public static $status = [
        self::STATUS_NORMAL => ['text' => 'Normal'],
        self::STATUS_FINISHED => ['text' => 'Finished'],
    ];

    const IS_DONE_YES = 1;
    const IS_DONE_NO = 0;


    protected $casts = [
        'progress' => 'json'
    ];

    public function getStatusTextAttribute(): string
    {
        return self::$status[$this->status]['text'] ?? '';
    }

    public function getBeginAttribute()
    {
        $begin = $this->getRawOriginal('begin');
        $end = $this->getRawOriginal('end');
        if ($begin && $end) {
            do_log(sprintf('examUser: %s, begin from self', $this->id));
            return $begin;
        }

        $exam = $this->exam;
        $begin = $exam->getRawOriginal('begin');
        $end = $exam->getRawOriginal('end');
        if ($begin && $end) {
            do_log(sprintf('examUser: %s, begin from exam: %s', $this->id, $exam->id));
            return $begin;
        }

        if ($exam->duration > 0) {
            do_log(sprintf('examUser: %s, begin from self created_at', $this->id));
            return $this->created_at->toDateTimeString();
        }
        return null;
    }

    public function getEndAttribute()
    {
        $begin = $this->getRawOriginal('begin');
        $end = $this->getRawOriginal('end');
        if ($begin && $end) {
            do_log(sprintf('examUser: %s, end from self', $this->id));
            return $end;
        }

        $exam = $this->exam;
        $begin = $exam->getRawOriginal('begin');
        $end = $exam->getRawOriginal('end');
        if ($begin && $end) {
            do_log(sprintf('examUser: %s, end from exam: %s', $this->id, $exam->id));
            return $end;
        }

        $duration = $exam->duration;
        if ($duration > 0) {
            do_log(sprintf('examUser: %s, end from self created_at + exam: %s %s days', $this->id, $exam->id, $duration));
            return $this->created_at->addDays($duration)->toDateTimeString();
        }
        return null;
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

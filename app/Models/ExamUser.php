<?php

namespace App\Models;

use App\Repositories\ExamRepository;

class ExamUser extends NexusModel
{
    protected $fillable = ['exam_id', 'uid', 'status', 'progress', 'begin', 'end', 'is_done'];

    public $timestamps = true;

    const STATUS_NORMAL = 0;
    const STATUS_FINISHED = 1;
    const STATUS_AVOIDED = -1;

    public static array $status = [
        self::STATUS_NORMAL => ['text' => 'Normal'],
        self::STATUS_FINISHED => ['text' => 'Finished'],
        self::STATUS_AVOIDED => ['text' => 'Avoided'],
    ];

    const IS_DONE_YES = 1;
    const IS_DONE_NO = 0;

    public static array $isDoneInfo = [
        self::IS_DONE_YES => ['text' => 'Yes'],
        self::IS_DONE_NO => ['text' => 'No'],
    ];


    protected $casts = [
        'progress' => 'json'
    ];

    public function getStatusTextAttribute(): string
    {
        return nexus_trans('exam-user.status.' . $this->status);
    }

    public function getIsDoneTextAttribute(): string
    {
        return self::$isDoneInfo[$this->is_done]['text'] ?? '';
    }

    public function getProgressFormattedAttribute(): array
    {
        $examRep = new ExamRepository();
        return $examRep->getProgressFormatted($this->exam, $this->progress);
    }

    public static function listStatus($onlyKeyValue = false): array
    {
        $result = self::$status;
        $keyValues = [];
        foreach ($result as $key => &$value) {
            $text = nexus_trans('exam-user.status.' . $key);
            $value['text'] = $text;
            $keyValues[$key] = $text;
        }
        if ($onlyKeyValue) {
            return $keyValues;
        }
        return $result;
    }

    public function getBeginAttribute()
    {
        $begin = $this->getRawOriginal('begin');
        $end = $this->getRawOriginal('end');
        if ($begin && $end) {
            do_log(sprintf('examUser: %s, begin from self: %s', $this->id, $begin));
            return $begin;
        }

        $exam = $this->exam;
        $begin = $exam->getRawOriginal('begin');
        $end = $exam->getRawOriginal('end');
        if ($begin && $end) {
            do_log(sprintf('examUser: %s, begin from exam(%s): %s', $this->id, $exam->id, $begin));
            return $begin;
        }

        if ($exam->duration > 0) {
            do_log(sprintf('examUser: %s, begin from self created_at(%s)', $this->id, $this->getRawOriginal('created_at')));
            return $this->created_at->toDateTimeString();
        }
        return null;
    }

    public function getEndAttribute()
    {
        $begin = $this->getRawOriginal('begin');
        $end = $this->getRawOriginal('end');
        if ($begin && $end) {
            do_log(sprintf('examUser: %s, end from self: %s', $this->id, $end));
            return $end;
        }

        $exam = $this->exam;
        $begin = $exam->getRawOriginal('begin');
        $end = $exam->getRawOriginal('end');
        if ($begin && $end) {
            do_log(sprintf('examUser: %s, end from exam(%s): %s', $this->id, $exam->id, $end));
            return $end;
        }

        $duration = $exam->duration;
        if ($duration > 0) {
            do_log(sprintf('examUser: %s, end from self created_at + exam(%s) created_at: %s + %s days', $this->id, $exam->id, $this->getRawOriginal('created_at'), $duration));
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

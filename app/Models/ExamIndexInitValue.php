<?php

namespace App\Models;

class ExamIndexInitValue extends NexusModel
{
    protected $fillable = ['uid', 'exam_id', 'index', 'value',];

    public $timestamps = true;
}

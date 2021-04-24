<?php

namespace App\Models;

class ExamProgress extends NexusModel
{
    protected $fillable = ['exam_id', 'uid', 'type_id', 'value'];

    public $timestamps = true;
}

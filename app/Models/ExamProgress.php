<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExamProgress extends NexusModel
{
    use HasFactory;

    protected $fillable = ['exam_id', 'uid', 'type_id', 'value'];
}

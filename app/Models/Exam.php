<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exam extends NexusModel
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'begin', 'end', 'status'];
}

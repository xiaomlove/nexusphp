<?php

namespace App\Models;

class UserRole extends NexusModel
{
    public $timestamps = true;

    protected $fillable = ['uid', 'role_id'];

}

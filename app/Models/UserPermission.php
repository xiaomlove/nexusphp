<?php

namespace App\Models;

class UserPermission extends NexusModel
{
    public $timestamps = true;

    protected $fillable = ['uid', 'permission_id'];

}

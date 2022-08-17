<?php

namespace App\Models;

class RolePermission extends NexusModel
{
    public $timestamps = true;

    protected $fillable = ['role_id', 'permission_id'];

}

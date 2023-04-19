<?php

namespace App\Models;
class ForumMod extends NexusModel
{
    protected $table = 'forummods';

    protected $fillable = ['forumid', 'userid'];

}

<?php

namespace App\Models;


class Forum extends NexusModel
{
    protected $fillable = ['sort', 'name', 'description', 'minclassread', 'minclasswrite', 'postcount', 'topiccount', 'minclasscreate', 'forid'];

    public function moderators()
    {
        return $this->belongsToMany(User::class, "forummods", "forumid", "userid");
    }
}

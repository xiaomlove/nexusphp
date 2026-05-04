<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Forum extends NexusModel
{
    protected $fillable = ['sort', 'name', 'description', 'minclassread', 'minclasswrite', 'postcount', 'topiccount', 'minclasscreate', 'forid'];

    public function moderators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'forummods', 'forumid', 'userid');
    }
}

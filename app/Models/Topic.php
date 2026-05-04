<?php

namespace App\Models;

use App\Models\Traits\NexusActivityLogTrait;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Topic extends NexusModel
{
    use NexusActivityLogTrait;

    protected $fillable = ['userid', 'subject', 'locked', 'forumid', 'firstpost', 'lastpost', 'sticky', 'hlcolor', 'views'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'userid');
    }

    public function forum()
    {
        return $this->belongsTo(Forum::class, 'forumid');
    }

    public function firstPost()
    {
        return $this->belongsTo(Post::class, 'firstpost');
    }

    public function lastPost()
    {
        return $this->belongsTo(Post::class, 'lastpost');
    }
}

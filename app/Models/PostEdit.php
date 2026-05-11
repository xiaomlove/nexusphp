<?php

namespace App\Models;

use App\Services\ForumPostService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Snapshot of a post body / subject just before it was edited.
 *
 * Written by {@see ForumPostService::editPost()} on
 * every successful edit so users can browse the edit history of a
 * post without losing context. The current (post-edit) body lives
 * on the `posts` row itself; this table only stores the *previous*
 * value, plus who made the edit and when.
 *
 * @property int $id
 * @property int $postid
 * @property int $editor_userid
 * @property string $body_before
 * @property string|null $subject_before
 * @property Carbon|null $edited_at
 */
class PostEdit extends Model
{
    protected $table = 'post_edits';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'edited_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'postid');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_userid');
    }

    /**
     * Convenience accessor used by the Livewire history viewer.
     */
    protected function editedAtForHumans(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->edited_at?->diffForHumans());
    }
}

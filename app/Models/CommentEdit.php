<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot of a comment body taken just before an edit was applied
 * via comment.php?action=edit. See {@see PostEdit} for the parallel
 * implementation on forum posts. Read-only — written by the legacy
 * edit handler, displayed by the inline history viewer.
 */
class CommentEdit extends Model
{
    protected $table = 'comment_edits';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'edited_at' => 'datetime',
    ];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_userid');
    }

    protected function editedAtForHumans(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->edited_at?->diffForHumans());
    }
}

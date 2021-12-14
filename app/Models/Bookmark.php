<?php

namespace App\Models;


class Bookmark extends NexusModel
{
    protected $table = 'bookmarks';

    protected $fillable = ['userid', 'torrentid'];
}

<?php

namespace App\Models;


class Icon extends NexusModel
{
    protected $table = 'caticons';

    protected $fillable = ['name', 'folder', 'cssfile', 'multilang', 'secondicon', 'designer', 'comment'];
}

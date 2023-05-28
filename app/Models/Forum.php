<?php

namespace App\Models;


class Forum extends NexusModel
{
    protected $fillable = ['sort', 'name', 'description', 'minclassread', 'minclasswrite', 'postcount', 'topiccount', 'minclasscreate', 'forid'];

}

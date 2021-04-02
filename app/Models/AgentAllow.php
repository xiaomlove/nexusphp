<?php

namespace App\Models;

class AgentAllow extends NexusModel
{
    protected $table = 'agent_allowed_family';

    protected $fillable = [
        'family', 'start_name', 'exception', 'allowhttps', 'comment', 'hits',
        'peer_id_pattern', 'peer_id_match_num', 'peer_id_matchtype', 'peer_id_start',
        'agent_pattern', 'agent_match_num', 'agent_matchtype', 'agent_start',
    ];
}

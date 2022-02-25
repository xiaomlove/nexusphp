<?php

namespace App\Models;

class AgentAllow extends NexusModel
{
    protected $table = 'agent_allowed_family';

    protected $fillable = [
        'family', 'start_name', 'exception', 'allowhttps', 'comment',
        'peer_id_pattern', 'peer_id_match_num', 'peer_id_matchtype', 'peer_id_start',
        'agent_pattern', 'agent_match_num', 'agent_matchtype', 'agent_start',
    ];

    const MATCH_TYPE_DEC = 'dec';
    const MATCH_TYPE_HEX = 'hex';

    public static $matchTypes = [
        self::MATCH_TYPE_DEC => 'dec',
        self::MATCH_TYPE_HEX => 'hex',
    ];

    public function denies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AgentDeny::class, 'family_id');
    }


}

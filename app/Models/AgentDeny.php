<?php

namespace App\Models;

class AgentDeny extends NexusModel
{
    protected $table = 'agent_allowed_exception';

    protected $fillable = [
        'family_id', 'name', 'peer_id', 'agent', 'comment'
    ];

    public function family(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AgentAllow::class, 'family_id');
    }
}

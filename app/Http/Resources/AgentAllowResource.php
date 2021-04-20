<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AgentAllowResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'family' => $this->family,
            'start_name' => $this->start_name,
            'peer_id_pattern' => $this->peer_id_pattern,
            'peer_id_match_num' => $this->peer_id_match_num,
            'peer_id_matchtype' => $this->peer_id_matchtype,
            'peer_id_start' => $this->peer_id_start,
            'agent_pattern' => $this->agent_pattern,
            'agent_match_num' => $this->agent_match_num,
            'agent_matchtype' => $this->agent_matchtype,
            'agent_start' => $this->agent_start,
            'exception' => $this->exception,
            'comment' => $this->comment,
            'allowhttps' => $this->allowhttps,
            'hits' => $this->hits,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AgentDenyResource extends JsonResource
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
            'family_id' => $this->family_id,
            'agent' => $this->agent,
            'peer_id' => $this->peer_id,
            'comment' => $this->comment,
            'name' => $this->name,
            'family' => new AgentAllowResource($this->whenLoaded('family'))
        ];
    }
}

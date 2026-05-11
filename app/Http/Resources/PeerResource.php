<?php

namespace App\Http\Resources;

use App\Models\Peer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Peer
 */
class PeerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'connectable_text' => $this->connectableText,
            'upload_text' => $this->upload_text,
            'download_text' => $this->download_text,
            'share_ratio' => $this->share_ratio,
            'download_progress' => $this->download_progress,
            'connect_time_total' => $this->connect_time_total,
            'last_action_human' => $this->last_action_human,
            'agent_human' => $this->agent_human,
            'user' => new UserResource($this->whenLoaded('user')),

        ];
    }
}

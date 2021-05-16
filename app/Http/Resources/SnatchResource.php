<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SnatchResource extends PeerResource
{
    /**
     * Transform the resource into an array.
     * @see viewsnatches.php
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'upload_text' => $this->upload_text,
            'download_text' => $this->download_text,
            'share_ratio' => $this->share_ratio,
            'seed_time' => $this->seed_time,
            'leech_time' => $this->leech_time,
            'completed_at_human' => $this->completed_at_human,
            'last_action_human' => $this->last_action_human,
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}

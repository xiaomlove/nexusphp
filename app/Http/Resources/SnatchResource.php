<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SnatchResource extends JsonResource
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
            'seed_time' => mkprettytime($this->seedtime),
            'leech_time' => mkprettytime($this->leechtime),
            'completed_at_human' => $this->completedat ? $this->completedat->diffForHumans() : '',
            'last_action_human' => $this->last_action ? $this->last_action->diffForHumans() : '',
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}

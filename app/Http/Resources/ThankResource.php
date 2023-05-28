<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ThankResource extends JsonResource
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
            'torrent_id' => $this->torrentid,
            'user_id' => $this->userid,
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}

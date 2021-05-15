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
        $uploaded = mksize($this->uploaded);
        $downloaded = mksize($this->downloaded);
        $seedtime = mkprettytime($this->seedtime);
        $leechtime = mkprettytime($this->leechtime);
        $uprate = $this->seedtime > 0 ? mksize($this->uploaded / ($this->seedtime + $this->leechtime)) : mksize(0);
        $downrate = $this->leechtime > 0 ? mksize($this->downloaded / $this->leechtime) : mksize(0);
        $nowTimestamp = time();

        return [
            'id' => $this->id,
            'upload_text' => $uploaded . "@" . $uprate . "/s",
            'download_text' => $downloaded . "@" . $downrate . "/s",
            'share_ratio' => $this->getShareRatio($this->resource),
            'seed_time' => $seedtime,
            'leech_time' => $leechtime,
            'completed_at_human' => mkprettytime($nowTimestamp - $this->completedat->timestamp),
            'last_action_human' => mkprettytime($nowTimestamp - $this->last_action->timestamp),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class HitAndRunResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $out = [
            'id' => $this->id,
            'uid' => $this->uid,
            'user' => new UserResource($this->whenLoaded('user')),
            'torrent_id' => $this->torrent_id,
            'torrent' => new TorrentResource($this->whenLoaded('torrent')),
            'snatched_id' => $this->snatched_id,
            'snatch' => new SnatchResource($this->whenLoaded('snatch')),
            'status' => $this->status,
            'status_text' => $this->status_text,
            'comment' => $this->comment,
            'created_at' => format_datetime($this->created_at),
            'updated_at' => format_datetime($this->updated_at),
            'seed_time_required' => $this->seedTimeRequired,
            'inspect_time_left' => $this->inspectTimeLeft,
        ];
        if (nexus()->isPlatformAdmin()) {
            $out['comment'] = nl2br(trim($out['comment']));
        }
        return $out;
    }
}

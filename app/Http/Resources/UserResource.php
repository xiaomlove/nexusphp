<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'email' => $this->email,
            'username' => $this->username,
            'status' => $this->status,
            'enabled' => $this->enabled,
            'added' => format_datetime($this->added),
            'class' => $this->class,
            'class_text' => $this->class_text,
            'avatar' => $this->avatar,
            'uploaded' => $this->uploaded,
            'uploaded_text' => mksize($this->uploaded),
            'downloaded' => $this->downloaded,
            'downloaded_text' => mksize($this->downloaded),
            'bonus' => $this->seedbonus,
            'seedtime' => $this->seedtime,
            'seedtime_text' => mkprettytime($this->seedtime),
            'leechtime' => $this->leechtime,
            'leechtime_text' => mkprettytime($this->leechtime),
            'inviter' => new UserResource($this->whenLoaded('inviter')),
            'valid_medals' => MedalResource::collection($this->whenLoaded('valid_medals')),
        ];
        if ($request->routeIs('user.me')) {
            $out['downloaded_human'] = mksize($this->downloaded);
            $out['uploaded_human'] = mksize($this->uploaded);
            $out['seed_time'] = mkprettytime($this->seedtime);
            $out['leech_time'] = mkprettytime($this->leechtime);
            $out['share_ratio'] = get_share_ratio($this->uploaded, $this->downloaded);
            $out['seed_bonus'] = $this->seedbonus;
            $out['invites'] = $this->invites;
            $out['comments_count'] = $this->comments_count;
            $out['posts_count'] = $this->posts_count;
        }
        return $out;
    }
}

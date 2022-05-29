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
            'last_access' => format_datetime($this->last_access),
            'class' => $this->class,
            'class_text' => $this->class_text,
            'avatar' => $this->avatar,
            'invites' => $this->invites,
            'attendance_card' => $this->attendance_card,
            'uploaded' => $this->uploaded,
            'uploaded_text' => mksize($this->uploaded),
            'downloaded' => $this->downloaded,
            'downloaded_text' => mksize($this->downloaded),
            'bonus' => number_format($this->seedbonus, 1),
            'seed_points' => floatval($this->seed_points),
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
            $out['comments_count'] = $this->comments_count;
            $out['posts_count'] = $this->posts_count;
            $out['torrents_count'] = $this->torrents_count;
            $out['seeding_torrents_count'] = $this->seeding_torrents_count;
            $out['leeching_torrents_count'] = $this->leeching_torrents_count;
            $out['completed_torrents_count'] = $this->completed_torrents_count;
            $out['incomplete_torrents_count'] = $this->incomplete_torrents_count;
        }

        if (nexus()->isPlatformAdmin() && $request->routeIs('users.show')) {
            $out['two_step_secret'] = $this->two_step_secret;
        }

        return $out;
    }
}

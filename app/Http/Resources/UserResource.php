<?php

namespace App\Http\Resources;

use App\Auth\Permission;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    const NAME = 'user';
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
            'username' => $this->username,
            'email' => $this->when(Gate::allows("viewEmail", $this->resource), $this->email),
            'status' => $this->status,
            'enabled' => $this->enabled,
            'added' => format_datetime($this->added),
            'added_human' => gettime($this->added),
            'last_access' => format_datetime($this->last_access),
            'last_access_human' => gettime($this->last_access),
            'last_login' => format_datetime($this->last_login),
            'last_login_human' => gettime($this->last_login),
            'class' => $this->class,
            'class_text' => $this->class_text,
            'avatar' => $this->avatar,
            'invites' => $this->invites,
            'attendance_card' => $this->attendance_card,
            'uploaded' => $this->uploaded,
            'uploaded_text' => mksize($this->uploaded),
            'downloaded' => $this->downloaded,
            'downloaded_text' => mksize($this->downloaded),
            'bonus' => floatval($this->seedbonus),
            'bonus_human' => number_format($this->seedbonus, 1),
            'seed_points' => floatval($this->seed_points),
            'seed_points_human' => number_format($this->seed_points, 1),
            'seed_points_per_hour' => floatval($this->seed_points_per_hour),
            'seed_points_per_hour_human' => number_format($this->seed_points_per_hour, 1),
            'seed_bonus_per_hour' => floatval($this->seed_bonus_per_hour),
            'seed_bonus_per_hour_human' => number_format($this->seed_bonus_per_hour, 1),
            'seedtime' => $this->seedtime,
            'seedtime_text' => mkprettytime($this->seedtime),
            'leechtime' => $this->leechtime,
            'leechtime_text' => mkprettytime($this->leechtime),
            'share_ratio' => get_ratio($this->id),
            'seeding_leeching_data' => $this->whenHas('seeding_leeching_data'),
            'inviter' => new UserResource($this->whenLoaded('inviter')),
            'valid_medals' => MedalResource::collection($this->whenLoaded('valid_medals')),
        ];
//        if ($request->routeIs('user.me')) {
//            $out['downloaded_human'] = mksize($this->downloaded);
//            $out['uploaded_human'] = mksize($this->uploaded);
//            $out['seed_time'] = mkprettytime($this->seedtime);
//            $out['leech_time'] = mkprettytime($this->leechtime);
//            $out['share_ratio'] = get_share_ratio($this->uploaded, $this->downloaded);
//            $out['comments_count'] = $this->comments_count;
//            $out['posts_count'] = $this->posts_count;
//            $out['torrents_count'] = $this->torrents_count;
//            $out['seeding_torrents_count'] = $this->seeding_torrents_count;
//            $out['leeching_torrents_count'] = $this->leeching_torrents_count;
//            $out['completed_torrents_count'] = $this->completed_torrents_count;
//            $out['incomplete_torrents_count'] = $this->incomplete_torrents_count;
//        }
//        if ($request->routeIs("oauth.user_info")) {
//            $out['name'] = $this->username;
//        }
//
//        if (nexus()->isPlatformAdmin() && $request->routeIs('users.show')) {
//            $out['two_step_secret'] = $this->two_step_secret;
//        }

        return $out;
    }
}

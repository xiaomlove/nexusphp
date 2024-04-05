<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TopicResource extends JsonResource
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
            'subject' => $this->subject,
            'locked' => $this->locked,
            'forumid' => $this->forumid,
            'sticky' => $this->sticky,
            'hlcolor' => $this->hlcolor,
            'views' => $this->views,
            'user' => new UserResource($this->whenLoaded('user')),
            'lastPost' => new PostResource($this->whenLoaded('lastPost')),
            'firstPost' => new PostResource($this->whenLoaded('firstPost')),
        ];
    }
}

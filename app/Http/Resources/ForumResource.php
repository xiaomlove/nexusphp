<?php

namespace App\Http\Resources;

use App\Models\Forum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Forum
 */
class ForumResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'postcount' => $this->postcount,
            'topiccount' => $this->topiccount,
            'moderators' => UserResource::collection($this->whenLoaded('moderators')),
        ];
    }
}

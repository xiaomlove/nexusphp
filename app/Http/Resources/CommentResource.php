<?php

namespace App\Http\Resources;

use Carbon\CarbonInterface;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Comment
 */
class CommentResource extends JsonResource
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
            'text' => bbcode_attach_to_img($this->text),
            'updated_at' => format_datetime($this->editdate),
            'created_at' => format_datetime($this->added),
            'create_user' => new UserResource($this->whenLoaded('create_user')),
            'update_user' => $this->when($this->editedby > 0, new UserResource($this->whenLoaded('update_user'))),
        ];
    }
}

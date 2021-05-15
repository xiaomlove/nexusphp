<?php

namespace App\Http\Resources;

use Carbon\CarbonInterface;
use Illuminate\Http\Resources\Json\JsonResource;

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
        $descriptionArr = format_description($this->text);
        return [
            'id' => $this->id,
            'description' => $descriptionArr,
            'images' => get_image_from_description($descriptionArr),
            'updated_at_human' => format_datetime($this->editdate),
            'created_at_human' => $this->added->format('Y-m-d H:i'),
            'create_user' => new UserResource($this->whenLoaded('create_user')),
            'update_user' => new UserResource($this->whenLoaded('update_user')),
        ];
    }
}

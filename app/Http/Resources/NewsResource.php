<?php

namespace App\Http\Resources;

use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin News
 */
class NewsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $descriptionArr = format_description($this->body);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $descriptionArr,
            'images' => get_image_from_description($descriptionArr),
            'added' => format_datetime($this->added, 'Y.m.d'),
            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}

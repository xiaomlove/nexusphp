<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Medal
 */
class MedalResource extends JsonResource
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
            'name' => $this->name,
            'get_type' => $this->get_type,
            'get_type_text' => $this->getTypeText,
            'image_large' => $this->image_large,
            'image_small' => $this->image_small,
            'price' => $this->price,
            'price_human' => number_format($this->price),
            'duration' => $this->duration,
            'description' => $this->description,
            'expire_at' => $this->whenPivotLoaded('user_medals', function () {return $this->pivot->expire_at;}),
            'user_medal_id' => $this->whenPivotLoaded('user_medals', function () {return $this->pivot->id;}),
            'wearing_status' => $this->whenPivotLoaded('user_medals', function () {return $this->pivot->status;}),
            'wearing_status_text' => $this->whenPivotLoaded('user_medals', function () {
                return nexus_trans("medal.wearing_status_text." . $this->pivot->status);
            }),
        ];
    }
}

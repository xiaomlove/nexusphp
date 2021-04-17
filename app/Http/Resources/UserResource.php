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
        return [
            'id' => $this->id,
            'email' => $this->email,
            'username' => $this->username,
            'status' => $this->status,
            'added' => $this->added,
            'class' => $this->class,
            'avatar' => $this->avatar,
            'uploaded' => $this->uploaded,
            'downloaded' => $this->downloaded,
            'seedtime' => $this->seedtime,
            'leechtime' => $this->leechtime,
        ];
    }
}

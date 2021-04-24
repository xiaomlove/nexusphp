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
            'class_text' => $this->class_text,
            'avatar' => $this->avatar,
            'uploaded' => $this->uploaded,
            'uploaded_text' => mksize($this->uploaded),
            'downloaded' => $this->downloaded,
            'downloaded_text' => mksize($this->downloaded),
            'bonus' => $this->seedbonus,
            'seedtime' => $this->seedtime,
            'leechtime' => $this->leechtime,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TorrentOperationLogResource extends JsonResource
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
            'action_type' => $this->action_type,
            'action_type_text' => $this->actionTypeText,
            'uid' => $this->uid,
            'username' => $this->user->username,
            'comment' => $this->comment,
            'created_at' => format_datetime($this->created_at)
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class InviteResource extends JsonResource
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
            'inviter' => $this->inviter,
            'invitee' => $this->invitee,
            'hash' => $this->hash,
            'time_invited' => $this->time_invited,
            'valid' => $this->valid,
            'valid_text' => $this->valid_text,
            'invitee_register_uid' => $this->invitee_register_uid,
            'invitee_register_email' => $this->invitee_register_email,
            'invitee_register_username' => $this->invitee_register_username,
            'inviter_user' => new UserResource($this->whenLoaded('inviter_user')),
            'invitee_user' => new UserResource($this->whenLoaded('invitee_user')),
        ];
    }
}

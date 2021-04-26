<?php

namespace App\Http\Resources;

use App\Models\Exam;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamUserResource extends JsonResource
{
    public $preserveKeys = true;

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
            'status' => $this->status,
            'status_text' => $this->statusText,
            'created_at' => formatDatetime($this->created_at),
            'progress' => $this->when($this->progress, $this->progress),
            'begin' => formatDatetime($this->begin),
            'end' => formatDatetime($this->end),
            'user' => new UserResource($this->whenLoaded('user')),
            'exam' => new ExamResource($this->whenLoaded('exam')),
        ];
    }


}

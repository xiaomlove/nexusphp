<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PollResource extends JsonResource
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
        $out = [
            'id' => $this->id,
            'added' => format_datetime($this->added),
            'question' => $this->question,
            'answers_count' => $this->answers_count,
            'options' => $this->options,
        ];

        return $out;
    }
}

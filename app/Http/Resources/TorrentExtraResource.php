<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TorrentExtra
 */
class TorrentExtraResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'descr' => $this->descr,
            'media_info' => $this->media_info,
            'media_info_summary' => $this->media_info_summary,
            'nfo' => $this->nfo,
        ];
    }
}

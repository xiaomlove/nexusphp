<?php

namespace App\Http\Resources;

use App\Models\Exam;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamResource extends JsonResource
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
            'description' => $this->description,
            'begin' => $this->begin,
            'end' => $this->end,
            'duration' => $this->duration ?: '',
            'duration_text' => $this->duration_text,
            'filters' => $this->normalizeFilters($this->resource),
            'filters_formatted' => $this->filterFormatted,
            'indexes' => $this->indexes,
            'indexes_formatted' => $this->indexFormatted,
            'status' => $this->status,
            'status_text' => $this->statusText,
            'is_discovered' => $this->is_discovered,
            'is_discovered_text' => $this->is_discovered_text,
            'priority' => $this->priority,
        ];
    }

    private function normalizeFilters(Exam $exam)
    {
        $filters = $exam->filters;
        foreach (Exam::$filters as $key => $value) {
            if (!isset($filters->$key)) {
                $filters->$key = [];
            }
        }
        return $filters;
    }

}

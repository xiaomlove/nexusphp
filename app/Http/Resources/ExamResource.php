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
            'filters' => $this->filters,
            'filters_formatted' => $this->formatFilters($this->resource),
            'indexes' => $this->indexes,
            'indexes_formatted' => $this->formatIndexes($this->resource),
            'status' => $this->status,
            'status_text' => $this->statusText,
            'is_discovered' => $this->is_discovered,
            'is_discovered_text' => $this->is_discovered_text,
        ];
    }

    private function formatFilters(Exam $exam)
    {
        $currentFilters = $exam->filters;
        $arr = [];
        $filter = Exam::FILTER_USER_CLASS;
        if (!empty($currentFilters->{$filter})) {
            $classes = collect(User::$classes)->only($currentFilters->{$filter});
            $arr[] = sprintf('%s: %s', Exam::$filters[$filter]['name'], $classes->pluck('text')->implode(', '));
        }

        $filter = Exam::FILTER_USER_REGISTER_TIME_RANGE;
        if (!empty($currentFilters->{$filter})) {
            $range = $currentFilters->{$filter};
            $arr[] = sprintf(
                "%s: \n%s ~ %s",
                Exam::$filters[$filter]['name'],
                $range[0] ? Carbon::parse($range[0])->toDateTimeString() : '-',
                $range[1] ? Carbon::parse($range[1])->toDateTimeString() : '-'
            );
        }

        return implode("\n", $arr);
    }

    private function formatIndexes(Exam $exam)
    {
        $indexes = $exam->indexes;
        $arr = [];
        foreach ($indexes as $index) {
            if (isset($index['checked']) && $index['checked']) {
                $arr[] = sprintf(
                    '%s: %s %s',
                    Exam::$indexes[$index['index']]['name'] ?? '',
                    $index['require_value'],
                    Exam::$indexes[$index['index']]['unit'] ?? ''
                );
            }
        }
        return implode("\n", $arr);
    }
}

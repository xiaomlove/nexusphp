<?php

namespace App\Http\Resources;

use App\Models\SearchBox;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SearchBox
 */
class SearchBoxResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var SearchBox $resource */
        $searchBox = $this->resource;
        $out = [
            'id' => $this->id,
            'name' => $this->name,
            'display_name' => $this->displaySectionName,
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
        if ($searchBox->showsubcat) {
            $subCategories = [];
            $fields = array_keys(SearchBox::$taxonomies);
            foreach ($fields as $field) {
                $relationName = "taxonomy_$field";
                if ($searchBox->relationLoaded($relationName)) {
                    $subCategories[] = [
                        'field' => $field,
                        'label' => $searchBox->getTaxonomyLabel($field),
                        'data' => MediaResource::collection($searchBox->{$relationName}),
                    ];
                }
            }
            if (!empty($subCategories)) {
                $out['sub_categories'] = $subCategories;
            }
        }
        return $out;
    }
}

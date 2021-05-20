<?php

namespace App\Repositories;

use App\Models\NexusModel;
use App\Models\SearchBox;
use App\Models\SearchBoxField;

class SearchBoxRepository extends BaseRepository
{
    public function getList(array $params): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = SearchBox::query();
        list($sortField, $sortType) = $this->getSortFieldAndType($params);
        $query->orderBy($sortField, $sortType);
        return $query->paginate();
    }

    public function store(array $params)
    {
        $result = SearchBox::query()->create($params);
        return $result;
    }

    public function update(array $params, $id)
    {
        $result = SearchBox::query()->findOrFail($id);
        $result->update($params);
        return $result;
    }

    public function getDetail($id)
    {
        $result = SearchBox::query()->findOrFail($id);
        return $result;
    }

    public function delete($id)
    {
        $result = SearchBox::query()->findOrFail($id);
        $success = $result->delete();
        return $success;
    }

    public function buildSearchBox($id)
    {
        $searchBox = SearchBox::query()->with(['categories', 'normal_fields'])->findOrFail($id);
        $fieldData = [];
        foreach ($searchBox->normal_fields as $normalField) {
            $fieldType = $normalField->field_type;
            $info = SearchBoxField::$fieldTypes[$fieldType] ?? null;
            if ($info) {
                /** @var NexusModel $model */
                $model = new $info[$fieldType]['model'];
                $fieldData[$fieldType] = $model::query()->get();
            }
        }
        $searchBox->setRelation('normal_fields_data', $fieldData);
        return $searchBox;
    }

    public function initSearchBoxField($id)
    {
        $searchBox = SearchBox::query()->findOrFail($id);
        foreach (SearchBoxField::$fieldTypes as $fieldType => $info) {
            if ($fieldType == SearchBoxField::FIELD_TYPE_CUSTOM) {
                continue;
            }
            $name = str_replace('_', '', "show{$fieldType}");
            $log = "name: $name, fieldType: $fieldType";
            $searchBox->normal_fields()->where('field_type', $fieldType)->delete();
            if ($searchBox->{$name}) {
                $searchBox->normal_fields()->create([
                    'field_type' => $fieldType,
                ]);
                do_log("$log, create.");
            } else {
                do_log("$log, delete.");
            }
        }
    }


}

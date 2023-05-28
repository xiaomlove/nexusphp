<?php

namespace App\Filament\Resources\Section\SectionResource\Pages;

use App\Filament\Resources\Section\SectionResource;
use App\Models\SearchBox;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSection extends EditRecord
{
    protected static string $resource = SectionResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return SearchBox::formatTaxonomyExtra($data);
    }

    protected function afterSave()
    {
        clear_search_box_cache($this->record->id);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (SearchBox::$extras as $field => $text) {
            if (!empty($data['extra'][$field])) {
                $data['other'][] = $field;
            }
            unset($data['extra'][$field]);
        }
        return $data;
    }

}

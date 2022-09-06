<?php

namespace App\Filament\Resources\System\SectionResource\Pages;

use App\Filament\Resources\System\SectionResource;
use App\Models\SearchBox;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSection extends CreateRecord
{
    protected static string $resource = SectionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        foreach (SearchBox::$subCatFields as $field) {
            $data["show{$field}"] = 0;
            foreach ($data['extra'][SearchBox::EXTRA_TAXONOMY_LABELS] ?? [] as $item) {
                if ($field == $item['torrent_field']) {
                    $data["show{$field}"] = 1;
                    $data["extra->" . SearchBox::EXTRA_TAXONOMY_LABELS][] = $item;
                }
            }
        }
        return array_filter($data);
    }
}

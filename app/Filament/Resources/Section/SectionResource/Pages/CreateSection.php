<?php

namespace App\Filament\Resources\Section\SectionResource\Pages;

use App\Filament\Resources\Section\SectionResource;
use App\Models\SearchBox;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSection extends CreateRecord
{
    protected static string $resource = SectionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return SearchBox::formatTaxonomyExtra($data);
    }

}

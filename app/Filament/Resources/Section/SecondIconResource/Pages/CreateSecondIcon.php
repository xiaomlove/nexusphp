<?php

namespace App\Filament\Resources\Section\SecondIconResource\Pages;

use App\Filament\Resources\Section\SecondIconResource;
use App\Models\SearchBox;
use App\Models\SecondIcon;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateSecondIcon extends CreateRecord
{
    protected static string $resource = SecondIconResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return SecondIcon::formatFormData($data);
    }
}

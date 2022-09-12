<?php

namespace App\Filament\Resources\System\CategoryIconResource\Pages;

use App\Filament\Resources\System\CategoryIconResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCategoryIcon extends CreateRecord
{
    protected static string $resource = CategoryIconResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return array_filter($data);
    }

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('index');
    }
}

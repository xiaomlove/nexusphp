<?php

namespace App\Filament\Resources\System\CategoryIconResource\Pages;

use App\Filament\Resources\System\CategoryIconResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCategoryIcon extends EditRecord
{
    protected static string $resource = CategoryIconResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return array_filter($data);
    }

    protected function getRedirectUrl(): string
    {
        return static::$resource::getUrl('index');
    }
}

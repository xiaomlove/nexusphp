<?php

namespace App\Filament\Resources\Section\MediaResource\Pages;

use App\Filament\Resources\Section\MediaResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMedia extends EditRecord
{
    protected static string $resource = MediaResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

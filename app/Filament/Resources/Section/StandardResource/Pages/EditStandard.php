<?php

namespace App\Filament\Resources\Section\StandardResource\Pages;

use App\Filament\Resources\Section\StandardResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStandard extends EditRecord
{
    protected static string $resource = StandardResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

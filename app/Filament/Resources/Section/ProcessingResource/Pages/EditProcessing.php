<?php

namespace App\Filament\Resources\Section\ProcessingResource\Pages;

use App\Filament\Resources\Section\ProcessingResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProcessing extends EditRecord
{
    protected static string $resource = ProcessingResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

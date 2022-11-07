<?php

namespace App\Filament\Resources\Section\ProcessingResource\Pages;

use App\Filament\Resources\Section\ProcessingResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Section\CodecResource\Pages\EditCodec;

class EditProcessing extends EditCodec
{
    protected static string $resource = ProcessingResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

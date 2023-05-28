<?php

namespace App\Filament\Resources\Section\SourceResource\Pages;

use App\Filament\Resources\Section\SourceResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Section\CodecResource\Pages\EditCodec;

class EditSource extends EditCodec
{
    protected static string $resource = SourceResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

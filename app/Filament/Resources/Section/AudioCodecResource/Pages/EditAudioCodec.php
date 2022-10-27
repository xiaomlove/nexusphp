<?php

namespace App\Filament\Resources\Section\AudioCodecResource\Pages;

use App\Filament\Resources\Section\AudioCodecResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAudioCodec extends EditRecord
{
    protected static string $resource = AudioCodecResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

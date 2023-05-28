<?php

namespace App\Filament\Resources\Section\TeamResource\Pages;

use App\Filament\Resources\Section\TeamResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Resources\Section\CodecResource\Pages\EditCodec;

class EditTeam extends EditCodec
{
    protected static string $resource = TeamResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

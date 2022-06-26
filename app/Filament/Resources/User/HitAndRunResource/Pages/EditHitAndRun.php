<?php

namespace App\Filament\Resources\User\HitAndRunResource\Pages;

use App\Filament\Resources\User\HitAndRunResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHitAndRun extends EditRecord
{
    protected static string $resource = HitAndRunResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

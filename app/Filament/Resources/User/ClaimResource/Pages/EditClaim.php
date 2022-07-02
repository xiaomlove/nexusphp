<?php

namespace App\Filament\Resources\User\ClaimResource\Pages;

use App\Filament\Resources\User\ClaimResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditClaim extends EditRecord
{
    protected static string $resource = ClaimResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

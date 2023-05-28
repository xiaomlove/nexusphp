<?php

namespace App\Filament\Resources\User\InviteResource\Pages;

use App\Filament\Resources\User\InviteResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditInvite extends EditRecord
{
    protected static string $resource = InviteResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\System\AgentAllowResource\Pages;

use App\Filament\Resources\System\AgentAllowResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAgentAllow extends EditRecord
{
    protected static string $resource = AgentAllowResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\System\AgentDenyResource\Pages;

use App\Filament\Resources\System\AgentDenyResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAgentDeny extends EditRecord
{
    protected static string $resource = AgentDenyResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function afterSave()
    {
        clear_agent_allow_deny_cache();
    }
}

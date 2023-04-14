<?php

namespace App\Filament\Resources\System\AgentAllowResource\Pages;

use App\Filament\Resources\System\AgentAllowResource;
use App\Models\NexusModel;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAgentAllow extends EditRecord
{
    protected static string $resource = AgentAllowResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make()->using(function ($record) {
                $record->delete();
                clear_agent_allow_deny_cache();
                return redirect(AgentAllowResource::getUrl());
            })
        ];
    }

    public function afterSave()
    {
        clear_agent_allow_deny_cache();
    }
}

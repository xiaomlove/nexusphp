<?php

namespace App\Filament\Resources\System\AgentDenyResource\Pages;

use App\Filament\Resources\System\AgentDenyResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAgentDeny extends CreateRecord
{
    protected static string $resource = AgentDenyResource::class;

    public function afterCreate()
    {
        clear_agent_allow_deny_cache();
    }
}

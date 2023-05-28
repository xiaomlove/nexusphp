<?php

namespace App\Filament\Resources\System\AgentAllowResource\Pages;

use App\Filament\Resources\System\AgentAllowResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateAgentAllow extends CreateRecord
{
    protected static string $resource = AgentAllowResource::class;

    public function afterCreate()
    {
        clear_agent_allow_deny_cache();
    }
}

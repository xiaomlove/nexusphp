<?php

namespace App\Filament\Resources\System\AgentAllowResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\System\AgentAllowResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAgentAllows extends PageList
{
    protected static string $resource = AgentAllowResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

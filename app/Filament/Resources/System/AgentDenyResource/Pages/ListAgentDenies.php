<?php

namespace App\Filament\Resources\System\AgentDenyResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\System\AgentDenyResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAgentDenies extends PageList
{
    protected static string $resource = AgentDenyResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

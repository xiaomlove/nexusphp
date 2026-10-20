<?php

namespace App\Filament\Resources\ResourceExchangeRules\Pages;

use App\Filament\Resources\ResourceExchangeRules\ResourceExchangeRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListResourceExchangeRules extends ListRecords
{
    protected static string $resource = ResourceExchangeRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

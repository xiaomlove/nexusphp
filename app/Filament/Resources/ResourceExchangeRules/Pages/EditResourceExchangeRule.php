<?php

namespace App\Filament\Resources\ResourceExchangeRules\Pages;

use App\Filament\Resources\ResourceExchangeRules\ResourceExchangeRuleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditResourceExchangeRule extends EditRecord
{
    protected static string $resource = ResourceExchangeRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

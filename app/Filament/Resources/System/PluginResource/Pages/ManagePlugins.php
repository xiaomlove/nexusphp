<?php

namespace App\Filament\Resources\System\PluginResource\Pages;

use App\Filament\Resources\System\PluginResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePlugins extends ManageRecords
{
    protected static string $resource = PluginResource::class;

    protected ?string $maxContentWidth = 'full';

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

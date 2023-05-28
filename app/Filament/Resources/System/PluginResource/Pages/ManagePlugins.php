<?php

namespace App\Filament\Resources\System\PluginResource\Pages;

use App\Filament\PageListSingle;
use App\Filament\Resources\System\PluginResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePlugins extends PageListSingle
{
    protected static string $resource = PluginResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

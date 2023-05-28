<?php

namespace App\Filament\Resources\Section\IconResource\Pages;

use App\Filament\Resources\Section\IconResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateIcon extends CreateRecord
{
    protected static string $view = 'filament.resources.system.category-icon-resource.pages.create-record';

    protected static string $resource = IconResource::class;

    protected function getViewData(): array
    {
        return [
            'desc' => nexus_trans('label.icon.desc')
        ];
    }

    public function afterCreate()
    {
        clear_icon_cache();
    }
}

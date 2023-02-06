<?php

namespace App\Filament\Resources\Section\IconResource\Pages;

use App\Filament\EditRedirectIndexTrait;
use App\Filament\Resources\Section\IconResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditIcon extends EditRecord
{
    use EditRedirectIndexTrait;

    protected static string $resource = IconResource::class;

    protected static string $view = 'filament.resources.system.category-icon-resource.pages.edit-record';

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getViewData(): array
    {
        return [
            'desc' => nexus_trans('label.icon.desc')
        ];
    }

    public function afterSave()
    {
        clear_icon_cache();
    }
}

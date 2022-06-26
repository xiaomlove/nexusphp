<?php

namespace App\Filament\Resources\System\SettingResource\Pages;

use App\Filament\Resources\System\SettingResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSetting extends EditRecord
{
    protected static string $resource = SettingResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $arr = json_decode($data['value'], true);
        if (is_array($arr)) {
            throw new \LogicException("Not support edit this !");
        }
        return $data;
    }
}

<?php

namespace App\Filament\Resources\Torrent\TagResource\Pages;

use App\Filament\Resources\Torrent\TagResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTag extends EditRecord
{
    protected static string $resource = TagResource::class;

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
        if ($data['mode'] === null) {
            $data['mode'] = 0;
        }
        return $data;
    }

}

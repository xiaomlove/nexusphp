<?php

namespace App\Filament\Resources\Torrent\TagResource\Pages;

use App\Filament\Resources\Torrent\TagResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTag extends CreateRecord
{
    protected static string $resource = TagResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($data['mode'] === null) {
            $data['mode'] = 0;
        }
        return $data;
    }
}

<?php

namespace App\Filament\Resources\Section\SecondIconResource\Pages;

use App\Filament\EditRedirectIndexTrait;
use App\Filament\Resources\Section\SecondIconResource;
use App\Models\SearchBox;
use App\Models\SecondIcon;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSecondIcon extends EditRecord
{
    use EditRedirectIndexTrait;

    protected static string $resource = SecondIconResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return SecondIcon::formatFormData($data);
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $mode = $data['mode'];
        foreach (SearchBox::$taxonomies as $torrentField => $taxonomyTableModel) {
            $taxonomyValue = $data[$torrentField] ?? null;
            unset($data[$torrentField]);
            $data[$torrentField][$mode] = $taxonomyValue;
        }
        return $data;
    }
}

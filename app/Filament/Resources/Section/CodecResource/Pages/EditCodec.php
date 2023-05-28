<?php

namespace App\Filament\Resources\Section\CodecResource\Pages;

use App\Filament\EditRedirectIndexTrait;
use App\Filament\Resources\Section\CodecResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCodec extends EditRecord
{
    use EditRedirectIndexTrait;

    protected static string $resource = CodecResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    public function afterSave()
    {
        clear_search_box_cache();
        $model = static::$resource::getModel();
        $table = (new $model)->getTable();
        clear_taxonomy_cache($table);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['mode'] === null) {
            $data['mode'] = 0;
        }
        return $data;
    }

}

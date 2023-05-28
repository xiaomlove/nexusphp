<?php

namespace App\Filament\Resources\Section\CodecResource\Pages;

use App\Filament\CreateRedirectIndexTrait;
use App\Filament\Resources\Section\CodecResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCodec extends CreateRecord
{
    use CreateRedirectIndexTrait;

    protected static string $resource = CodecResource::class;

    public function afterCreate()
    {
        clear_search_box_cache();
        $model = static::$resource::getModel();
        $table = (new $model)->getTable();
        clear_taxonomy_cache($table);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($data['mode'] === null) {
            $data['mode'] = 0;
        }
        return $data;
    }
}

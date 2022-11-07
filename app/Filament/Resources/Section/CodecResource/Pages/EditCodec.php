<?php

namespace App\Filament\Resources\Section\CodecResource\Pages;

use App\Filament\RedirectIndexTrait;
use App\Filament\Resources\Section\CodecResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCodec extends EditRecord
{
    use RedirectIndexTrait;

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
    }

}

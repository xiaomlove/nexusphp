<?php

namespace App\Filament\Resources\System\ExamResource\Pages;

use App\Filament\Resources\System\ExamResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditExam extends EditRecord
{
    protected static string $resource = ExamResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

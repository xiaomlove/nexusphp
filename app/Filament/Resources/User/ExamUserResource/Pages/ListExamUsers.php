<?php

namespace App\Filament\Resources\User\ExamUserResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\User\ExamUserResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListExamUsers extends PageList
{
    protected static string $resource = ExamUserResource::class;

    protected function getActions(): array
    {
        return [
//            Actions\CreateAction::make(),
        ];
    }
}

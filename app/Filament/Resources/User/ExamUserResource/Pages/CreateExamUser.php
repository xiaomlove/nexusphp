<?php

namespace App\Filament\Resources\User\ExamUserResource\Pages;

use App\Filament\Resources\User\ExamUserResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateExamUser extends CreateRecord
{
    protected static string $resource = ExamUserResource::class;
}

<?php

namespace App\Filament\Resources\User\HitAndRunResource\Pages;

use App\Filament\Resources\User\HitAndRunResource;
use App\Models\HitAndRun;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Forms;

class ViewHitAndRun extends ViewRecord
{
    protected static string $resource = HitAndRunResource::class;

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('id'),
            Forms\Components\TextInput::make('uid'),
            Forms\Components\Radio::make('status')->options(HitAndRun::listStatus(true))->inline(),
            Forms\Components\Textarea::make('comment'),
            Forms\Components\DateTimePicker::make('created_at'),
        ];
    }

}

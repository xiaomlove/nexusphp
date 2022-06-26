<?php

namespace App\Filament\Resources\User\UserResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\User\UserResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;

class ListUsers extends PageList
{
    protected static string $resource = UserResource::class;

    protected function getActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

//    public function isTableSearchable(): bool
//    {
//        return true;
//    }




}

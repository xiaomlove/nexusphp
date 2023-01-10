<?php

namespace App\Filament\Resources\User\UserMetaResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\User\UserMetaResource;
use App\Models\UserMeta;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListUserMetas extends PageList
{
    protected static string $resource = UserMetaResource::class;

    protected function getActions(): array
    {
        return [
//            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return UserMeta::query()->whereIn('meta_key', array_keys(UserMeta::$metaKeys));
    }


}

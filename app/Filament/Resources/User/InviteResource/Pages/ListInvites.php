<?php

namespace App\Filament\Resources\User\InviteResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\User\InviteResource;
use App\Models\Invite;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListInvites extends PageList
{
    protected static string $resource = InviteResource::class;

    protected function getActions(): array
    {
        return [
//            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return Invite::query()->with(['inviter_user']);
    }
}

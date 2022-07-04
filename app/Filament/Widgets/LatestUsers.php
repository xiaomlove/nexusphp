<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Closure;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestUsers extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getTableHeading(): string | Closure | null
    {
        return __('dashboard.latest_user.page_title');
    }

    protected function isTablePaginationEnabled(): bool
    {
        return false;
    }

    protected function getTableQuery(): Builder
    {
        return User::query()->orderBy('id', 'desc')->limit(5);
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('username')->label(__('label.user.username')),
            Tables\Columns\TextColumn::make('email')->label(__('label.email')),
            Tables\Columns\BadgeColumn::make('status')->colors(['success' => 'confirmed', 'danger' => 'pending'])->label(__('label.status')),
            Tables\Columns\TextColumn::make('added')->dateTime()->label(__('label.added')),
        ];
    }
}

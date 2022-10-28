<?php

namespace App\Filament;

use Closure;
use Filament\Resources\Pages\ManageRecords;
use Filament\Tables\Filters\Layout;
use Illuminate\Database\Eloquent\Model;

class PageListSingle extends ManageRecords
{
    protected ?string $maxContentWidth = 'full';

    protected function getTableFiltersLayout(): ?string
    {
        return Layout::AboveContent;
    }

    protected function getTableRecordUrlUsing(): ?Closure
    {
        return function (Model $record): ?string {
            return null;
        };
    }
}

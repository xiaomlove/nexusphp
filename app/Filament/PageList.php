<?php

namespace App\Filament;

use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Filters\Layout;
use Illuminate\Database\Eloquent\Model;

class PageList extends ListRecords
{
    protected ?string $maxContentWidth = 'full';

    protected function getTableRecordUrlUsing(): ?\Closure
    {
        return function (Model $record): ?string {
            return null;
        };
    }

    protected function getTableFiltersLayout(): ?string
    {
        return Layout::AboveContent;
    }
}

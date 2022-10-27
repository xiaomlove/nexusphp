<?php

namespace App\Filament\Resources\Section;

use App\Filament\Resources\Section\ProcessingResource\Pages;
use App\Filament\Resources\Section\ProcessingResource\RelationManagers;
use App\Models\Processing;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProcessingResource extends CodecResource
{
    protected static ?string $model = Processing::class;

    protected static ?int $navigationSort = 9;

    public static function form(Form $form): Form
    {
        return parent::form($form);
    }

    public static function table(Table $table): Table
    {
        return parent::table($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProcessings::route('/'),
            'create' => Pages\CreateProcessing::route('/create'),
            'edit' => Pages\EditProcessing::route('/{record}/edit'),
        ];
    }
}

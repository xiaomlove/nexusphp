<?php

namespace App\Filament\Resources\Section;

use App\Filament\Resources\Section\StandardResource\Pages;
use App\Filament\Resources\Section\StandardResource\RelationManagers;
use App\Models\Standard;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StandardResource extends CodecResource
{
    protected static ?string $model = Standard::class;

    protected static ?int $navigationSort = 5;

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
            'index' => Pages\ListStandards::route('/'),
            'create' => Pages\CreateStandard::route('/create'),
            'edit' => Pages\EditStandard::route('/{record}/edit'),
        ];
    }
}

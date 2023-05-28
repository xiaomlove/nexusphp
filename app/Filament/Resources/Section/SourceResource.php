<?php

namespace App\Filament\Resources\Section;

use App\Filament\Resources\Section\SourceResource\Pages;
use App\Filament\Resources\Section\SourceResource\RelationManagers;
use App\Models\Source;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SourceResource extends CodecResource
{
    protected static ?string $model = Source::class;

    protected static ?int $navigationSort = 7;

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
            'index' => Pages\ListSources::route('/'),
            'create' => Pages\CreateSource::route('/create'),
            'edit' => Pages\EditSource::route('/{record}/edit'),
        ];
    }
}

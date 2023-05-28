<?php

namespace App\Filament\Resources\Section;

use App\Filament\Resources\Section\AudioCodecResource\Pages;
use App\Filament\Resources\Section\AudioCodecResource\RelationManagers;
use App\Models\AudioCodec;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AudioCodecResource extends CodecResource
{
    protected static ?string $model = AudioCodec::class;

    protected static ?int $navigationSort = 4;

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
            'index' => Pages\ListAudioCodecs::route('/'),
            'create' => Pages\CreateAudioCodec::route('/create'),
            'edit' => Pages\EditAudioCodec::route('/{record}/edit'),
        ];
    }
}

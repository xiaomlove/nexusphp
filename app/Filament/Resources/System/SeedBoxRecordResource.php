<?php

namespace App\Filament\Resources\System;

use App\Filament\Resources\System\SeedBoxRecordResource\Pages;
use App\Filament\Resources\System\SeedBoxRecordResource\RelationManagers;
use App\Models\SeedBoxRecord;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SeedBoxRecordResource extends Resource
{
    protected static ?string $model = SeedBoxRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 98;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.seedbox_records');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('operator')->label(__('label.seedbox_record.operator')),
                Forms\Components\TextInput::make('bandwidth')->label(__('label.seedbox_record.bandwidth'))->integer(),
                Forms\Components\TextInput::make('ip_begin')->label(__('label.seedbox_record.ip_begin')),
                Forms\Components\TextInput::make('ip_end')->label(__('label.seedbox_record.ip_end')),
                Forms\Components\TextInput::make('ip')->label(__('label.seedbox_record.ip'))->helperText(__('label.seedbox_record.ip_help')),
                Forms\Components\Textarea::make('comment')->label(__('label.comment')),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('typeText')->label(__('label.seedbox_record.type')),
                Tables\Columns\TextColumn::make('user.username')->label(__('label.username')),
                Tables\Columns\TextColumn::make('operation')->label(__('label.seedbox_record.operator')),
                Tables\Columns\TextColumn::make('bandwidth')->label(__('label.seedbox_record.bandwidth')),
                Tables\Columns\TextColumn::make('ip')->label(__('label.seedbox_record.ip'))->formatStateUsing(fn ($record) => $record->ip ?: sprintf('%s ~ %s', $record->ip_begin, $record->ip_end)),
                Tables\Columns\TextColumn::make('comment')->label(__('label.comment')),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
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
            'index' => Pages\ListSeedBoxRecords::route('/'),
            'create' => Pages\CreateSeedBoxRecord::route('/create'),
            'edit' => Pages\EditSeedBoxRecord::route('/{record}/edit'),
        ];
    }
}

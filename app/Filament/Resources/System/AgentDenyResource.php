<?php

namespace App\Filament\Resources\System;

use App\Filament\Resources\System\AgentDenyResource\Pages;
use App\Filament\Resources\System\AgentDenyResource\RelationManagers;
use App\Models\AgentDeny;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AgentDenyResource extends Resource
{
    protected static ?string $model = AgentDeny::class;

    protected static ?string $navigationIcon = 'heroicon-o-ban';

    protected static ?string $navigationGroup = 'System';

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.agent_denies');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('family_id')->label('Allow family')
                    ->relationship('family', 'family')->required(),
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\TextInput::make('peer_id')->required(),
                Forms\Components\TextInput::make('agent')->required(),
                Forms\Components\Textarea::make('comment'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('family.family')->label('Family'),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('peer_id')->searchable(),
                Tables\Columns\TextColumn::make('agent')->searchable(),
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
            'index' => Pages\ListAgentDenies::route('/'),
            'create' => Pages\CreateAgentDeny::route('/create'),
            'edit' => Pages\EditAgentDeny::route('/{record}/edit'),
        ];
    }
}

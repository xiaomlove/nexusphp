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

    protected static ?int $navigationSort = 4;

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
                    ->relationship('family', 'family')->required()->label(__('label.agent_allow.family')),
                Forms\Components\TextInput::make('name')->required()->label(__('label.name')),
                Forms\Components\TextInput::make('peer_id')->required()->label(__('label.agent_deny.peer_id')),
                Forms\Components\TextInput::make('agent')->required()->label(__('label.agent_deny.agent')),
                Forms\Components\Textarea::make('comment')->label(__('label.comment')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('family.family')->label(__('label.agent_allow.family')),
                Tables\Columns\TextColumn::make('name')->searchable()->label(__('label.name')),
                Tables\Columns\TextColumn::make('peer_id')->searchable()->label(__('label.agent_deny.peer_id')),
                Tables\Columns\TextColumn::make('agent')->searchable()->label(__('label.agent_deny.agent')),
            ])
            ->defaultSort('id', 'desc')
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

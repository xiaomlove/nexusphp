<?php

namespace App\Filament\Resources\System;

use App\Filament\NexusOptionsTrait;
use App\Filament\Resources\System\AgentAllowResource\Pages;
use App\Filament\Resources\System\AgentAllowResource\RelationManagers;
use App\Models\AgentAllow;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AgentAllowResource extends Resource
{
    use NexusOptionsTrait;

    protected static ?string $model = AgentAllow::class;

    protected static ?string $navigationIcon = 'heroicon-o-check';

    protected static ?string $navigationGroup = 'System';

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.agent_allows');
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('family')->required(),
                Forms\Components\TextInput::make('start_name')->required(),
                Forms\Components\TextInput::make('peer_id_start')->required(),
                Forms\Components\TextInput::make('peer_id_pattern')->required(),
                Forms\Components\Radio::make('peer_id_matchtype')->options(self::$matchTypes)->required(),
                Forms\Components\TextInput::make('peer_id_match_num')->integer()->required(),
                Forms\Components\TextInput::make('agent_start')->required(),
                Forms\Components\TextInput::make('agent_pattern')->required(),
                Forms\Components\Radio::make('agent_matchtype')->options(self::$matchTypes)->required(),
                Forms\Components\TextInput::make('agent_match_num')->required(),
                Forms\Components\Radio::make('exception')->options(self::$yesOrNo)->required(),
                Forms\Components\Radio::make('allowhttps')->options(self::$yesOrNo)->required(),

                Forms\Components\Textarea::make('comment'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('family')->searchable(),
                Tables\Columns\TextColumn::make('start_name')->searchable(),
                Tables\Columns\TextColumn::make('peer_id_start'),
                Tables\Columns\TextColumn::make('agent_start'),
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
            RelationManagers\DeniesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAgentAllows::route('/'),
            'create' => Pages\CreateAgentAllow::route('/create'),
            'edit' => Pages\EditAgentAllow::route('/{record}/edit'),
        ];
    }
}

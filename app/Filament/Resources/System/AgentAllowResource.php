<?php

namespace App\Filament\Resources\System;

use App\Filament\OptionsTrait;
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
    use OptionsTrait;

    protected static ?string $model = AgentAllow::class;

    protected static ?string $navigationIcon = 'heroicon-o-check';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 3;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.agent_allows');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('family')->required()->label(__('label.agent_allow.family')),
                Forms\Components\TextInput::make('start_name')->required()->label(__('label.agent_allow.start_name')),
                Forms\Components\TextInput::make('peer_id_start')->required()->label(__('label.agent_allow.peer_id_start')),
                Forms\Components\TextInput::make('peer_id_pattern')->required()->label(__('label.agent_allow.peer_id_pattern')),
                Forms\Components\Radio::make('peer_id_matchtype')->options(self::$matchTypes)->required()->label(__('label.agent_allow.peer_id_matchtype')),
                Forms\Components\TextInput::make('peer_id_match_num')->integer()->required()->label(__('label.agent_allow.peer_id_match_num')),
                Forms\Components\TextInput::make('agent_start')->required()->label(__('label.agent_allow.agent_start')),
                Forms\Components\TextInput::make('agent_pattern')->required()->label(__('label.agent_allow.agent_pattern')),
                Forms\Components\Radio::make('agent_matchtype')->options(self::$matchTypes)->required()->label(__('label.agent_allow.agent_matchtype')),
                Forms\Components\TextInput::make('agent_match_num')->required()->label(__('label.agent_allow.agent_match_num')),
                Forms\Components\Radio::make('exception')->options(self::$yesOrNo)->required()->label(__('label.agent_allow.exception')),
                Forms\Components\Radio::make('allowhttps')->options(self::$yesOrNo)->required()->label(__('label.agent_allow.allowhttps')),

                Forms\Components\Textarea::make('comment')->label(__('label.comment')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('family')->searchable()->label(__('label.agent_allow.family')),
                Tables\Columns\TextColumn::make('start_name')->searchable()->label(__('label.agent_allow.start_name')),
                Tables\Columns\TextColumn::make('peer_id_start')->label(__('label.agent_allow.peer_id_start')),
                Tables\Columns\TextColumn::make('agent_start')->label(__('label.agent_allow.agent_start')),
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

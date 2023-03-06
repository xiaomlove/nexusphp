<?php

namespace App\Filament\Resources\System\AgentAllowResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DeniesRelationManager extends RelationManager
{
    protected static string $relationship = 'denies';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required()->maxLength(255)->label(__('label.name')),
                Forms\Components\TextInput::make('peer_id')->required()->maxLength(255)->label(__('label.agent_deny.peer_id')),
                Forms\Components\TextInput::make('agent')->required()->maxLength(255)->label(__('label.agent_deny.agent')),
                Forms\Components\Textarea::make('comment')->label(__('label.comment')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label(__('label.name')),
                Tables\Columns\TextColumn::make('peer_id')->label(__('label.agent_deny.peer_id')),
                Tables\Columns\TextColumn::make('agent')->label(__('label.agent_deny.agent')),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}

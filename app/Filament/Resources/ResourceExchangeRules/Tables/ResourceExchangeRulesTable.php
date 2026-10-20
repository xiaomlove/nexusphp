<?php

namespace App\Filament\Resources\ResourceExchangeRules\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ResourceExchangeRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('min_size_gb')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('max_size_gb')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('bonus_per_gb_day')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tier1_bonus_per_day')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tier2_bonus_per_day')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tier3_bonus_per_day')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('enabled')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

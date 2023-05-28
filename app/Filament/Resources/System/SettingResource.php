<?php

namespace App\Filament\Resources\System;

use App\Filament\OptionsTrait;
use App\Filament\Resources\System\SettingResource\Pages;
use App\Filament\Resources\System\SettingResource\RelationManagers;
use App\Models\Setting;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SettingResource extends Resource
{
    use OptionsTrait;

    protected static ?string $model = Setting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 100;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.settings');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }


    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required()->disabled()->columnSpan(['sm' => 2]),
                Forms\Components\Textarea::make('value')->required()->columnSpan(['sm' => 2])
                    ->afterStateHydrated(function (Forms\Components\Textarea $component, $state) {
                        $arr = json_decode($state, true);
                        if (is_array($arr)) {
                            $component->disabled();
                        }
                    })
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('value')->limit(),
                Tables\Columns\BadgeColumn::make('autoload')->colors(['success' => 'yes', 'warning' => 'no']),
                Tables\Columns\TextColumn::make('updated_at'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('autoload')->options(self::$yesOrNo),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
//                Tables\Actions\DeleteBulkAction::make(),
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
//            'index' => Pages\ListSettings::route('/'),
//            'create' => Pages\CreateSetting::route('/create'),
            'index' => Pages\EditSetting::route('/'),
        ];
    }
}

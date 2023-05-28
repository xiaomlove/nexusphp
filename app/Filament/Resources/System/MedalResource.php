<?php

namespace App\Filament\Resources\System;

use App\Filament\Resources\System\MedalResource\Pages;
use App\Filament\Resources\System\MedalResource\RelationManagers;
use App\Models\Medal;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class MedalResource extends Resource
{
    protected static ?string $model = Medal::class;

    protected static ?string $navigationIcon = 'heroicon-o-badge-check';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 2;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.medals_list');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required()->label(__('label.name')),
                Forms\Components\TextInput::make('price')->required()->integer()->label(__('label.price')),
                Forms\Components\TextInput::make('image_large')->required()->label(__('label.medal.image_large')),
                Forms\Components\TextInput::make('image_small')->required()->label(__('label.medal.image_small')),
                Forms\Components\Radio::make('get_type')
                    ->options(Medal::listGetTypes(true))
                    ->inline()
                    ->label(__('label.medal.get_type'))
                    ->required()
                ,
                Forms\Components\Toggle::make('display_on_medal_page')
                    ->label(__('label.medal.display_on_medal_page'))
                    ->required()
                ,
                Forms\Components\TextInput::make('duration')
                    ->integer()
                    ->label(__('label.medal.duration'))
                    ->helperText(__('label.medal.duration_help'))
                ,
                Forms\Components\TextInput::make('inventory')
                    ->integer()
                    ->label(__('medal.fields.inventory'))
                    ->helperText(__('medal.fields.inventory_help'))
                ,
                Forms\Components\DateTimePicker::make('sale_begin_time')
                    ->label(__('medal.fields.sale_begin_time'))
                    ->helperText(__('medal.fields.sale_begin_time_help'))
                ,
                Forms\Components\DateTimePicker::make('sale_end_time')
                    ->label(__('medal.fields.sale_end_time'))
                    ->helperText(__('medal.fields.sale_end_time_help'))
                ,
                Forms\Components\TextInput::make('bonus_addition_factor')
                    ->label(__('medal.fields.bonus_addition_factor'))
                    ->helperText(__('medal.fields.bonus_addition_factor_help'))
                    ->numeric()
                    ->default(0)
                ,
                Forms\Components\TextInput::make('gift_fee_factor')
                    ->label(__('medal.fields.gift_fee_factor'))
                    ->helperText(__('medal.fields.gift_fee_factor_help'))
                    ->numeric()
                    ->default(0)
                ,
                Forms\Components\Textarea::make('description')
                    ->label(__('label.description'))
                ,
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name')->label(__('label.name'))->searchable(),
                Tables\Columns\ImageColumn::make('image_large')->height(60)->label(__('label.medal.image_large')),
                Tables\Columns\TextColumn::make('getTypeText')->label('Get type')->label(__('label.medal.get_type')),
                Tables\Columns\IconColumn::make('display_on_medal_page')->label(__('label.medal.display_on_medal_page'))->boolean(),
                Tables\Columns\TextColumn::make('sale_begin_end_time')
                    ->label(__('medal.fields.sale_begin_end_time'))
                    ->formatStateUsing(fn ($record) => new HtmlString(sprintf('%s ~<br/>%s', $record->sale_begin_time ?? nexus_trans('nexus.no_limit'), $record->sale_end_time ?? nexus_trans('nexus.no_limit'))))
                ,
                Tables\Columns\TextColumn::make('bonus_addition_factor')->label(__('medal.fields.bonus_addition_factor')),
                Tables\Columns\TextColumn::make('gift_fee_factor')->label(__('medal.fields.gift_fee_factor')),
                Tables\Columns\TextColumn::make('price')->label(__('label.price'))->formatStateUsing(fn ($state) => number_format($state)),

                Tables\Columns\TextColumn::make('duration')->label(__('label.medal.duration')),

                Tables\Columns\TextColumn::make('inventory')
                    ->label(__('medal.fields.inventory'))
                    ->formatStateUsing(fn ($record) => $record->inventory ?? nexus_trans('label.infinite'))
                ,
                Tables\Columns\TextColumn::make('users_count')->label(__('medal.fields.users_count')),
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
            'index' => Pages\ListMedals::route('/'),
            'create' => Pages\CreateMedal::route('/create'),
            'edit' => Pages\EditMedal::route('/{record}/edit'),
        ];
    }
}

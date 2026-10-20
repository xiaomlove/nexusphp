<?php

namespace App\Filament\Resources\System;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\System\MedalResource\Pages\ListMedals;
use App\Filament\Resources\System\MedalResource\Pages\CreateMedal;
use App\Filament\Resources\System\MedalResource\Pages\EditMedal;
use App\Filament\Resources\System\MedalResource\Pages;
use App\Filament\Resources\System\MedalResource\RelationManagers;
use App\Models\Medal;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class MedalResource extends Resource
{
    protected static ?string $model = Medal::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-check-badge';

    protected static string | \UnitEnum | null $navigationGroup = 'System';

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('admin.sidebar.medals_list');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }


    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->label(__('label.name')),
                TextInput::make('price')->required()->integer()->label(__('label.price')),
                TextInput::make('image_large')->required()->label(__('label.medal.image_large')),
                TextInput::make('image_small')->required()->label(__('label.medal.image_small')),
                TextInput::make('medal_type')
                    ->label('勋章类型')
                    ->required()
                    ->default('通用')
                    ->helperText('同一类型的勋章共享同一加成比例；留空时使用“通用”。'),
                Radio::make('get_type')
                    ->options(Medal::listGetTypes(true))
                    ->inline()
                    ->label(__('label.medal.get_type'))
                    ->required()
                ,
                Toggle::make('display_on_medal_page')
                    ->label(__('label.medal.display_on_medal_page'))
                    ->required()
                ,
                TextInput::make('duration')
                    ->integer()
                    ->label(__('label.medal.duration'))
                    ->helperText(__('label.medal.duration_help'))
                ,
                TextInput::make('inventory')
                    ->integer()
                    ->label(__('medal.fields.inventory'))
                    ->helperText(__('medal.fields.inventory_help'))
                ,
                DateTimePicker::make('sale_begin_time')
                    ->label(__('medal.fields.sale_begin_time'))
                    ->helperText(__('medal.fields.sale_begin_time_help'))
                ,
                DateTimePicker::make('sale_end_time')
                    ->label(__('medal.fields.sale_end_time'))
                    ->helperText(__('medal.fields.sale_end_time_help'))
                ,
                TextInput::make('bonus_addition_factor')
                    ->label(__('medal.fields.bonus_addition_factor'))
                    ->helperText('填写 1-5，分别表示 1%-5% 的魔力加成。')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(5)
                    ->step(1)
                    ->suffix('%')
                    ->required()
                    ->formatStateUsing(fn ($state) => $state === null ? null : (float) $state * 100)
                    ->dehydrateStateUsing(fn ($state) => ((float) $state) / 100)
                    ->default(0.01),
                TextInput::make('bonus_addition_duration')
                    ->label(__('medal.fields.bonus_addition_duration'))
                    ->helperText(__('medal.fields.bonus_addition_duration_help'))
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                ,
                TextInput::make('gift_fee_factor')
                    ->label(__('medal.fields.gift_fee_factor'))
                    ->helperText(__('medal.fields.gift_fee_factor_help'))
                    ->numeric()
                    ->default(0)
                ,
                TextInput::make('priority')
                    ->label(__('label.priority'))
                    ->helperText(__('label.priority_help'))
                    ->numeric()
                    ->default(0)
                ,
                Textarea::make('description')
                    ->label(__('label.description'))
                ,
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('name')->label(__('label.name'))->searchable(),
                TextColumn::make('medal_type')->label('勋章类型')->searchable(),
                ImageColumn::make('image_large')->height(60)->label(__('label.medal.image_large')),
                TextColumn::make('getTypeText')->label('Get type')->label(__('label.medal.get_type')),
                IconColumn::make('display_on_medal_page')->label(__('label.medal.display_on_medal_page'))->boolean(),
                TextColumn::make('sale_begin_end_time')
                    ->label(__('medal.fields.sale_begin_end_time'))
                    ->formatStateUsing(fn ($record) => new HtmlString(sprintf('%s ~<br/>%s', $record->sale_begin_time ?? nexus_trans('nexus.no_limit'), $record->sale_end_time ?? nexus_trans('nexus.no_limit'))))
                ,
                TextColumn::make('bonus_addition_factor')
                    ->label(__('medal.fields.bonus_addition_factor'))
                    ->formatStateUsing(fn ($state) => number_format((float) $state * 100, 0) . '%'),
                TextColumn::make('bonus_addition_duration')->label(__('medal.fields.bonus_addition_duration')),
                TextColumn::make('gift_fee_factor')->label(__('medal.fields.gift_fee_factor')),
                TextColumn::make('price')->label(__('label.price'))->formatStateUsing(fn ($state) => number_format($state)),

                TextColumn::make('duration')->label(__('label.medal.duration')),

                TextColumn::make('inventoryText')
                    ->label(__('medal.fields.inventory'))
                ,
                TextColumn::make('users_count')->label(__('medal.fields.users_count')),
                TextColumn::make('priority')->label(__('label.priority')),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->orderBy("priority", 'desc')->orderBy('id', 'desc'))
            ;
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
            'index' => ListMedals::route('/'),
            'create' => CreateMedal::route('/create'),
            'edit' => EditMedal::route('/{record}/edit'),
        ];
    }
}

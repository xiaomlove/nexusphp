<?php

namespace App\Filament\Resources\Section;

use App\Filament\Resources\Section\CodecResource\Pages;
use App\Filament\Resources\Section\CodecResource\RelationManagers;
use App\Models\Codec;
use App\Models\Icon;
use App\Models\SearchBox;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CodecResource extends Resource
{
    protected static ?string $model = Codec::class;

    protected static ?string $navigationIcon = 'heroicon-o-bookmark';

    protected static ?string $navigationGroup = 'Section';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')->required()->label(__('label.search_box.taxonomy.name'))->required(),
                Forms\Components\TextInput::make('sort_index')
                    ->default(0)
                    ->label(__('label.priority'))
                    ->helperText(__('label.priority_help'))
                ,
                Forms\Components\Select::make('mode')
                    ->options(SearchBox::query()->pluck('name', 'id')->toArray())
                    ->label(__('label.search_box.taxonomy.mode'))
                    ->helperText(__('label.search_box.taxonomy.mode_help'))
                ,
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('search_box.name')
                    ->label(__('label.search_box.label'))
                    ->formatStateUsing(fn ($record) => $record->search_box->name ?? 'All')
                ,
                Tables\Columns\TextColumn::make('name')->label(__('label.search_box.taxonomy.name'))->searchable(),
                Tables\Columns\TextColumn::make('sort_index')->label(__('label.search_box.taxonomy.sort_index'))->sortable(),
            ])
            ->defaultSort('sort_index', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('mode')
                    ->options(SearchBox::query()->pluck('name', 'id')->toArray())
                    ->label(__('label.search_box.taxonomy.mode'))
                    ->query(function (Builder $query, array $data) {
                        return $query->when($data['value'], function (Builder $query, $value) {
                            return $query->where(function (Builder $query) use ($value) {
                                return $query->where('mode', $value)->orWhere('mode', 0);
                            });
                        });
                    })
                ,
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListCodecs::route('/'),
            'create' => Pages\CreateCodec::route('/create'),
            'edit' => Pages\EditCodec::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Torrent;

use App\Filament\Resources\Torrent\TagResource\Pages;
use App\Filament\Resources\Torrent\TagResource\RelationManagers;
use App\Models\SearchBox;
use App\Models\Tag;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Torrent';

    protected static ?int $navigationSort = 2;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.tags_list');
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
                Forms\Components\TextInput::make('color')->required()->label(__('label.tag.color')),
                Forms\Components\TextInput::make('font_color')->required()->label(__('label.tag.font_color')),
                Forms\Components\TextInput::make('font_size')->required()->label(__('label.tag.font_size')),
                Forms\Components\TextInput::make('margin')->required()->label(__('label.tag.margin')),
                Forms\Components\TextInput::make('padding')->required()->label(__('label.tag.padding')),
                Forms\Components\TextInput::make('border_radius')->required()->label(__('label.tag.border_radius')),
                Forms\Components\TextInput::make('priority')->integer()->required()->label(__('label.priority'))->default(0),
                Forms\Components\Select::make('mode')
                    ->options(SearchBox::query()->pluck('name', 'id')->toArray())
                    ->label(__('label.search_box.taxonomy.mode'))
                    ->helperText(__('label.search_box.taxonomy.mode_help'))
                ,
                Forms\Components\Textarea::make('description')->label(__('label.description')),
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
                Tables\Columns\TextColumn::make('name')->label(__('label.name'))->searchable(),
                Tables\Columns\TextColumn::make('color')->label(__('label.tag.color')),
                Tables\Columns\TextColumn::make('font_color')->label(__('label.tag.font_color')),
                Tables\Columns\TextColumn::make('font_size')->label(__('label.tag.font_size')),
                Tables\Columns\TextColumn::make('margin')->label(__('label.tag.margin')),
                Tables\Columns\TextColumn::make('padding')->label(__('label.tag.padding')),
                Tables\Columns\TextColumn::make('border_radius')->label(__('label.tag.border_radius')),
                Tables\Columns\TextColumn::make('priority')->label(__('label.priority'))->sortable(),
                Tables\Columns\TextColumn::make('torrents_count')->label(__('label.tag.torrents_count')),
                Tables\Columns\TextColumn::make('torrents_sum_size')->label(__('label.tag.torrents_sum_size'))->formatStateUsing(fn ($state) => mksize($state)),
//                Tables\Columns\TextColumn::make('updated_at')->dateTime()->label(__('label.updated_at')),
            ])
            ->defaultSort('priority', 'desc')
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
            ->actions(self::getActions())
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
            'index' => Pages\ListTags::route('/'),
            'create' => Pages\CreateTag::route('/create'),
            'edit' => Pages\EditTag::route('/{record}/edit'),
        ];
    }

    private static function getActions(): array
    {
        $actions = [];
        $actions[] = Tables\Actions\Action::make('detach_torrents')
            ->label(__('admin.resources.tag.detach_torrents'))
            ->requiresConfirmation()
            ->action(function ($record) {
                $record->torrent_tags()->delete();
            });
        $actions[] = Tables\Actions\EditAction::make();
        return $actions;
    }
}

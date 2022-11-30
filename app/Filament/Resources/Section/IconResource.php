<?php

namespace App\Filament\Resources\Section;

use App\Filament\OptionsTrait;
use App\Filament\EditRedirectIndexTrait;
use App\Filament\Resources\Section\IconResource\Pages;
use App\Filament\Resources\Section\IconResource\RelationManagers;
use App\Models\Icon;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class IconResource extends Resource
{
    use OptionsTrait;

    protected static ?string $model = Icon::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'Section';

    protected static ?int $navigationSort = 10;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.icon');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label(__('label.name'))
                    ->required()
                ,
                Forms\Components\TextInput::make('folder')
                    ->label(__('label.icon.folder'))
                    ->required()
                    ->helperText(__('label.icon.folder_help'))
                ,
                Forms\Components\Radio::make('multilang')
                    ->label(__('label.icon.multilang'))
                    ->options(self::$yesOrNo)
                    ->required()
                    ->helperText(__('label.icon.multilang_help'))
                ,
                Forms\Components\Radio::make('secondicon')
                    ->label(__('label.icon.secondicon'))
                    ->options(self::$yesOrNo)
                    ->required()
                    ->helperText(__('label.icon.secondicon_help'))
                ,
                Forms\Components\TextInput::make('cssfile')->label(__('label.icon.cssfile'))->helperText(__('label.icon.cssfile_help')),
                Forms\Components\TextInput::make('designer')->label(__('label.icon.designer'))->helperText(__('label.icon.designer_help')),
                Forms\Components\Textarea::make('comment')->label(__('label.icon.comment'))->helperText(__('label.icon.comment_help')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('name')->label(__('label.name')),
                Tables\Columns\TextColumn::make('folder')->label(__('label.icon.folder')),
                Tables\Columns\TextColumn::make('multilang')->label(__('label.icon.multilang')),
                Tables\Columns\TextColumn::make('secondicon')->label(__('label.icon.secondicon')),
                Tables\Columns\TextColumn::make('cssfile')->label(__('label.icon.cssfile')),
                Tables\Columns\TextColumn::make('designer')->label(__('label.icon.designer')),
            ])
            ->filters([
                //
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
            'index' => Pages\ListIcons::route('/'),
            'create' => Pages\CreateIcon::route('/create'),
            'edit' => Pages\EditIcon::route('/{record}/edit'),
        ];
    }
}

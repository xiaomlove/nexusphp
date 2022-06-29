<?php

namespace App\Filament\Resources\User;

use App\Filament\Resources\User\UserResource\Pages;
use App\Filament\Resources\User\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'User';

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.users_list');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('username')->required(),
                Forms\Components\TextInput::make('email')->required(),
                Forms\Components\TextInput::make('password')->password()->required()->visibleOn(Pages\CreateUser::class),
                Forms\Components\TextInput::make('password_confirmation')->password()->required()->same('password')->visibleOn(Pages\CreateUser::class),
                Forms\Components\TextInput::make('id')->integer(),
                Forms\Components\Select::make('class')->options(array_column(User::$classes, 'text')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('username')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('class')->label('Class')
                    ->formatStateUsing(fn(Tables\Columns\Column $column) => $column->getRecord()->classText)
                    ->sortable(),
                Tables\Columns\TextColumn::make('uploaded')->label('Uploaded')
                    ->formatStateUsing(fn(Tables\Columns\Column $column) => $column->getRecord()->uploadedText)
                    ->sortable(),
                Tables\Columns\TextColumn::make('downloaded')->label('Downloaded')
                    ->formatStateUsing(fn(Tables\Columns\Column $column) => $column->getRecord()->downloadedText)
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')->colors(['success' => 'confirmed', 'warning' => 'pending']),
                Tables\Columns\BadgeColumn::make('enabled')->colors(['success' => 'yes', 'danger' => 'no']),
                Tables\Columns\TextColumn::make('added')->sortable()->dateTime('Y-m-d H:i'),
                Tables\Columns\TextColumn::make('last_access')->dateTime('Y-m-d H:i'),
            ])
            ->defaultSort('added', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('class')->options(array_column(User::$classes, 'text')),
                Tables\Filters\SelectFilter::make('status')->options(['confirmed' => 'confirmed', 'pending' => 'pending']),
                Tables\Filters\SelectFilter::make('enabled')->options(['enabled' => 'enabled', 'disabled' => 'disabled']),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
//            'edit' => Pages\EditUser::route('/{record}/edit'),
//            'view' => Pages\ViewUser::route('/{record}'),
            'view' => Pages\UserProfile::route('/{record}'),
        ];
    }

}

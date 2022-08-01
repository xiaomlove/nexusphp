<?php

namespace App\Filament\Resources\User;

use App\Filament\OptionsTrait;
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
    use OptionsTrait;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'User';

    protected static ?int $navigationSort = 1;

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
                Tables\Columns\TextColumn::make('id')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('username')->searchable()->label(__("label.user.username")),
                Tables\Columns\TextColumn::make('email')->searchable()->label(__("label.email")),
                Tables\Columns\TextColumn::make('class')->label('Class')
                    ->formatStateUsing(fn(Tables\Columns\Column $column) => $column->getRecord()->classText)
                    ->sortable()->label(__("label.user.class")),
                Tables\Columns\TextColumn::make('uploaded')->label('Uploaded')
                    ->formatStateUsing(fn(Tables\Columns\Column $column) => $column->getRecord()->uploadedText)
                    ->sortable()->label(__("label.uploaded")),
                Tables\Columns\TextColumn::make('downloaded')->label('Downloaded')
                    ->formatStateUsing(fn(Tables\Columns\Column $column) => $column->getRecord()->downloadedText)
                    ->sortable()->label(__("label.downloaded")),
                Tables\Columns\BadgeColumn::make('status')->colors(['success' => 'confirmed', 'warning' => 'pending'])->label(__("label.user.status")),
                Tables\Columns\BadgeColumn::make('enabled')->colors(['success' => 'yes', 'danger' => 'no'])->label(__("label.user.enabled")),
                Tables\Columns\BadgeColumn::make('downloadpos')->colors(['success' => 'yes', 'danger' => 'no'])->label(__("label.user.downloadpos")),
                Tables\Columns\TextColumn::make('added')->sortable()->dateTime('Y-m-d H:i')->label(__("label.added")),
                Tables\Columns\TextColumn::make('last_access')->dateTime()->label(__("label.last_access")),
            ])
            ->defaultSort('added', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('class')->options(array_column(User::$classes, 'text'))->label(__('label.user.class')),
                Tables\Filters\SelectFilter::make('status')->options(['confirmed' => 'confirmed', 'pending' => 'pending'])->label(__('label.user.status')),
                Tables\Filters\SelectFilter::make('enabled')->options(self::$yesOrNo)->label(__('label.user.enabled')),
                Tables\Filters\SelectFilter::make('downloadpos')->options(self::$yesOrNo)->label(__('label.user.downloadpos')),
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
//            RelationManagers\MedalsRelationManager::class,
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

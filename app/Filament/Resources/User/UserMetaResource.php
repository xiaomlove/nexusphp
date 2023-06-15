<?php

namespace App\Filament\Resources\User;

use App\Filament\Resources\User\UserMetaResource\Pages;
use App\Filament\Resources\User\UserMetaResource\RelationManagers;
use App\Models\NexusModel;
use App\Models\UserMeta;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class UserMetaResource extends Resource
{
    protected static ?string $model = UserMeta::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'User';

    protected static ?int $navigationSort = 8;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.user_props');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('uid')
                    ->searchable()
                    ->label(__('label.username'))
                    ->formatStateUsing(fn ($state) => username_for_admin($state))
                ,
                Tables\Columns\TextColumn::make('meta_key_text')
                    ->label(__('label.name'))
                ,
                Tables\Columns\TextColumn::make('deadline')
                    ->label(__('label.deadline'))
                ,
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('label.created_at'))
                    ->formatStateUsing(fn ($state) => format_datetime($state))
                ,
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\Filter::make('uid')
                    ->form([
                        Forms\Components\TextInput::make('uid')
                            ->label(__('label.username'))
                            ->placeholder('UID')
                        ,
                    ])->query(function (Builder $query, array $data) {
                        return $query->when($data['uid'], fn (Builder $query, $value) => $query->where("uid", $value));
                    })
                ,
                Tables\Filters\SelectFilter::make('meta_key')
                    ->options(UserMeta::listProps())
                    ->label(__('label.name'))
                ,
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()->using(function (NexusModel $record) {
                    $record->delete();
                    clear_user_cache($record->uid);
                    do_log(sprintf("user: %d meta: %s was del by %s", $record->uid, $record->meta_key, Auth::user()->username));
                }),
            ])
            ->bulkActions([
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
            'index' => Pages\ListUserMetas::route('/'),
            'create' => Pages\CreateUserMeta::route('/create'),
            'edit' => Pages\EditUserMeta::route('/{record}/edit'),
        ];
    }
}

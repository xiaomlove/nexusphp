<?php

namespace App\Filament\Resources\User;

use App\Filament\Resources\User\UserMedalResource\Pages;
use App\Filament\Resources\User\UserMedalResource\RelationManagers;
use App\Models\Medal;
use App\Models\NexusModel;
use App\Models\UserMedal;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class UserMedalResource extends Resource
{
    protected static ?string $model = UserMedal::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'User';

    protected static ?int $navigationSort = 5;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.users_medals');
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
                Tables\Columns\TextColumn::make('uid')->searchable(),
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('label.username'))
                    ->searchable()
                    ->formatStateUsing(fn ($record) => new HtmlString(get_username($record->uid, false, true, true, true)))
                ,
                Tables\Columns\TextColumn::make('medal.name')->label(__('label.medal.label'))->searchable(),
                Tables\Columns\ImageColumn::make('medal.image_large')->label(__('label.image')),
                Tables\Columns\TextColumn::make('expire_at')->label(__('label.expire_at'))->dateTime(),
                Tables\Columns\TextColumn::make('wearingStatusText')->label(__('label.status')),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\Filter::make('uid')
                    ->form([
                        Forms\Components\TextInput::make('uid')
                            ->label('UID')
                            ->placeholder('UID')
                        ,
                    ])->query(function (Builder $query, array $data) {
                        return $query->when($data['uid'], fn (Builder $query, $uid) => $query->where("uid", $uid));
                    })
                ,
                Tables\Filters\SelectFilter::make('medal_id')
                    ->options(Medal::query()->pluck('name', 'id')->toArray())
                    ->label(__('medal.label'))
                ,
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()->using(function (NexusModel $record) {
                    $record->delete();
                    clear_user_cache($record->uid);
                })
            ])
            ->bulkActions([

            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'medal']);
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
            'index' => Pages\ListUserMedals::route('/'),
            'create' => Pages\CreateUserMedal::route('/create'),
            'edit' => Pages\EditUserMedal::route('/{record}/edit'),
        ];
    }
}

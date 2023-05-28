<?php

namespace App\Filament\Resources\User;

use App\Filament\Resources\User\ClaimResource\Pages;
use App\Filament\Resources\User\ClaimResource\RelationManagers;
use App\Models\Claim;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class ClaimResource extends Resource
{
    protected static ?string $model = Claim::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'User';

    protected static ?int $navigationSort = 4;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.claims');
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
                    ->label(__('label.user.label'))
                    ->searchable()
                    ->formatStateUsing(fn ($record) => new HtmlString(get_username($record->uid, false, true, true, true)))
                ,
                Tables\Columns\TextColumn::make('torrent.name')->limit(40)->label(__('label.torrent.label'))->searchable(),
                Tables\Columns\TextColumn::make('torrent.size')->label(__('label.torrent.size'))->formatStateUsing(fn (Model $record) => mksize($record->torrent->size)),
                Tables\Columns\TextColumn::make('torrent.added')->label(__('label.torrent.ttl'))->formatStateUsing(fn (Model $record) => mkprettytime($record->torrent->added->diffInSeconds())),
                Tables\Columns\TextColumn::make('created_at')->label(__('label.created_at'))->dateTime(),
                Tables\Columns\TextColumn::make('last_settle_at')->label(__('label.claim.last_settle_at'))->dateTime(),
                Tables\Columns\TextColumn::make('seedTimeThisMonth')->label(__('label.claim.seed_time_this_month')),
                Tables\Columns\TextColumn::make('uploadedThisMonth')->label(__('label.claim.uploaded_this_month')),
                Tables\Columns\BooleanColumn::make('isReachedThisMonth')->label(__('label.claim.is_reached_this_month')),
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
                Tables\Filters\Filter::make('torrent_id')
                    ->form([
                        Forms\Components\TextInput::make('torrent_id')
                            ->label(__('claim.fields.torrent_id'))
                            ->placeholder(__('claim.fields.torrent_id'))
                        ,
                    ])->query(function (Builder $query, array $data) {
                        return $query->when($data['torrent_id'], fn (Builder $query, $value) => $query->where("torrent_id", $value));
                    })
                ,
            ])
            ->actions([
//                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
//                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'torrent', 'snatch']);
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
            'index' => Pages\ListClaims::route('/'),
            'create' => Pages\CreateClaim::route('/create'),
            'edit' => Pages\EditClaim::route('/{record}/edit'),
        ];
    }
}

<?php

namespace App\Filament\Resources\User;

use App\Filament\OptionsTrait;
use App\Filament\Resources\User\InviteResource\Pages;
use App\Filament\Resources\User\InviteResource\RelationManagers;
use App\Models\Invite;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class InviteResource extends Resource
{
    use OptionsTrait;

    protected static ?string $model = Invite::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-add';

    protected static ?string $navigationGroup = 'User';

    protected static ?int $navigationSort = 7;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.invite');
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
                Tables\Columns\TextColumn::make('inviter')
                    ->label(__('invite.fields.inviter'))
                    ->formatStateUsing(fn ($state) => username_for_admin($state))
                ,
                Tables\Columns\TextColumn::make('invitee')
                    ->label(__('invite.fields.invitee'))
                    ->searchable()
                ,
                Tables\Columns\TextColumn::make('hash')
                ,
                Tables\Columns\TextColumn::make('time_invited')
                    ->label(__('invite.fields.time_invited'))
                ,
                Tables\Columns\IconColumn::make('valid')
                    ->label(__('invite.fields.valid'))
                    ->boolean()
                ,
                Tables\Columns\TextColumn::make('invitee_register_uid')
                    ->label(__('invite.fields.invitee_register_uid'))
                    ->searchable()
                ,
                Tables\Columns\TextColumn::make('invitee_register_email')
                    ->label(__('invite.fields.invitee_register_email'))
                    ->searchable()
                ,
                Tables\Columns\TextColumn::make('invitee_register_username')
                    ->label(__('invite.fields.invitee_register_username'))
                    ->searchable()
                ,
                Tables\Columns\TextColumn::make('expired_at')
                    ->label(__('invite.fields.expired_at'))
                    ->formatStateUsing(fn ($state) => format_datetime($state))
                ,
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('label.created_at'))
                    ->formatStateUsing(fn ($state) => format_datetime($state))
                ,
            ])
            ->defaultSort('id', 'desc')
            ->filters(self::getFilters())
            ->actions([
//                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListInvites::route('/'),
            'create' => Pages\CreateInvite::route('/create'),
            'edit' => Pages\EditInvite::route('/{record}/edit'),
        ];
    }

    private static function getFilters()
    {
        $filters = [];
        $filters[] = Tables\Filters\Filter::make('inviter')
            ->form([
                Forms\Components\TextInput::make('inviter')
                    ->label(__('invite.fields.inviter'))
                    ->placeholder('UID')
                ,
            ])->query(function (Builder $query, array $data) {
                return $query->when($data['inviter'], fn (Builder $query, $value) => $query->where("inviter", $value));
            })
        ;
        $filters[] = Tables\Filters\SelectFilter::make('valid')
            ->options(self::getYesNoOptions())
            ->label(__('invite.fields.valid'))
        ;
        $filters[] = Tables\Filters\Filter::make('time_invited_begin')
            ->form([
                Forms\Components\DatePicker::make('time_invited_begin')
                    ->maxDate(now())
                    ->label(__('invite.fields.time_invited_begin'))
                ,
            ])->query(function (Builder $query, array $data) {
                return $query->when($data['time_invited_begin'], fn (Builder $query, $value) => $query->where("time_invited", '>=', $value));
            })
        ;
        $filters[] = Tables\Filters\Filter::make('time_invited_end')
            ->form([
                Forms\Components\DatePicker::make('time_invited_end')
                    ->maxDate(now())
                    ->label(__('invite.fields.time_invited_end'))
                ,
            ])->query(function (Builder $query, array $data) {
                return $query->when($data['time_invited_end'], fn (Builder $query, $value) => $query->where("time_invited", '<=', $value));
            })
        ;
        return $filters;
    }
}

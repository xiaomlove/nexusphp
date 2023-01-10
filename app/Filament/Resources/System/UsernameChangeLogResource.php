<?php

namespace App\Filament\Resources\System;

use App\Filament\Resources\System\UsernameChangeLogResource\Pages;
use App\Filament\Resources\System\UsernameChangeLogResource\RelationManagers;
use App\Models\UsernameChangeLog;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;

class UsernameChangeLogResource extends Resource
{
    protected static ?string $model = UsernameChangeLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-pencil-alt';

    protected static ?string $navigationGroup = 'User';

    protected static ?int $navigationSort = 100;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.username_change_log');
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
                Tables\Columns\TextColumn::make('changeTypeText')->label(__('username-change-log.labels.change_type')),
                Tables\Columns\TextColumn::make('uid')->searchable(),
                Tables\Columns\TextColumn::make('user.username')->searchable()->label(__('label.username')),
                Tables\Columns\TextColumn::make('username_old')->searchable()->label(__('username-change-log.labels.username_old')),
                Tables\Columns\TextColumn::make('username_new')
                    ->searchable()
                    ->label(__('username-change-log.labels.username_new'))
                    ->formatStateUsing(fn ($record) => new HtmlString(get_username($record->uid, false, true, true, true)))
                ,
                Tables\Columns\TextColumn::make('operator')
                    ->searchable()
                    ->label(__('label.operator'))
                ,
                Tables\Columns\TextColumn::make('created_at')->label(__('label.created_at'))->formatStateUsing(fn ($state) => format_datetime($state)),

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
                Tables\Filters\SelectFilter::make('change_type')->options(UsernameChangeLog::listChangeType())->label(__('username-change-log.labels.change_type')),
            ])
            ->actions([
//                Tables\Actions\EditAction::make(),
//                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
//                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageUsernameChangeLogs::route('/'),
        ];
    }
}

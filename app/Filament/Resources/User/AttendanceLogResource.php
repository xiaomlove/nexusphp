<?php

namespace App\Filament\Resources\User;

use App\Filament\OptionsTrait;
use App\Filament\Resources\User\AttendanceLogResource\Pages;
use App\Filament\Resources\User\AttendanceLogResource\RelationManagers;
use App\Models\AttendanceLog;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class AttendanceLogResource extends Resource
{
    use OptionsTrait;

    protected static ?string $model = AttendanceLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-pencil-alt';

    protected static ?string $navigationGroup = 'User';

    protected static ?int $navigationSort = 11;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.attendance_log');
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
                Tables\Columns\TextColumn::make('uid')->formatStateUsing(fn ($state) => username_for_admin($state)),
                Tables\Columns\TextColumn::make('date')->label(__('attendance.fields.date'))->sortable(),
                Tables\Columns\TextColumn::make('points')->label(__('attendance.fields.points')),
                Tables\Columns\IconColumn::make('is_retroactive')
                    ->label(__('attendance.fields.is_retroactive'))
                    ->boolean(true)
                ,
                Tables\Columns\TextColumn::make('created_at')->label(__('label.created_at')),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\Filter::make('id')
                    ->form([
                        Forms\Components\TextInput::make('id')
                            ->placeholder('UID')
                        ,
                    ])->query(function (Builder $query, array $data) {
                        return $query->when($data['id'], fn (Builder $query, $value) => $query->where("uid", $value));
                    })
                ,
                Tables\Filters\SelectFilter::make('is_retroactive')
                    ->options(self::getYesNoOptions())
                    ->label(__('attendance.fields.is_retroactive'))
                ,
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('date')
                            ->maxDate(now())
                            ->label(__('attendance.fields.date'))
                        ,
                    ])->query(function (Builder $query, array $data) {
                        return $query->when($data['date'], fn (Builder $query, $value) => $query->where("date", $value));
                    })
                ,
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_at')
                            ->label(__('label.created_at'))
                        ,
                    ])->query(function (Builder $query, array $data) {
                        return $query->when($data['created_at'], function (Builder $query, $value) {
                            $begin = Carbon::parse($value)->startOfDay();
                            $end = Carbon::parse($value)->endOfDay();
                            return $query->where("created_at", ">=", $begin)->where('created_at', '<=', $end);
                        });
                    })
                ,
            ])
            ->actions([
            ])
            ->bulkActions([
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageAttendanceLogs::route('/'),
        ];
    }
}

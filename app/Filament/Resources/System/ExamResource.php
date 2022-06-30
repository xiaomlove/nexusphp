<?php

namespace App\Filament\Resources\System;

use App\Filament\OptionsTrait;
use App\Filament\Resources\System\ExamResource\Pages;
use App\Filament\Resources\System\ExamResource\RelationManagers;
use App\Models\Exam;
use App\Repositories\UserRepository;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ExamResource extends Resource
{
    use OptionsTrait;

    protected static ?string $model = Exam::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation';

    protected static ?string $navigationGroup = 'System';

    const IS_DISCOVERED_OPTIONS = ['0' => 'No', '1' => 'Yes'];

    const STATUS_OPTIONS = ['0' => 'Enabled', '1' => 'Disabled'];

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.exams_list');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        $userRep = new UserRepository();
        return $form
            ->schema([
                Forms\Components\Section::make('Base info')->schema([
                    Forms\Components\TextInput::make('name')->required()->columnSpan(['sm' => 2])->label(__('label.name')),
                    Forms\Components\TextInput::make('priority')
                        ->columnSpan(['sm' => 2])
                        ->label(__("label.priority"))
                        ->helperText('The higher the value, the higher the priority, and when multiple exam match the same user, the one with the highest priority is assigned.'),
                    Forms\Components\Radio::make('status')
                        ->options(self::STATUS_OPTIONS)
                        ->inline()
                        ->label(__('label.status'))
                        ->columnSpan(['sm' => 2]),
                    Forms\Components\Radio::make('is_discovered')
                        ->options(self::IS_DISCOVERED_OPTIONS)
                        ->label(__('label.exam.is_discovered'))
                        ->inline()
                        ->columnSpan(['sm' => 2]),
                ])->columns(2),

                Forms\Components\Section::make('Time')->schema([
                    Forms\Components\DateTimePicker::make('begin')->label(__('label.begin')),
                    Forms\Components\DateTimePicker::make('end')->label(__('label.begin')),
                    Forms\Components\TextInput::make('duration')
                        ->integer()
                        ->columnSpan(['sm' => 2])
                        ->label(__('label.duration'))
                        ->helperText('Unit: days. When assign to user, begin and end are used if they are specified. Otherwise begin time is the time at assignment, and the end time is the time at assignment plus the duration.'),
                ])->columns(2),

                Forms\Components\Section::make('Select user')->schema([
                    Forms\Components\CheckboxList::make('filters.classes')
                        ->options($userRep->listClass())->columnSpan(['sm' => 2])
                        ->columns(4)
                        ->label(__('label.user.class')),
                    Forms\Components\DateTimePicker::make('filters.register_time_range.0')->label(__("label.exam.register_time_range.begin")),
                    Forms\Components\DateTimePicker::make('filters.register_time_range.1')->label(__("label.exam.register_time_range.end")),
                    Forms\Components\Toggle::make('filters.donate_status')->label(__('label.exam.donated')),
                ])->columns(2),


                Forms\Components\Textarea::make('description')->columnSpan(['sm' => 2]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('name')->searchable()->label(__('label.name')),
                Tables\Columns\TextColumn::make('indexFormatted')->label(__('label.exam.index_formatted'))->html(),
                Tables\Columns\TextColumn::make('begin')->label(__('label.begin')),
                Tables\Columns\TextColumn::make('end')->label(__('label.begin')),
                Tables\Columns\TextColumn::make('durationText')->label(__('label.duration')),
                Tables\Columns\TextColumn::make('filterFormatted')->label(__('label.exam.filter_formatted'))->html(),
                Tables\Columns\BooleanColumn::make('is_discovered')->label(__('label.exam.is_discovered')),
                Tables\Columns\TextColumn::make('priority')->label(__('label.priority')),
                Tables\Columns\TextColumn::make('statusText')->label(__('label.status')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_discovered')->options(self::IS_DISCOVERED_OPTIONS)->label(__("label.exam.is_discovered")),
                Tables\Filters\SelectFilter::make('status')->options(self::STATUS_OPTIONS)->label(__("label.status")),
            ])
            ->actions([
//                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListExams::route('/'),
//            'create' => Pages\CreateExam::route('/create'),
//            'edit' => Pages\EditExam::route('/{record}/edit'),
        ];
    }
}

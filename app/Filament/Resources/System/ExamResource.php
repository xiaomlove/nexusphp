<?php

namespace App\Filament\Resources\System;

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
    protected static ?string $model = Exam::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation';

    protected static ?string $navigationGroup = 'System';

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.exams_list');
    }

    public static function form(Form $form): Form
    {
        $userRep = new UserRepository();
        return $form
            ->schema([
                Forms\Components\Section::make('Base info')->schema([
                    Forms\Components\TextInput::make('name')->required()->columnSpan(['sm' => 2]),
                    Forms\Components\TextInput::make('priority')->columnSpan(['sm' => 2])->helperText('The higher the value, the higher the priority, and when multiple exam match the same user, the one with the highest priority is assigned.'),
                    Forms\Components\Radio::make('status')->options(['0' => 'Enabled', '1' => 'Disabled'])->inline()->columnSpan(['sm' => 2]),
                    Forms\Components\Radio::make('is_discovered')->options(['0' => 'No', '1' => 'Yes'])->label('Discovered')->inline()->columnSpan(['sm' => 2]),
                ])->columns(2),

                Forms\Components\Section::make('Time')->schema([
                    Forms\Components\DateTimePicker::make('begin'),
                    Forms\Components\DateTimePicker::make('end'),
                    Forms\Components\TextInput::make('duration')->integer()->columnSpan(['sm' => 2])
                        ->helperText('Unit: days. When assign to user, begin and end are used if they are specified. Otherwise begin time is the time at assignment, and the end time is the time at assignment plus the duration.'),
                ])->columns(2),

                Forms\Components\Section::make('Select user')->schema([
                    Forms\Components\CheckboxList::make('filters.classes')->options($userRep->listClass())->columnSpan(['sm' => 2])->columns(4)->label('Classes'),
                    Forms\Components\DateTimePicker::make('filters.register_time_range.0')->label('Register time begin'),
                    Forms\Components\DateTimePicker::make('filters.register_time_range.1')->label('Register time end'),
                    Forms\Components\Toggle::make('filters.donate_status')->label('Donated'),
                ])->columns(2),


                Forms\Components\Textarea::make('description')->columnSpan(['sm' => 2]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('indexFormatted')->label('Indexes')->html(),
                Tables\Columns\TextColumn::make('begin'),
                Tables\Columns\TextColumn::make('end'),
                Tables\Columns\TextColumn::make('durationText')->label('Duration'),
                Tables\Columns\TextColumn::make('filterFormatted')->label('Target users')->html(),
                Tables\Columns\BooleanColumn::make('is_discovered')->label('Discovered'),
                Tables\Columns\TextColumn::make('priority')->label('Priority'),
                Tables\Columns\TextColumn::make('statusText')->label('Status'),
            ])
            ->filters([
                //
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

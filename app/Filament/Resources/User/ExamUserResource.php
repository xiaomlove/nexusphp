<?php

namespace App\Filament\Resources\User;

use App\Filament\Resources\User\ExamUserResource\Pages;
use App\Filament\Resources\User\ExamUserResource\RelationManagers;
use App\Models\ExamUser;
use App\Repositories\ExamRepository;
use App\Repositories\HitAndRunRepository;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ExamUserResource extends Resource
{
    protected static ?string $model = ExamUser::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'User';

    protected static ?int $navigationSort = 2;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.exam_users');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('uid')->searchable(),
                Tables\Columns\TextColumn::make('user.username')->label(__('label.username'))->searchable(),
                Tables\Columns\TextColumn::make('exam.name')->label(__('label.exam.label')),
                Tables\Columns\TextColumn::make('begin')->label(__('label.begin'))->dateTime(),
                Tables\Columns\TextColumn::make('end')->label(__('label.end'))->dateTime(),
                Tables\Columns\BooleanColumn::make('is_done')->label(__('label.exam_user.is_done')),
                Tables\Columns\TextColumn::make('statusText')->label(__('label.status')),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->label(__('label.created_at')),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(ExamUser::listStatus(true))->label(__("label.status")),
                Tables\Filters\SelectFilter::make('is_done')->options(['0' => 'No', '1' => 'yes'])->label(__('label.exam.is_done')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->prependBulkActions([
                Tables\Actions\BulkAction::make('Avoid')->action(function (Collection $records) {
                    $idArr = $records->pluck('id')->toArray();
                    $rep = new ExamRepository();
                    $rep->avoidExamUserBulk(['id' => $idArr], Auth::user());
                })
                ->deselectRecordsAfterCompletion()
                ->label(__('admin.resources.exam_user.bulk_action_avoid_label'))
                ->icon('heroicon-o-x')
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'exam']);
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
            'index' => Pages\ListExamUsers::route('/'),
//            'create' => Pages\CreateExamUser::route('/create'),
//            'edit' => Pages\EditExamUser::route('/{record}/edit'),
            'view' => Pages\ViewExamUser::route('/{record}'),
        ];
    }
}

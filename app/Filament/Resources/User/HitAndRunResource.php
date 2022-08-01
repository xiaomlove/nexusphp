<?php

namespace App\Filament\Resources\User;

use App\Filament\Resources\User\HitAndRunResource\Pages;
use App\Filament\Resources\User\HitAndRunResource\RelationManagers;
use App\Models\HitAndRun;
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

class HitAndRunResource extends Resource
{
    protected static ?string $model = HitAndRun::class;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?string $navigationGroup = 'User';

    protected static ?int $navigationSort = 3;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.hit_and_runs');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('uid')->searchable(),
                Tables\Columns\TextColumn::make('user.username')->searchable()->label(__('label.username')),
                Tables\Columns\TextColumn::make('torrent.name')->limit(30)->label(__('label.torrent.label')),
                Tables\Columns\TextColumn::make('snatch.uploadText')->label(__('label.uploaded')),
                Tables\Columns\TextColumn::make('snatch.downloadText')->label(__('label.downloaded')),
                Tables\Columns\TextColumn::make('snatch.shareRatio')->label(__('label.ratio')),
                Tables\Columns\TextColumn::make('seedTimeRequired')->label(__('label.seed_time_required')),
                Tables\Columns\TextColumn::make('inspectTimeLeft')->label(__('label.inspect_time_left')),
                Tables\Columns\TextColumn::make('statusText')->label(__('label.status')),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(HitAndRun::listStatus(true))->label(__('label.status')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->prependBulkActions([
                Tables\Actions\BulkAction::make('Pardon')->action(function (Collection $records) {
                    $idArr = $records->pluck('id')->toArray();
                    $rep = new HitAndRunRepository();
                    $rep->bulkPardon(['id' => $idArr], Auth::user());
                })
                ->deselectRecordsAfterCompletion()
                ->label(__('admin.resources.hit_and_run.bulk_action_pardon'))
                    ->icon('heroicon-o-x')
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
            'index' => Pages\ListHitAndRuns::route('/'),
//            'create' => Pages\CreateHitAndRun::route('/create'),
//            'edit' => Pages\EditHitAndRun::route('/{record}/edit'),
            'view' => Pages\ViewHitAndRun::route('/{record}'),
        ];
    }
}

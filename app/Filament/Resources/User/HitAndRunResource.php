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

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.hit_and_runs');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema(Forms\Components\Card::make()->schema([
//                Forms\Components\Select::make('user')->relationship('user', 'username')->required(),
//                Forms\Components\Select::make('torrent_id')->relationship('torrent', 'name')->required(),
                Forms\Components\Radio::make('status')->options(HitAndRun::listStatus(true))->inline()->required(),
//                Forms\Components\Select::make('snatch_id')->relationship('snatch', 'uploaded'),
                Forms\Components\Textarea::make('comment'),
                Forms\Components\DateTimePicker::make('created_at')->displayFormat('Y-m-d H:i:s'),
            ]));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('user.username')->searchable(),
                Tables\Columns\TextColumn::make('torrent.name')->limit(50),
                Tables\Columns\TextColumn::make('snatch.uploadText')->label('Uploaded'),
                Tables\Columns\TextColumn::make('snatch.downloadText')->label('Downloaded'),
                Tables\Columns\TextColumn::make('snatch.shareRatio')->label('Ratio'),
                Tables\Columns\TextColumn::make('seedTimeRequired'),
                Tables\Columns\TextColumn::make('inspectTimeLeft'),
                Tables\Columns\TextColumn::make('statusText')->label('Status'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->options(HitAndRun::listStatus(true)),
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
            'index' => Pages\ListHitAndRuns::route('/'),
//            'create' => Pages\CreateHitAndRun::route('/create'),
//            'edit' => Pages\EditHitAndRun::route('/{record}/edit'),
            'view' => Pages\ViewHitAndRun::route('/{record}'),
        ];
    }
}

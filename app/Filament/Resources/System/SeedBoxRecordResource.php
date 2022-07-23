<?php

namespace App\Filament\Resources\System;

use App\Filament\Resources\System\SeedBoxRecordResource\Pages;
use App\Filament\Resources\System\SeedBoxRecordResource\RelationManagers;
use App\Models\SeedBoxRecord;
use App\Repositories\SeedBoxRepository;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use phpDocumentor\Reflection\DocBlock\Tags\See;

class SeedBoxRecordResource extends Resource
{
    protected static ?string $model = SeedBoxRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 98;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.seed_box_records');
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('operator')->label(__('label.seed_box_record.operator')),
                Forms\Components\TextInput::make('bandwidth')->label(__('label.seed_box_record.bandwidth'))->integer(),
                Forms\Components\TextInput::make('ip_begin')->label(__('label.seed_box_record.ip_begin')),
                Forms\Components\TextInput::make('ip_end')->label(__('label.seed_box_record.ip_end')),
                Forms\Components\TextInput::make('ip')->label(__('label.seed_box_record.ip'))->helperText(__('label.seed_box_record.ip_help')),
                Forms\Components\Textarea::make('comment')->label(__('label.comment')),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('typeText')->label(__('label.seed_box_record.type')),
                Tables\Columns\TextColumn::make('user.username')->label(__('label.username'))->searchable(),
                Tables\Columns\TextColumn::make('operator')->label(__('label.seed_box_record.operator'))->searchable(),
                Tables\Columns\TextColumn::make('bandwidth')->label(__('label.seed_box_record.bandwidth')),
                Tables\Columns\TextColumn::make('ip')
                    ->label(__('label.seed_box_record.ip'))
                    ->searchable()
                    ->formatStateUsing(fn ($record) => $record->ip ?: sprintf('%s ~ %s', $record->ip_begin, $record->ip_end)),
                Tables\Columns\TextColumn::make('comment')->label(__('label.comment')),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => SeedBoxRecord::STATUS_ALLOWED,
                        'warning' => SeedBoxRecord::STATUS_UNAUDITED,
                        'danger' => SeedBoxRecord::STATUS_DENIED,
                    ])
                    ->formatStateUsing(fn ($record) => $record->statusText)
                    ->label(__('label.seed_box_record.status')),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')->options(SeedBoxRecord::listTypes('text'))->label(__('label.seed_box_record.type')),
                Tables\Filters\SelectFilter::make('status')->options(SeedBoxRecord::listStatus('text'))->label(__('label.seed_box_record.status')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('audit')
                    ->label(__('admin.resources.seed_box_record.toggle_status'))
                    ->form([
                        Forms\Components\Radio::make('status')->options(SeedBoxRecord::listStatus('text'))
                            ->inline()->label(__('label.seed_box_record.status'))->required()
                    ])
                    ->action(function (SeedBoxRecord $record, array $data) {
                        $rep = new SeedBoxRepository();
                        try {
                            $rep->updateStatus($record, $data['status']);
                        } catch (\Exception $exception) {
                            Filament::notify('danger', class_basename($exception));
                        }
                    })
                ,
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
            'index' => Pages\ListSeedBoxRecords::route('/'),
            'create' => Pages\CreateSeedBoxRecord::route('/create'),
            'edit' => Pages\EditSeedBoxRecord::route('/{record}/edit'),
        ];
    }
}

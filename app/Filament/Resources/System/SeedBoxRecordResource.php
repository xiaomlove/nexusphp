<?php

namespace App\Filament\Resources\System;

use App\Filament\OptionsTrait;
use App\Filament\Resources\System\SeedBoxRecordResource\Pages;
use App\Filament\Resources\System\SeedBoxRecordResource\RelationManagers;
use App\Models\NexusModel;
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
use PhpIP\IP;

class SeedBoxRecordResource extends Resource
{
    use OptionsTrait;

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
                Forms\Components\Toggle::make('is_allowed')
                    ->label(__('label.seed_box_record.is_allowed'))
                    ->helperText(__('label.seed_box_record.is_allowed_help'))
                ,
                Forms\Components\Textarea::make('comment')->label(__('label.comment')),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('typeText')->label(__('label.seed_box_record.type')),
                Tables\Columns\TextColumn::make('uid')->searchable(),
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('label.username'))
                    ->searchable()
                    ->formatStateUsing(fn ($record) => username_for_admin($record->uid))
                ,
                Tables\Columns\TextColumn::make('operator')->label(__('label.seed_box_record.operator'))->searchable(),
                Tables\Columns\TextColumn::make('bandwidth')->label(__('label.seed_box_record.bandwidth')),
                Tables\Columns\TextColumn::make('ip')
                    ->label(__('label.seed_box_record.ip'))
                    ->searchable(true, function (Builder $query, $search) {
                        try {
                            $ip = IP::create($search);
                            $ipNumeric = $ip->numeric();
                            return $query->orWhere(function (Builder $query) use ($ipNumeric) {
                                return $query->where('ip_begin_numeric', '<=', $ipNumeric)->where('ip_end_numeric', '>=', $ipNumeric);
                            });
                        } catch (\Exception $exception) {
                            do_log("Invalid IP: $search, error: " . $exception->getMessage());
                        }
                    })
                    ->formatStateUsing(fn ($record) => $record->ip ?: sprintf('%s ~ %s', $record->ip_begin, $record->ip_end)),
                Tables\Columns\TextColumn::make('comment')->label(__('label.comment'))->searchable(),
                Tables\Columns\IconColumn::make('is_allowed')->boolean()->label(__('label.seed_box_record.is_allowed')),
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
                Tables\Filters\SelectFilter::make('type')->options(SeedBoxRecord::listTypes('text'))->label(__('label.seed_box_record.type')),
                Tables\Filters\SelectFilter::make('is_allowed')->options(self::getYesNoOptions())->label(__('label.seed_box_record.is_allowed')),
                Tables\Filters\SelectFilter::make('status')->options(SeedBoxRecord::listStatus('text'))->label(__('label.seed_box_record.status')),

            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('audit')
                    ->label(__('admin.resources.seed_box_record.toggle_status'))
                    ->mountUsing(fn (Forms\ComponentContainer $form, NexusModel $record) => $form->fill([
                        'status' => $record->status,
                    ]))
                    ->form([
                        Forms\Components\Radio::make('status')
                            ->options(SeedBoxRecord::listStatus('text'))
                            ->inline()
                            ->label(__('label.seed_box_record.status'))
                            ->required()
                        ,
                        Forms\Components\TextInput::make('reason')
                            ->label(__('label.reason'))
                        ,
                    ])
                    ->action(function (SeedBoxRecord $record, array $data) {
                        $rep = new SeedBoxRepository();
                        try {
                            $rep->updateStatus($record, $data['status'], $data['reason']);
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

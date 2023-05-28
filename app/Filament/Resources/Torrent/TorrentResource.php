<?php

namespace App\Filament\Resources\Torrent;

use App\Filament\OptionsTrait;
use App\Filament\Resources\Torrent\TorrentResource\Pages;
use App\Filament\Resources\Torrent\TorrentResource\RelationManagers;
use App\Models\Category;
use App\Models\SearchBox;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\Torrent;
use App\Models\TorrentTag;
use App\Models\User;
use App\Repositories\TagRepository;
use App\Repositories\TorrentRepository;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Resources\Form;
use Filament\Resources\Resource;
use Filament\Resources\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Nexus\Database\NexusDB;

class TorrentResource extends Resource
{
    use OptionsTrait;

    protected static ?string $model = Torrent::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Torrent';

    protected static ?int $navigationSort = 1;

    private static ?TorrentRepository $rep;

    protected static function getNavigationLabel(): string
    {
        return __('admin.sidebar.torrent_list');
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

    public static function getRep(): ?TorrentRepository
    {
        if (self::$rep === null) {
            self::$rep = new TorrentRepository();
        }
        return self::$rep;
    }

    public static function table(Table $table): Table
    {
        $showApproval = self::shouldShowApproval();
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('basic_category.name')->label(__('label.torrent.category')),
                Tables\Columns\TextColumn::make('name')->formatStateUsing(fn ($record) => torrent_name_for_admin($record, true))
                    ->label(__('label.name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('posStateText')->label(__('label.torrent.pos_state')),
                Tables\Columns\TextColumn::make('spStateText')->label(__('label.torrent.sp_state')),
                Tables\Columns\TextColumn::make('pickInfoText')
                    ->label(__('label.torrent.picktype'))
                    ->formatStateUsing(fn ($record) => $record->pickInfo['text'])
                ,
                Tables\Columns\IconColumn::make('hr')
                    ->label(__('label.torrent.hr'))
                    ->boolean()
                ,
                Tables\Columns\TextColumn::make('size')
                    ->label(__('label.torrent.size'))
                    ->formatStateUsing(fn ($state) => mksize($state))
                    ->sortable()
                ,
                Tables\Columns\TextColumn::make('seeders')->label(__('label.torrent.seeders'))->sortable(),
                Tables\Columns\TextColumn::make('leechers')->label(__('label.torrent.leechers'))->sortable(),
                Tables\Columns\BadgeColumn::make('approval_status')
                    ->visible($showApproval)
                    ->label(__('label.torrent.approval_status'))
                    ->colors(array_flip(Torrent::listApprovalStatus(true, 'badge_color')))
                    ->formatStateUsing(fn ($record) => $record->approvalStatusText),
                Tables\Columns\TextColumn::make('added')->label(__('label.added'))->dateTime(),
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('label.torrent.owner'))
                    ->formatStateUsing(fn ($record) => username_for_admin($record->owner))
                ,
            ])
            ->defaultSort('id', 'desc')
            ->filters(self::getFilters())
            ->actions(self::getActions())
            ->bulkActions(self::getBulkActions());

    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user', 'basic_category', 'tags']);
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
            'index' => Pages\ListTorrents::route('/'),
            'create' => Pages\CreateTorrent::route('/create'),
            'edit' => Pages\EditTorrent::route('/{record}/edit'),
        ];
    }

    private static function getBulkActions(): array
    {
        $user = Auth::user();
        $actions = [];
        if (user_can('torrentsticky')) {
            $actions[] = Tables\Actions\BulkAction::make('posState')
                ->label(__('admin.resources.torrent.bulk_action_pos_state'))
                ->form([
                    Forms\Components\Select::make('pos_state')
                        ->label(__('label.torrent.pos_state'))
                        ->options(Torrent::listPosStates(true))
                        ->required()
                    ,
                    Forms\Components\DateTimePicker::make('pos_state_until')
                        ->label(__('label.deadline'))
                    ,
                ])
                ->icon('heroicon-o-arrow-circle-up')
                ->action(function (Collection $records, array $data) {
                    $idArr = $records->pluck('id')->toArray();
                    try {
                        $torrentRep = new TorrentRepository();
                        $torrentRep->setPosState($idArr, $data['pos_state'], $data['pos_state_until']);
                    } catch (\Exception $exception) {
                        do_log($exception->getMessage() . $exception->getTraceAsString(), 'error');
                        Filament::notify('danger', class_basename($exception));
                    }
                })
                ->deselectRecordsAfterCompletion();
        }

        if (user_can('torrentonpromotion')) {
            $actions[] = Tables\Actions\BulkAction::make('sp_state')
                ->label(__('admin.resources.torrent.bulk_action_sp_state'))
                ->form([
                    Forms\Components\Select::make('sp_state')
                        ->label(__('label.torrent.sp_state'))
                        ->options(Torrent::listPromotionTypes(true))
                        ->required()
                    ,
                    Forms\Components\Select::make('promotion_time_type')
                        ->label(__('label.torrent.promotion_time_type'))
                        ->options(Torrent::listPromotionTimeTypes(true))
                        ->required()
                    ,
                    Forms\Components\DateTimePicker::make('promotion_until')
                        ->label(__('label.deadline'))
                    ,
                ])
                ->icon('heroicon-o-speakerphone')
                ->action(function (Collection $records, array $data) {
                    $idArr = $records->pluck('id')->toArray();
                    try {
                        $torrentRep = new TorrentRepository();
                        $torrentRep->setSpState($idArr, $data['sp_state'], $data['promotion_time_type'], $data['promotion_until']);
                    } catch (\Exception $exception) {
                        do_log($exception->getMessage() . $exception->getTraceAsString(), 'error');
                        Filament::notify('danger', $exception->getMessage());
                    }
                })
                ->deselectRecordsAfterCompletion();
        }

        if (user_can('torrentmanage') && ($user->picker == 'yes' || $user->class >= User::CLASS_SYSOP)) {
            $actions[] = Tables\Actions\BulkAction::make('recommend')
                ->label(__('admin.resources.torrent.bulk_action_recommend'))
                ->form([
                    Forms\Components\Radio::make('picktype')
                        ->label(__('admin.resources.torrent.bulk_action_recommend'))
                        ->inline()
                        ->options(Torrent::listPickInfo(true))
                        ->required(),

                ])
                ->icon('heroicon-o-fire')
                ->action(function (Collection $records, array $data) {
                    if (empty($data['picktype'])) {
                        return;
                    }
                    $idArr = $records->pluck('id')->toArray();
                    try {
                        $torrentRep = new TorrentRepository();
                        $torrentRep->setPickType($idArr, $data['picktype']);
                    } catch (\Exception $exception) {
                        do_log($exception->getMessage() . $exception->getTraceAsString(), 'error');
                        Filament::notify('danger', class_basename($exception));
                    }
                })
                ->deselectRecordsAfterCompletion();
        }

        if (user_can('torrentmanage')) {
            $actions[] = Tables\Actions\BulkAction::make('remove_tag')
                ->label(__('admin.resources.torrent.bulk_action_remove_tag'))
                ->requiresConfirmation()
                ->icon('heroicon-o-minus-circle')
                ->action(function (Collection $records) {
                    $idArr = $records->pluck('id')->toArray();
                    try {
                        $torrentRep = new TorrentRepository();
                        $torrentRep->syncTags($idArr);
                    } catch (\Exception $exception) {
                        do_log($exception->getMessage() . $exception->getTraceAsString(), 'error');
                        Filament::notify('danger', class_basename($exception));
                    }
                })
                ->deselectRecordsAfterCompletion();

            $actions[] = Tables\Actions\BulkAction::make('attach_tag')
                ->label(__('admin.resources.torrent.bulk_action_attach_tag'))
                ->form([
                    Forms\Components\Checkbox::make('remove')->label(__('admin.resources.torrent.bulk_action_attach_tag_remove_old')),
                    Forms\Components\CheckboxList::make('tags')
                        ->label(__('label.tag.label'))
                        ->columns(4)
                        ->options(TagRepository::createBasicQuery()->pluck('name', 'id')->toArray())
                        ->required(),

                ])
                ->icon('heroicon-o-tag')
                ->action(function (Collection $records, array $data) {
                    if (empty($data['tags'])) {
                        return;
                    }
                    $idArr = $records->pluck('id')->toArray();
                    try {
                        $torrentRep = new TorrentRepository();
                        $torrentRep->syncTags($idArr, $data['tags'], $data['remove'] ?? false);
                    } catch (\Exception $exception) {
                        do_log($exception->getMessage() . $exception->getTraceAsString(), 'error');
                        Filament::notify('danger', class_basename($exception));
                    }
                })
                ->deselectRecordsAfterCompletion();

            $actions[] = Tables\Actions\BulkAction::make('hr')
                ->label(__('admin.resources.torrent.bulk_action_hr'))
                ->form([
                    Forms\Components\Radio::make('hr')
                        ->label(__('admin.resources.torrent.bulk_action_hr'))
                        ->inline()
                        ->options(self::getYesNoOptions())
                        ->required(),

                ])
                ->icon('heroicon-o-sparkles')
                ->action(function (Collection $records, array $data) {
                    if (empty($data['hr'])) {
                        return;
                    }
                    $idArr = $records->pluck('id')->toArray();
                    try {
                        $torrentRep = new TorrentRepository();
                        $torrentRep->setHr($idArr, $data['hr']);
                    } catch (\Exception $exception) {
                        do_log($exception->getMessage() . $exception->getTraceAsString(), 'error');
                        Filament::notify('danger', class_basename($exception));
                    }
                })
                ->deselectRecordsAfterCompletion();
        }

        if (user_can('torrent-delete')) {
            $actions[] = Tables\Actions\DeleteBulkAction::make('bulk-delete')->using(function (Collection $records) {
                deletetorrent($records->pluck('id')->toArray());
            });
        }

        return $actions;
    }

    private static function getActions(): array
    {
        $actions = [];
        if (self::shouldShowApproval() && user_can('torrent-approval')) {
            $actions[] = Tables\Actions\Action::make('approval')
                ->label(__('admin.resources.torrent.action_approval'))
                ->form([
                    Forms\Components\Radio::make('approval_status')
                        ->label(__('label.torrent.approval_status'))
                        ->inline()
                        ->required()
                        ->options(Torrent::listApprovalStatus(true))
                    ,
                    Forms\Components\Textarea::make('comment')->label(__('label.comment')),
                ])
                ->action(function (Torrent $record, array $data) {
                    $torrentRep = new TorrentRepository();
                    try {
                        $data['torrent_id'] = $record->id;
                        $torrentRep->approval(Auth::user(), $data);
                    } catch (\Exception $exception) {
                        do_log($exception->getMessage(), 'error');
                    }
                });

        }
        if (user_can('torrent-delete')) {
            $actions[] = Tables\Actions\DeleteAction::make('delete')->using(function ($record) {
                deletetorrent($record->id);
            });
        }
        return $actions;
    }

    private static function shouldShowApproval(): bool
    {
        return false;
//        return Setting::get('torrent.approval_status_none_visible') == 'no' || Setting::get('torrent.approval_status_icon_enabled') == 'yes';
    }

    private static function getFilters()
    {
        $filters = [
            Tables\Filters\Filter::make('owner')
                ->form([
                    Forms\Components\TextInput::make('owner')
                        ->label(__('label.torrent.owner'))
                        ->placeholder('UID')
                    ,
                ])->query(function (Builder $query, array $data) {
                    return $query->when($data['owner'], fn (Builder $query, $owner) => $query->where("owner", $owner));
                })
            ,

            Tables\Filters\SelectFilter::make('visible')
                ->options(self::$yesOrNo)
                ->label(__('label.torrent.visible'))
            ,
            Tables\Filters\SelectFilter::make('hr')
                ->options(self::getYesNoOptions())
                ->label(__('label.torrent.hr'))
            ,

            Tables\Filters\SelectFilter::make('pos_state')
                ->options(Torrent::listPosStates(true))
                ->label(__('label.torrent.pos_state'))
                ->multiple()
            ,

            Tables\Filters\SelectFilter::make('sp_state')
                ->options(Torrent::listPromotionTypes(true))
                ->label(__('label.torrent.sp_state'))
                ->multiple()
            ,

            Tables\Filters\SelectFilter::make('picktype')
                ->options(Torrent::listPickInfo(true))
                ->label(__('label.torrent.picktype'))
                ->multiple()
            ,

            Tables\Filters\SelectFilter::make('approval_status')
                ->options(Torrent::listApprovalStatus(true))
                ->label(__('label.torrent.approval_status'))
                ->multiple()
            ,

            Tables\Filters\SelectFilter::make('tags')
                ->relationship('tags', 'name')
                ->label(__('label.tag.label'))
                ->multiple()
            ,
            Tables\Filters\SelectFilter::make('category')
                ->options(Category::query()->pluck('name', 'id')->toArray())
                ->label(__('label.torrent.category'))
                ->multiple()
            ,
        ];
        foreach (SearchBox::$taxonomies as $torrentField => $tableModel) {
            $filters[] = Tables\Filters\SelectFilter::make($torrentField)
                ->options(NexusDB::table($tableModel['table'])->orderBy('sort_index')->orderBy('id')->pluck('name', 'id'))
                ->multiple()
            ;
        }

        $filters[] = Tables\Filters\Filter::make('added_begin')
            ->form([
                Forms\Components\DatePicker::make('added_begin')
                    ->maxDate(now())
                    ->label(__('label.torrent.added_begin'))
                ,
            ])->query(function (Builder $query, array $data) {
                return $query->when($data['added_begin'], fn (Builder $query, $value) => $query->where("added", '>=', $value));
            })
        ;
        $filters[] = Tables\Filters\Filter::make('added_end')
            ->form([
                Forms\Components\DatePicker::make('added_end')
                    ->maxDate(now())
                    ->label(__('label.torrent.added_end'))
                ,
            ])->query(function (Builder $query, array $data) {
                return $query->when($data['added_end'], fn (Builder $query, $value) => $query->where("added", '<=', $value));
            })
        ;
        $filters[] = Tables\Filters\Filter::make('size_begin')
            ->form([
                Forms\Components\TextInput::make('size_begin')
                    ->numeric()
                    ->placeholder('GB')
                    ->label(__('label.torrent.size_begin'))
                ,
            ])->query(function (Builder $query, array $data) {
                return $query->when($data['size_begin'], fn (Builder $query, $value) => $query->where("size", '>=', $value * 1024 * 1024 * 1024));
            })
        ;
        $filters[] = Tables\Filters\Filter::make('size_end')
            ->form([
                Forms\Components\TextInput::make('size_end')
                    ->numeric()
                    ->placeholder('GB')
                    ->label(__('label.torrent.size_end'))
                ,
            ])->query(function (Builder $query, array $data) {
                return $query->when($data['size_end'], fn (Builder $query, $value) => $query->where("size", '<=', $value * 1024 * 1024 * 1024));
            })
        ;


        return $filters;

    }

}

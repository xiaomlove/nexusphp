<?php

namespace App\Filament\Resources\Torrent;

use App\Filament\OptionsTrait;
use App\Filament\Resources\Torrent\TorrentResource\Pages;
use App\Filament\Resources\Torrent\TorrentResource\RelationManagers;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\Torrent;
use App\Models\TorrentTag;
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

class TorrentResource extends Resource
{
    use OptionsTrait;

    protected static ?string $model = Torrent::class;

    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static ?string $navigationGroup = 'Torrent';

    protected static ?int $navigationSort = 1;

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

    public static function table(Table $table): Table
    {
        $showApproval = self::shouldShowApproval();
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('basic_category.name')->label(__('label.torrent.category')),
                Tables\Columns\TextColumn::make('name')->formatStateUsing(function (Torrent $record) {
                    $name = sprintf(
                        '<div class="text-primary-600 transition hover:underline hover:text-primary-500 focus:underline focus:text-primary-500"><a href="/details.php?id=%s" target="_blank">%s</a></div>',
                        $record->id, Str::limit($record->name, 40)
                    );
                    $tags = sprintf('&nbsp;<div>%s</div>', $record->tagsFormatted);

                    return new HtmlString('<div class="flex">' . $name . $tags . '</div>');
                })->label(__('label.name'))->searchable(),
                Tables\Columns\TextColumn::make('posStateText')->label(__('label.torrent.pos_state')),
                Tables\Columns\TextColumn::make('spStateText')->label(__('label.torrent.sp_state')),
                Tables\Columns\TextColumn::make('size')->label(__('label.torrent.size'))->formatStateUsing(fn ($state) => mksize($state)),
                Tables\Columns\TextColumn::make('seeders')->label(__('label.torrent.seeders')),
                Tables\Columns\TextColumn::make('leechers')->label(__('label.torrent.leechers')),
//                Tables\Columns\TextColumn::make('times_completed')->label(__('label.torrent.times_completed')),
                Tables\Columns\BadgeColumn::make('approval_status')
                    ->visible($showApproval)
                    ->label(__('label.torrent.approval_status'))
                    ->colors(array_flip(Torrent::listApprovalStatus(true, 'badge_color')))
                    ->formatStateUsing(fn ($record) => $record->approvalStatusText),
                Tables\Columns\TextColumn::make('added')->label(__('label.added'))->dateTime(),
                Tables\Columns\TextColumn::make('user.username')
                    ->label(__('label.torrent.owner'))
                    ->url(fn ($record) => sprintf('/userdetails.php?id=%s', $record->owner))
                    ->openUrlInNewTab(true)
                ,
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('visible')
                    ->options(self::$yesOrNo)
                    ->label(__('label.torrent.visible')),

                Tables\Filters\SelectFilter::make('pos_state')
                    ->options(Torrent::listPosStates(true))
                    ->label(__('label.torrent.pos_state')),

                Tables\Filters\SelectFilter::make('sp_state')
                    ->options(Torrent::listPromotionTypes(true))
                    ->label(__('label.torrent.sp_state')),

                Tables\Filters\SelectFilter::make('approval_status')
                    ->options(Torrent::listApprovalStatus(true))
                    ->visible($showApproval)
                    ->label(__('label.torrent.approval_status')),
            ])
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
        $actions = [];
        $userClass = Auth::user()->class;
        if ($userClass >= Setting::get('authority.torrentsticky')) {
            $actions[] = Tables\Actions\BulkAction::make('posState')
                ->label(__('admin.resources.torrent.bulk_action_pos_state'))
                ->form([
                    Forms\Components\Select::make('pos_state')
                        ->label(__('label.torrent.pos_state'))
                        ->options(Torrent::listPosStates(true))
                        ->required()
                ])
                ->icon('heroicon-o-arrow-circle-up')
                ->action(function (Collection $records, array $data) {
                    $idArr = $records->pluck('id')->toArray();
                    try {
                        $torrentRep = new TorrentRepository();
                        $torrentRep->setPosState($idArr, $data['pos_state']);
                    } catch (\Exception $exception) {
                        Filament::notify('danger', class_basename($exception));
                    }
                })
                ->deselectRecordsAfterCompletion();
        }

        if ($userClass >= Setting::get('authority.torrentmanage')) {
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
                        Filament::notify('danger', class_basename($exception));
                    }
                })
                ->deselectRecordsAfterCompletion();

            $actions[] = Tables\Actions\BulkAction::make('attach_tag')
                ->label(__('admin.resources.torrent.bulk_action_attach_tag'))
                ->form([
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
                        $torrentRep->syncTags($idArr, $data['tags']);
                    } catch (\Exception $exception) {
                        Filament::notify('danger', class_basename($exception));
                    }
                })
                ->deselectRecordsAfterCompletion();
        }

        return $actions;
    }

    private static function getActions(): array
    {
        $actions = [];
        $userClass = Auth::user()->class;
        if (self::shouldShowApproval() && $userClass >= Setting::get('authority.torrentmanage')) {
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
        return $actions;
    }

    private static function shouldShowApproval(): bool
    {
        return Setting::get('torrent.approval_status_none_visible') == 'no' || Setting::get('torrent.approval_status_icon_enabled') == 'yes';
    }

}

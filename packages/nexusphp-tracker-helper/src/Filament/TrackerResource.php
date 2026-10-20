<?php

namespace NexusPlugin\Tracker\Filament;

use App\Filament\OptionsTrait;
use App\Filament\PageListSingle;
use App\Models\NexusModel;
use App\Models\Setting;
use Filament\Facades\Filament;
use NexusPlugin\Tracker\Filament\TrackerResource\Pages;
use App\Models\OauthClient;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use NexusPlugin\Tracker\Models\Tracker;
use NexusPlugin\Tracker\TrackerRepository;

class TrackerResource extends Resource
{
    use OptionsTrait;

//    protected static ?string $navigationIcon = 'heroicon-o-collection';

    protected static string|null|\UnitEnum $navigationGroup = 'Other';

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return "Tracker";
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }


//    public static function form(Form $form): Form
//    {
//        $transPrefix = "tgbot::tgbot";
//        return $form
//            ->schema([
//                Forms\Components\TextInput::make('token'),
//                Forms\Components\Radio::make('enabled')
//                    ->options(self::getYesNoOptions())
//                    ->default(0)
//                    ->inline()
//                    ->label(__('label.enabled')),
//                Forms\Components\Repeater::make("group_chats")
//                    ->label(nexus_trans("$transPrefix.group_chats"))
//                    ->schema([
//                        Forms\Components\TextInput::make('chat_id')
//                            ->label(nexus_trans("$transPrefix.chat_id"))
//                            ->integer()
//                            ->required()
//                        ,
//                        Forms\Components\CheckboxList::make('receive_msg_types')
//                            ->label(nexus_trans("$transPrefix.receive_msg_types"))
//                            ->options(TrackerRepository::listReceiveMsgTypes())
//                        ,
//                    ])->columns(2)
//
//            ])->columns(1);
//    }
//
//    public static function table(Table $table): Table
//    {
//        return $table
//            ->columns([
//                Tables\Columns\TextColumn::make('token'),
//                Tables\Columns\TextColumn::make('webhook_uri'),
//                Tables\Columns\IconColumn::make('enabled')
//                    ->boolean()
//                    ->label(__('label.enabled'))
//                ,
//                Tables\Columns\TextColumn::make('updated_at')->label(__("label.updated_at")),
//
//            ])
//            ->filters([
//                //
//            ])
//            ->actions([
//                Tables\Actions\EditAction::make()->using(function (Tracker $record, array $data) {
//                    try {
//                        $rep = new TrackerRepository();
//                        $rep->update($record, $data);
//                    } catch (\Throwable $throwable) {
//                        do_log(sprintf("message: %s, trace: %s", $throwable->getMessage(), $throwable->getTraceAsString()), "error");
//                        Filament::notify("danger", $throwable->getMessage());
//                    }
//                    return false;
//                }),
////                Tables\Actions\DeleteAction::make(),
//            ])
//            ->bulkActions([
////                Tables\Actions\DeleteBulkAction::make(),
//            ]);
//    }

//    public static function getPages(): array
//    {
//        return [
//            'index' => Pages\TrackerStatus::route('/'),
//        ];
//    }
}

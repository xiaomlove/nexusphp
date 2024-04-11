<?php

namespace App\Filament\Resources\System\SettingResource\Pages;

use App\Filament\OptionsTrait;
use App\Filament\Resources\System\SettingResource;
use App\Models\HitAndRun;
use App\Models\SearchBox;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use App\Repositories\MeiliSearchRepository;
use Filament\Facades\Filament;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Illuminate\Support\Facades\DB;
use Nexus\Database\NexusDB;

class EditSetting extends Page implements Forms\Contracts\HasForms
{
    use InteractsWithForms, OptionsTrait;

    protected static string $resource = SettingResource::class;

    protected static string $view = 'filament.resources.system.setting-resource.pages.edit-hit-and-run';

    protected function getTitle(): string
    {
        return __('label.setting.nav_text');
    }

    public function mount()
    {
        static::authorizeResourceAccess();
        $this->fillForm();
    }

    private function fillForm()
    {
        $settings = Setting::get();
        $this->form->fill($settings);
    }

    protected function getFormSchema(): array
    {
        return [
            Forms\Components\Tabs::make('Heading')
                ->tabs($this->getTabs())
        ];
    }

    public function submit()
    {
        static::authorizeResourceAccess();

        $formData = $this->form->getState();
        $notAutoloadNames = ['donation_custom'];
        $data = [];
        foreach ($formData as $prefix => $parts) {
            foreach ($parts as $name => $value) {
                if (in_array($name, $notAutoloadNames)) {
                    $autoload = 'no';
                } else {
                    $autoload = 'yes';
                }
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                $data[] = [
                    'name' => "$prefix.$name",
                    'value' => $value,
                    'autoload' => $autoload,
                ];

            }
        }
        Setting::query()->upsert($data, ['name'], ['value']);
        do_action("nexus_setting_update");
        clear_setting_cache();
        Filament::notify('success', __('filament::resources/pages/edit-record.messages.saved'), true);
    }

    private function getTabs(): array
    {
        $tabs = [];
        $tabs[] = Forms\Components\Tabs\Tab::make(__('label.setting.hr.tab_header'))
            ->id('hr')
            ->schema($this->getHitAndRunSchema())
            ->columns(2)
        ;

        $tabs[] = Forms\Components\Tabs\Tab::make(__('label.setting.backup.tab_header'))
            ->id('backup')
            ->schema([
                Forms\Components\Radio::make('backup.enabled')->options(self::$yesOrNo)->inline(true)->label(__('label.enabled'))->helperText(__('label.setting.backup.enabled_help')),
                Forms\Components\Radio::make('backup.frequency')->options(['daily' => 'daily', 'hourly' => 'hourly'])->inline(true)->label(__('label.setting.backup.frequency'))->helperText(__('label.setting.backup.frequency_help')),
                Forms\Components\Select::make('backup.hour')->options(range(0, 23))->label(__('label.setting.backup.hour'))->helperText(__('label.setting.backup.hour_help')),
                Forms\Components\Select::make('backup.minute')->options(range(0, 59))->label(__('label.setting.backup.minute'))->helperText(__('label.setting.backup.minute_help')),
                Forms\Components\TextInput::make('backup.google_drive_client_id')->label(__('label.setting.backup.google_drive_client_id')),
                Forms\Components\TextInput::make('backup.google_drive_client_secret')->label(__('label.setting.backup.google_drive_client_secret')),
                Forms\Components\TextInput::make('backup.google_drive_refresh_token')->label(__('label.setting.backup.google_drive_refresh_token')),
                Forms\Components\TextInput::make('backup.google_drive_folder_id')->label(__('label.setting.backup.google_drive_folder_id')),
                Forms\Components\Radio::make('backup.via_ftp')->options(self::$yesOrNo)->inline(true)->label(__('label.setting.backup.via_ftp'))->helperText(__('label.setting.backup.via_ftp_help')),
                Forms\Components\Radio::make('backup.via_sftp')->options(self::$yesOrNo)->inline(true)->label(__('label.setting.backup.via_sftp'))->helperText(__('label.setting.backup.via_sftp_help')),
            ])->columns(2);

        $tabs[] = Forms\Components\Tabs\Tab::make(__('label.setting.seed_box.tab_header'))
            ->id('seed_box')
            ->schema([
                Forms\Components\Radio::make('seed_box.enabled')->options(self::$yesOrNo)->inline(true)->label(__('label.enabled'))->helperText(__('label.setting.seed_box.enabled_help')),
                Forms\Components\TextInput::make('seed_box.not_seed_box_max_speed')->label(__('label.setting.seed_box.not_seed_box_max_speed'))->helperText(__('label.setting.seed_box.not_seed_box_max_speed_help'))->integer(),
                Forms\Components\Radio::make('seed_box.no_promotion')->options(self::$yesOrNo)->inline(true)->label(__('label.setting.seed_box.no_promotion'))->helperText(__('label.setting.seed_box.no_promotion_help')),
                Forms\Components\TextInput::make('seed_box.max_uploaded')->label(__('label.setting.seed_box.max_uploaded'))->helperText(__('label.setting.seed_box.max_uploaded_help'))->integer(),
                Forms\Components\TextInput::make('seed_box.max_uploaded_duration')->label(__('label.setting.seed_box.max_uploaded_duration'))->helperText(__('label.setting.seed_box.max_uploaded_duration_help'))->integer(),
            ])->columns(2);

        $id = "meilisearch";
        $tabs[] = Forms\Components\Tabs\Tab::make(__("label.setting.$id.tab_header"))
            ->id($id)
            ->schema($this->getTabMeilisearchSchema($id))
            ->columns(2)
        ;

        $tabs[] = Forms\Components\Tabs\Tab::make(__('label.setting.system.tab_header'))
            ->id('system')
            ->schema([
                Forms\Components\Radio::make('system.change_username_card_allow_characters_outside_the_alphabets')
                    ->options(self::$yesOrNo)
                    ->inline(true)
                    ->label(__('label.setting.system.change_username_card_allow_characters_outside_the_alphabets'))
                ,
                Forms\Components\TextInput::make('system.change_username_min_interval_in_days')
                    ->integer()
                    ->label(__('label.setting.system.change_username_min_interval_in_days'))
                ,
                Forms\Components\TextInput::make('system.maximum_number_of_medals_can_be_worn')
                    ->integer()
                    ->label(__('label.setting.system.maximum_number_of_medals_can_be_worn'))
                ,
                Forms\Components\TextInput::make('system.cookie_valid_days')
                    ->integer()
                    ->label(__('label.setting.system.cookie_valid_days'))
                ,
                Forms\Components\TextInput::make('system.maximum_upload_speed')
                    ->integer()
                    ->label(__('label.setting.system.maximum_upload_speed'))
                    ->helperText(__('label.setting.system.maximum_upload_speed_help'))
                ,
                Forms\Components\Radio::make('system.is_invite_pre_email_and_username')
                    ->options(self::$yesOrNo)
                    ->inline(true)
                    ->label(__('label.setting.system.is_invite_pre_email_and_username'))
                    ->helperText(__('label.setting.system.is_invite_pre_email_and_username_help'))
                ,
                Forms\Components\Select::make('system.access_admin_class_min')
                    ->options(User::listClass(User::CLASS_VIP))
                    ->label(__('label.setting.system.access_admin_class_min'))
                    ->helperText(__('label.setting.system.access_admin_class_min_help'))
                ,
            ])->columns(2);

        $tabs = apply_filter('nexus_setting_tabs', $tabs);
        return $tabs;
    }

    private function getHitAndRunSchema()
    {
        $default = [
            Forms\Components\Radio::make('hr.mode')->options(HitAndRun::listModes(true))->inline(true)->label(__('label.setting.hr.mode')),
            Forms\Components\TextInput::make('hr.inspect_time')->helperText(__('label.setting.hr.inspect_time_help'))->label(__('label.setting.hr.inspect_time'))->integer(),
            Forms\Components\TextInput::make('hr.seed_time_minimum')->helperText(__('label.setting.hr.seed_time_minimum_help'))->label(__('label.setting.hr.seed_time_minimum'))->integer(),
            Forms\Components\TextInput::make('hr.ignore_when_ratio_reach')->helperText(__('label.setting.hr.ignore_when_ratio_reach_help'))->label(__('label.setting.hr.ignore_when_ratio_reach'))->integer(),
            Forms\Components\TextInput::make('hr.ban_user_when_counts_reach')->helperText(__('label.setting.hr.ban_user_when_counts_reach_help'))->label(__('label.setting.hr.ban_user_when_counts_reach'))->integer(),
            Forms\Components\TextInput::make('hr.include_rate')->helperText(__('label.setting.hr.include_rate_help'))->label(__('label.setting.hr.include_rate'))->numeric(),
        ];
        return apply_filter("hit_and_run_setting_schema", $default);
    }

    private function getTabMeilisearchSchema($id)
    {
        $schema = [];

        $name = "$id.enabled";
        $schema[] = Forms\Components\Radio::make($name)
            ->options(self::$yesOrNo)
            ->inline(true)
            ->label(__('label.enabled'))
            ->helperText(__("label.setting.{$name}_help"))
        ;

        $name = "$id.search_description";
        $schema[] = Forms\Components\Radio::make($name)
            ->options(self::$yesOrNo)
            ->inline(true)
            ->label(__("label.setting.$name"))
            ->helperText(__("label.setting.{$name}_help"))
        ;

        $name = "$id.default_search_mode";
        $schema[] = Forms\Components\Radio::make($name)
            ->options(SearchBox::listSearchModes())
            ->inline(true)
            ->label(__("label.setting.$name"))
            ->helperText(__("label.setting.{$name}_help"))
        ;

        return $schema;
    }

}

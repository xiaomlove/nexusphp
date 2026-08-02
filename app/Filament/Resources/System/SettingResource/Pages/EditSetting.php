<?php

namespace App\Filament\Resources\System\SettingResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Section;
use App\Auth\Permission;
use App\Filament\OptionsTrait;
use App\Filament\Resources\System\SettingResource;
use App\Models\HitAndRun;
use App\Models\SearchBox;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\Torrent;
use App\Models\User;
use App\Repositories\TokenRepository;
use App\Repositories\ToolRepository;
use Filament\Facades\Filament;
use Filament\Resources\Pages\Page;
use Filament\Forms;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Arr;
use Meilisearch\Contracts\Index\Settings;
use Nexus\Database\NexusDB;

class EditSetting extends Page implements HasForms
{
    use InteractsWithForms, OptionsTrait;

    protected static string $resource = SettingResource::class;

    public ?array $data = [];

    public function getTitle(): string
    {
        return __('label.setting.nav_text');
    }

    public function mount()
    {
        static::authorizeResourceAccess();
        $this->fillForm();
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components($this->getFormSchema())
            ->statePath('data');
    }

    private function fillForm()
    {
        $settings = Setting::getFromDb();

        $fallbackEnabled = (bool) config('captcha.attendance.enabled', true);
        $rawSetting = Arr::get($settings, 'captcha.attendance.enabled', $fallbackEnabled);
        if (is_string($rawSetting)) {
            $normalized = in_array(strtolower($rawSetting), ['1', 'true', 'yes'], true);
        } else {
            $normalized = (bool) $rawSetting;
        }
        Arr::set($settings, 'captcha.attendance.enabled', $normalized);

        $this->content->fill($settings);
    }



    protected function getFormSchema(): array
    {
        return [
            Tabs::make('Heading')
                ->tabs($this->getTabs()),
            Action::make('submit')
                ->label(__('label.save'))
                ->action(fn() => $this->submit()),
        ];
    }

    public function submit()
    {
        static::authorizeResourceAccess();

        $formData = $this->content->getState();
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
        Setting::query()->whereIn('name', [
            'captcha.driver',
            'captcha.turnstile',
            'captcha.recaptcha',
        ])->delete();
        $this->doAfterUpdate();
        do_action("nexus_setting_update");
        clear_setting_cache();
        send_admin_success_notification();
    }

    /**
     * this actions get config must not use cache !!!
     *
     * @return void
     */
    private function doAfterUpdate(): void
    {
        Setting::updateUserTokenPermissionAllowedCache();
    }

    private function getTabs(): array
    {
        $tabs = [];
        $tabs[] = Tab::make(__('label.setting.hr.tab_header'))
            ->id('hr')
            ->schema($this->getHitAndRunSchema())
            ->columns(2)
        ;
//        $tabs[] = Forms\Components\Tabs\Tab::make(__('label.setting.require_seed_section.tab_header'))
//            ->id('require_seed_section')
//            ->schema($this->getRequireSeedSectionSchema())
//            ->columns(2)
//        ;

        $tabs[] = Tab::make(__('label.setting.backup.tab_header'))
            ->id('backup')
            ->schema([
                Radio::make('backup.enabled')->options(self::$yesOrNo)->inline(true)->label(__('label.enabled'))->helperText(__('label.setting.backup.enabled_help')),
                Radio::make('backup.frequency')->options(['daily' => 'daily', 'hourly' => 'hourly'])->inline(true)->label(__('label.setting.backup.frequency'))->helperText(__('label.setting.backup.frequency_help')),
                Select::make('backup.hour')->options(range(0, 23))->label(__('label.setting.backup.hour'))->helperText(__('label.setting.backup.hour_help')),
                Select::make('backup.minute')->options(range(0, 59))->label(__('label.setting.backup.minute'))->helperText(__('label.setting.backup.minute_help')),
//                Forms\Components\TextInput::make('backup.google_drive_client_id')->label(__('label.setting.backup.google_drive_client_id')),
//                Forms\Components\TextInput::make('backup.google_drive_client_secret')->label(__('label.setting.backup.google_drive_client_secret')),
//                Forms\Components\TextInput::make('backup.google_drive_refresh_token')->label(__('label.setting.backup.google_drive_refresh_token')),
//                Forms\Components\TextInput::make('backup.google_drive_folder_id')->label(__('label.setting.backup.google_drive_folder_id')),
                TextInput::make('backup.export_path')->label(__('label.setting.backup.export_path'))->helperText(new HtmlString(__('label.setting.backup.export_path_help', ['default_path' => ToolRepository::getBackupExportPathDefault()]))),
                TextInput::make('backup.retention_count')->numeric()->label(__('label.setting.backup.retention_count'))->helperText(new HtmlString(__('label.setting.backup.retention_count_help', ['default_count' => ToolRepository::BACKUP_RETENTION_COUNT_DEFAULT]))),
                Radio::make('backup.via_ftp')->options(self::$yesOrNo)->inline(true)->label(__('label.setting.backup.via_ftp'))->helperText(new HtmlString(__('label.setting.backup.via_ftp_help'))),
                Radio::make('backup.via_sftp')->options(self::$yesOrNo)->inline(true)->label(__('label.setting.backup.via_sftp'))->helperText(new HtmlString(__('label.setting.backup.via_sftp_help'))),
            ])->columns(2);

        $tabs[] = Tab::make(__('label.setting.seed_box.tab_header'))
            ->id('seed_box')
            ->schema([
                Radio::make('seed_box.enabled')->options(self::$yesOrNo)->inline(true)->label(__('label.enabled'))->helperText(__('label.setting.seed_box.enabled_help')),
                TextInput::make('seed_box.not_seed_box_max_speed')->label(__('label.setting.seed_box.not_seed_box_max_speed'))->helperText(__('label.setting.seed_box.not_seed_box_max_speed_help'))->integer(),
                Radio::make('seed_box.no_promotion')->options(self::$yesOrNo)->inline(true)->label(__('label.setting.seed_box.no_promotion'))->helperText(__('label.setting.seed_box.no_promotion_help')),
                TextInput::make('seed_box.max_uploaded')->label(__('label.setting.seed_box.max_uploaded'))->helperText(__('label.setting.seed_box.max_uploaded_help'))->integer(),
                TextInput::make('seed_box.max_uploaded_duration')->label(__('label.setting.seed_box.max_uploaded_duration'))->helperText(__('label.setting.seed_box.max_uploaded_duration_help'))->integer(),
            ])->columns(2);

        $id = "meilisearch";
        $tabs[] = Tab::make(__("label.setting.$id.tab_header"))
            ->id($id)
            ->schema($this->getTabMeilisearchSchema($id))
            ->columns(2)
        ;

        $id = "image_hosting";
        $tabs[] = Tab::make(__("label.setting.$id.tab_header"))
            ->id($id)
            ->schema($this->getTabImageHostingSchema($id))
            ->columns(2)
        ;
        $id = "permission";
        $tabs[] = Tab::make(__("label.setting.$id.tab_header"))
            ->id($id)
            ->schema($this->getTabPermissionSchema($id))
            ->columns(2)
        ;

        $tabs[] = Tab::make(__('label.setting.captcha.tab_header'))
            ->id('captcha')
            ->schema($this->getTabCaptchaSchema())
            ->columns(2)
        ;

        $tabs[] = Tab::make(__('label.setting.system.tab_header'))
            ->id('system')
            ->schema([
                Radio::make('system.change_username_card_allow_characters_outside_the_alphabets')
                    ->options(self::$yesOrNo)
                    ->inline(true)
                    ->label(__('label.setting.system.change_username_card_allow_characters_outside_the_alphabets'))
                ,
                TextInput::make('system.change_username_min_interval_in_days')
                    ->integer()
                    ->label(__('label.setting.system.change_username_min_interval_in_days'))
                ,
                TextInput::make('system.maximum_number_of_medals_can_be_worn')
                    ->integer()
                    ->label(__('label.setting.system.maximum_number_of_medals_can_be_worn'))
                ,
                TextInput::make('system.cookie_valid_days')
                    ->integer()
                    ->label(__('label.setting.system.cookie_valid_days'))
                ,
                TextInput::make('system.maximum_upload_speed')
                    ->integer()
                    ->label(__('label.setting.system.maximum_upload_speed'))
                    ->helperText(__('label.setting.system.maximum_upload_speed_help'))
                ,
                Radio::make('system.is_invite_pre_email_and_username')
                    ->options(self::$yesOrNo)
                    ->inline(true)
                    ->label(__('label.setting.system.is_invite_pre_email_and_username'))
                    ->helperText(__('label.setting.system.is_invite_pre_email_and_username_help'))
                ,
                Radio::make('system.is_record_announce_log')
                    ->options(self::$yesOrNo)
                    ->inline(true)
                    ->label(__('label.setting.system.is_record_announce_log'))
                    ->helperText(__('label.setting.system.is_record_announce_log_help'))
                ,
                Radio::make('system.is_record_seeding_bonus_log')
                    ->options(self::$yesOrNo)
                    ->inline(true)
                    ->label(__('label.setting.system.is_record_seeding_bonus_log'))
                    ->helperText(__('label.setting.system.is_record_seeding_bonus_log_help'))
                ,
                Select::make('system.access_admin_class_min')
                    ->options(User::listClass(User::CLASS_VIP))
                    ->label(__('label.setting.system.access_admin_class_min'))
                    ->helperText(__('label.setting.system.access_admin_class_min_help'))
                ,
                TextInput::make('system.alarm_email_receiver')
                    ->label(__('label.setting.system.alarm_email_receiver'))
                    ->helperText(__('label.setting.system.alarm_email_receiver_help'))
                ,
            ])->columns(2);

        $tabs = apply_filter('nexus_setting_tabs', $tabs);
        return $tabs;
    }

    private function getTabCaptchaSchema(): array
    {
        $captchaPrefix = 'captcha';

        $driverOptions = [
            'image' => __('label.setting.captcha.drivers.image'),
            'cloudflare_turnstile' => __('label.setting.captcha.drivers.cloudflare_turnstile'),
            'google_recaptcha_v2' => __('label.setting.captcha.drivers.google_recaptcha_v2'),
        ];

        $defaultDriver = Setting::get('captcha.default');
        if (is_null($defaultDriver)) {
            $defaultDriver = Setting::get('captcha.driver', nexus_env('CAPTCHA_DRIVER', 'image'));
        }

        $turnstileSiteKey = Setting::get(
            'captcha.drivers.cloudflare_turnstile.site_key',
            Setting::get('captcha.turnstile.site_key', nexus_env('TURNSTILE_SITE_KEY'))
        );
        $turnstileSecretKey = Setting::get(
            'captcha.drivers.cloudflare_turnstile.secret_key',
            Setting::get('captcha.turnstile.secret_key', nexus_env('TURNSTILE_SECRET_KEY'))
        );
        $turnstileTheme = Setting::get(
            'captcha.drivers.cloudflare_turnstile.theme',
            Setting::get('captcha.turnstile.theme', nexus_env('TURNSTILE_THEME', 'auto'))
        );
        $turnstileSize = Setting::get(
            'captcha.drivers.cloudflare_turnstile.size',
            Setting::get('captcha.turnstile.size', nexus_env('TURNSTILE_SIZE', 'flexible'))
        );

        $recaptchaSiteKey = Setting::get(
            'captcha.drivers.google_recaptcha_v2.site_key',
            Setting::get('captcha.recaptcha.site_key', nexus_env('RECAPTCHA_SITE_KEY'))
        );
        $recaptchaSecretKey = Setting::get(
            'captcha.drivers.google_recaptcha_v2.secret_key',
            Setting::get('captcha.recaptcha.secret_key', nexus_env('RECAPTCHA_SECRET_KEY'))
        );
        $recaptchaTheme = Setting::get(
            'captcha.drivers.google_recaptcha_v2.theme',
            Setting::get('captcha.recaptcha.theme', nexus_env('RECAPTCHA_THEME', 'light'))
        );
        $recaptchaSize = Setting::get(
            'captcha.drivers.google_recaptcha_v2.size',
            Setting::get('captcha.recaptcha.size', nexus_env('RECAPTCHA_SIZE', 'normal'))
        );

        $attendanceCaptchaSetting = Setting::get('captcha.attendance.enabled', true);
        if (is_string($attendanceCaptchaSetting)) {
            $attendanceCaptchaEnabled = in_array(strtolower($attendanceCaptchaSetting), ['1', 'true', 'yes'], true);
        } else {
            $attendanceCaptchaEnabled = (bool) $attendanceCaptchaSetting;
        }

        $schema = [
            Toggle::make("$captchaPrefix.attendance.enabled")
                ->label(__('label.setting.captcha.attendance.enabled'))
                ->helperText(__('label.setting.captcha.attendance.enabled_help'))
                ->default($attendanceCaptchaEnabled)
                ->columnSpanFull()
            ,
            Select::make("$captchaPrefix.default")
                ->options($driverOptions)
                ->label(__('label.setting.captcha.driver'))
                ->helperText(__('label.setting.captcha.driver_help'))
                ->default($defaultDriver)
                ->reactive()
            ,
            Fieldset::make(__('label.setting.captcha.turnstile.section'))
                ->visible(fn (Get $get) => $get('captcha.default') === 'cloudflare_turnstile')
                ->schema([
                    TextInput::make('captcha.drivers.cloudflare_turnstile.site_key')
                        ->label(__('label.setting.captcha.turnstile.site_key'))
                        ->helperText(__('label.setting.captcha.turnstile.site_key_help'))
                        ->default($turnstileSiteKey) ,
                    TextInput::make('captcha.drivers.cloudflare_turnstile.secret_key')
                        ->label(__('label.setting.captcha.turnstile.secret_key'))
                        ->helperText(__('label.setting.captcha.turnstile.secret_key_help'))
                        ->password()
                        ->revealable()
                        ->default($turnstileSecretKey),
                    Select::make('captcha.drivers.cloudflare_turnstile.theme')
                        ->label(__('label.setting.captcha.turnstile.theme'))
                        ->helperText(__('label.setting.captcha.turnstile.theme_help'))
                        ->options([
                            'auto' => __('label.setting.captcha.turnstile.theme_auto'),
                            'light' => __('label.setting.captcha.turnstile.theme_light'),
                            'dark' => __('label.setting.captcha.turnstile.theme_dark'),
                        ])
                        ->default($turnstileTheme),
                    Select::make('captcha.drivers.cloudflare_turnstile.size')
                        ->label(__('label.setting.captcha.turnstile.size'))
                        ->helperText(__('label.setting.captcha.turnstile.size_help'))
                        ->options([
                            'normal' => __('label.setting.captcha.turnstile.size_normal'),
                            'compact' => __('label.setting.captcha.turnstile.size_compact'),
                            'flexible' => __('label.setting.captcha.turnstile.size_flexible'),
                        ])
                        ->default($turnstileSize),
                ])
            ,
            Fieldset::make(__('label.setting.captcha.recaptcha.section'))
                ->visible(fn (Get $get) => $get('captcha.default') === 'google_recaptcha_v2')
                ->schema([
                    TextInput::make('captcha.drivers.google_recaptcha_v2.site_key')
                        ->label(__('label.setting.captcha.recaptcha.site_key'))
                        ->helperText(__('label.setting.captcha.recaptcha.site_key_help'))
                        ->default($recaptchaSiteKey),
                    TextInput::make('captcha.drivers.google_recaptcha_v2.secret_key')
                        ->label(__('label.setting.captcha.recaptcha.secret_key'))
                        ->helperText(__('label.setting.captcha.recaptcha.secret_key_help'))
                        ->password()
                        ->revealable()
                        ->default($recaptchaSecretKey),
                    Select::make('captcha.drivers.google_recaptcha_v2.theme')
                        ->label(__('label.setting.captcha.recaptcha.theme'))
                        ->helperText(__('label.setting.captcha.recaptcha.theme_help'))
                        ->options([
                            'light' => __('label.setting.captcha.recaptcha.theme_light'),
                            'dark' => __('label.setting.captcha.recaptcha.theme_dark'),
                        ])
                        ->default($recaptchaTheme),
                    Select::make('captcha.drivers.google_recaptcha_v2.size')
                        ->label(__('label.setting.captcha.recaptcha.size'))
                        ->helperText(__('label.setting.captcha.recaptcha.size_help'))
                        ->options([
                            'normal' => __('label.setting.captcha.recaptcha.size_normal'),
                            'compact' => __('label.setting.captcha.recaptcha.size_compact'),
                        ])
                        ->default($recaptchaSize),
                ])
        ];

        return $schema;
    }

    private function getHitAndRunSchema()
    {
        $default = [
            Radio::make('hr.mode')->options(HitAndRun::listModes(true))->inline(true)->label(__('label.setting.hr.mode')),
            TextInput::make('hr.inspect_time')->helperText(__('label.setting.hr.inspect_time_help'))->label(__('label.setting.hr.inspect_time'))->integer(),
            TextInput::make('hr.seed_time_minimum')->helperText(__('label.setting.hr.seed_time_minimum_help'))->label(__('label.setting.hr.seed_time_minimum'))->integer(),
            TextInput::make('hr.leech_time_minimum')->helperText(__('label.setting.hr.leech_time_minimum_help'))->label(__('label.setting.hr.leech_time_minimum'))->integer(),
            TextInput::make('hr.ignore_when_ratio_reach')->helperText(__('label.setting.hr.ignore_when_ratio_reach_help'))->label(__('label.setting.hr.ignore_when_ratio_reach'))->integer(),
            TextInput::make('hr.ban_user_when_counts_reach')->helperText(__('label.setting.hr.ban_user_when_counts_reach_help'))->label(__('label.setting.hr.ban_user_when_counts_reach'))->integer(),
            TextInput::make('hr.include_rate')->helperText(__('label.setting.hr.include_rate_help'))->label(__('label.setting.hr.include_rate'))->numeric(),
        ];
        return apply_filter("hit_and_run_setting_schema", $default);
    }

    private function getRequireSeedSectionSchema(): array
    {
        return [
            Radio::make('require_seed_section.enabled')->options(self::$yesOrNo)->label(__('label.enabled'))->helperText(__('label.setting.require_seed_section.enabled_help')),
            TextInput::make('require_seed_section.menu_title')->label(__('label.setting.require_seed_section.menu_title'))->helperText(__('label.setting.require_seed_section.menu_title_help')),
            TextInput::make('require_seed_section.seeder_lte')->label(__('label.setting.require_seed_section.seeder_lte'))->helperText(__('label.setting.require_seed_section.seeder_lte_help'))->integer(),
            TextInput::make('require_seed_section.seeder_gte')->label(__('label.setting.require_seed_section.seeder_gte'))->helperText(__('label.setting.require_seed_section.seeder_gte_help'))->integer(),
            CheckboxList::make('require_seed_section.require_tags')->label(__('label.setting.require_seed_section.require_tags'))->helperText(__('label.setting.require_seed_section.require_tags_help'))->options(Tag::query()->pluck('name', 'id'))->columns(4),
            Select::make('require_seed_section.promotion_state')->label(__('label.setting.require_seed_section.promotion_state'))->helperText(__('label.setting.require_seed_section.promotion_state_help'))->options(Torrent::listPromotionTypes(true)),
            TextInput::make('require_seed_section.daily_seed_time_min')->label(__('label.setting.require_seed_section.daily_seed_time_min'))->helperText(__('label.setting.require_seed_section.daily_seed_time_min_help'))->integer(),
            TextInput::make('require_seed_section.torrent_count_max')->label(__('label.setting.require_seed_section.torrent_count_max'))->helperText(__('label.setting.require_seed_section.torrent_count_max_help'))->integer(),
            Repeater::make('require_seed_section.bonus_reward')
                ->label(__('label.setting.require_seed_section.bonus_reward'))
                ->helperText(__('label.setting.require_seed_section.bonus_reward_help'))
                ->schema([
                    TextInput::make('seeders')
                        ->label(__('label.setting.require_seed_section.seeders'))
                        ->required()
                        ->integer()
                        ->columnSpan(2)
                    ,
                    Repeater::make('seed_time_reward')
                        ->label(__('label.setting.require_seed_section.seed_time_reward'))
                        ->schema([
                            TextInput::make('begin')->label(__('label.setting.require_seed_section.seed_time_reward_begin'))->helperText(__('label.setting.require_seed_section.seed_time_reward_begin_help')),
                            TextInput::make('end')->label(__('label.setting.require_seed_section.seed_time_reward_end'))->helperText(__('label.setting.require_seed_section.seed_time_reward_end_help')),
                            TextInput::make('window')->label(__('label.setting.require_seed_section.seed_time_reward_window'))->helperText(__('label.setting.require_seed_section.seed_time_reward_window_help')),
                            TextInput::make('reward')->label(__('label.setting.require_seed_section.seed_time_reward_reward'))->helperText(__('label.setting.require_seed_section.seed_time_reward_reward_help')),
                        ])
                        ->columns(4)
                        ->columnSpan(5)
                    ,
                    Repeater::make('data_traffic_reward')
                        ->label(__('label.setting.require_seed_section.data_traffic_reward'))
                        ->schema([
                            TextInput::make('begin')->label(__('label.setting.require_seed_section.data_traffic_reward_begin'))->helperText(__('label.setting.require_seed_section.data_traffic_reward_begin_help')),
                            TextInput::make('end')->label(__('label.setting.require_seed_section.data_traffic_reward_end'))->helperText(__('label.setting.require_seed_section.data_traffic_reward_end_help')),
                            TextInput::make('window')->label(__('label.setting.require_seed_section.data_traffic_reward_window'))->helperText(__('label.setting.require_seed_section.data_traffic_reward_window_help')),
                            TextInput::make('reward')->label(__('label.setting.require_seed_section.data_traffic_reward_reward'))->helperText(__('label.setting.require_seed_section.data_traffic_reward_reward_help')),
                        ])
                        ->columns(4)
                        ->columnSpan(5)
                ])
                ->columns(12)
                ->columnSpanFull()
                ->defaultItems(3)
                ->reorderable(false)
        ];
    }

    private function getTabMeilisearchSchema($id): array
    {
        $schema = [];

        $name = "$id.enabled";
        $schema[] = Radio::make($name)
            ->options(self::$yesOrNo)
            ->inline(true)
            ->label(__('label.enabled'))
            ->helperText(__("label.setting.{$name}_help"))
        ;

        $name = "$id.search_description";
        $schema[] = Radio::make($name)
            ->options(self::$yesOrNo)
            ->inline(true)
            ->label(__("label.setting.$name"))
            ->helperText(__("label.setting.{$name}_help"))
        ;

        $name = "$id.default_search_mode";
        $schema[] = Radio::make($name)
            ->options(SearchBox::listSearchModes())
            ->inline(true)
            ->label(__("label.setting.$name"))
            ->helperText(__("label.setting.{$name}_help"))
        ;

        return $schema;
    }

    private function getTabImageHostingSchema($id): array
    {
        $schema = [];
        $name = "$id.driver";
        $schema[] = Radio::make($name)
            ->options([
                'local' => 'local',
                'chevereto' => 'chevereto',
                'lsky' => 'lsky',
                'cloudflare_imgbed' => 'CloudFlare ImgBed',
            ])
            ->inline(true)
            ->label(__("label.setting.$name"))
            ->helperText(__("label.setting.{$name}_help"))
            ->columnSpanFull()
        ;

        //chevereto
        $driverName = "chevereto";
        $driverId = sprintf("%s_%s", $id, $driverName);
        $driverSchemas = [];
        $field = "upload_api_endpoint";
        $driverSchemas[] = TextInput::make("$driverId.$field")
            ->label(__("label.setting.$id.$field"))
        ;
        $field = "upload_token";
        $driverSchemas[] = TextInput::make("$driverId.$field")
            ->label(__("label.setting.$id.$field"))
            ->password()
            ->revealable()
        ;
        $field = "base_url";
        $driverSchemas[] = TextInput::make("$driverId.$field")
            ->label(__("label.setting.$id.$field"))
        ;

        $driverSection = Section::make($driverName)->schema($driverSchemas);
        $schema[] = $driverSection;

        //lsky
        $driverName = "lsky";
        $driverId = sprintf("%s_%s", $id, $driverName);
        $driverSchemas = [];
        $field = "upload_api_endpoint";
        $driverSchemas[] = TextInput::make("$driverId.$field")
            ->label(__("label.setting.$id.$field"))
        ;
        $field = "upload_token";
        $driverSchemas[] = TextInput::make("$driverId.$field")
            ->label(__("label.setting.$id.$field"))
            ->password()
            ->revealable()
        ;
        $field = "base_url";
        $driverSchemas[] = TextInput::make("$driverId.$field")
            ->label(__("label.setting.$id.$field"))
        ;
        $driverSection = Section::make($driverName)->schema($driverSchemas);
        $schema[] = $driverSection;

        //CloudFlare ImgBed
        $driverName = "cloudflare_imgbed";
        $driverId = sprintf("%s_%s", $id, $driverName);
        $driverSchemas = [];
        $field = "upload_api_endpoint";
        $driverSchemas[] = TextInput::make("$driverId.$field")
            ->label(__("label.setting.$id.$field"))
            ->helperText(__("label.setting.$id.cloudflare_imgbed_endpoint_help"))
        ;
        $field = "upload_token";
        $driverSchemas[] = TextInput::make("$driverId.$field")
            ->label(__("label.setting.$id.$field"))
            ->helperText(__("label.setting.$id.cloudflare_imgbed_token_help"))
            ->password()
            ->revealable()
        ;
        $field = "base_url";
        $driverSchemas[] = TextInput::make("$driverId.$field")
            ->label(__("label.setting.$id.$field"))
            ->helperText(__("label.setting.$id.cloudflare_imgbed_base_url_help"))
        ;
        $field = "upload_channel";
        $driverSchemas[] = Select::make("$driverId.$field")
            ->options([
                'telegram' => 'Telegram',
                'cfr2' => 'Cloudflare R2',
                's3' => 'S3',
                'discord' => 'Discord',
                'huggingface' => 'Hugging Face',
                'webdav' => 'WebDAV',
            ])
            ->placeholder(__("label.setting.$id.upload_channel_default"))
            ->label(__("label.setting.$id.$field"))
        ;
        $field = "channel_name";
        $driverSchemas[] = TextInput::make("$driverId.$field")
            ->label(__("label.setting.$id.$field"))
            ->helperText(__("label.setting.$id.channel_name_help"))
        ;
        $field = "upload_folder";
        $driverSchemas[] = TextInput::make("$driverId.$field")
            ->label(__("label.setting.$id.$field"))
            ->helperText(__("label.setting.$id.upload_folder_help"))
        ;
        $driverSection = Section::make('CloudFlare ImgBed')->schema($driverSchemas);
        $schema[] = $driverSection;


        return $schema;
    }

    private function getTabPermissionSchema($id): array
    {
        $schema = [];

        $name = "$id.user_token_allowed";
        $schema[] = CheckboxList::make($name)
            ->options(TokenRepository::listUserTokenPermissions())
            ->label(__("label.setting.{$name}"))
            ->helperText(__("label.setting.{$name}_help"))
            ->columns(2)
        ;


        return $schema;
    }

}

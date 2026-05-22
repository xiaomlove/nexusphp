<?php

namespace App\Filament\Resources\System;

use App\Filament\Resources\System\LocationResource\Pages\CreateLocation;
use App\Filament\Resources\System\LocationResource\Pages\EditLocation;
use App\Filament\Resources\System\LocationResource\Pages\ListLocations;
use App\Models\Location;
use App\Models\User;
use App\Support\Validators;
use Closure;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Filament 5 admin resource for the `locations` table — replaces
 * the legacy `public/location.php` (deleted in the same PR). The
 * legacy URL `/location.php` 302s to `/nexusphp/locations` so
 * `SysoppanelTableSeeder` row 10 (`url=location.php`) and any
 * staff bookmarks keep working — see `routes/web.php`.
 *
 * The legacy script was a SYSOP-only CRUD over the `locations`
 * table, with a quirky GET-with-querystring write protocol
 * (`?delid=N&sure=yes` for delete, `?editid=N` for edit form,
 * `?edited=1&...` for edit submit, `?add=true&...` for create
 * submit). Filament replaces every one of those flows with the
 * standard List / Create / Edit pages, so the legacy verbs no
 * longer need to be preserved (the redirect lands users on the
 * Filament list, where they pick the same action through the UI).
 *
 * Validation
 * ----------
 * The legacy script enforced two cross-field rules:
 *   1. `validip_format($start_ip)` and `validip_format($end_ip)`
 *      — i.e. dotted IPv4. Reused via
 *      `App\Support\Validators::isIpv4Format` (the same helper the
 *      legacy `validip_format()` proxies to).
 *   2. `ip2long($end_ip) >= ip2long($start_ip)` — end >= start,
 *      with equal allowed for a single-IP entry. Implemented as a
 *      closure rule on the `end_ip` field that reads `start_ip`
 *      from the Filament form context (`$get('start_ip')`).
 *
 * Both rules render the original "Invalid IP Address Format !!!"
 * / "The end IP address should be larger than the start one"
 * messages so a SYSOP migrating from the legacy admin sees the
 * same wording.
 *
 * The `hit` column is a read-only counter populated by the
 * tracker side; it is shown in the table for context but
 * intentionally not surfaced in the form.
 */
class LocationResource extends Resource
{
    protected static ?string $model = Location::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 11;

    public static function getNavigationLabel(): string
    {
        return 'Locations';
    }

    public static function getBreadcrumb(): string
    {
        return self::getNavigationLabel();
    }

    /**
     * Restrict to SYSOP+. Mirrors the legacy
     * `if (get_user_class() < UC_SYSOP) exit('access denied.');`
     * gate. The `User::CLASS_SYSOP` constant is the same numeric
     * value the legacy `UC_SYSOP` constant resolves to.
     */
    public static function canAccess(): bool
    {
        $user = Auth::guard('nexus-web')->user() ?? Auth::user();
        if (! $user instanceof User) {
            return false;
        }

        return (int) $user->class >= (int) User::CLASS_SYSOP;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Name')
                ->maxLength(50)
                ->required(),

            TextInput::make('location_main')
                ->label('Main Location')
                ->maxLength(200)
                ->required(),

            TextInput::make('location_sub')
                ->label('Sub Location')
                ->maxLength(200)
                ->required(),

            TextInput::make('start_ip')
                ->label('Start IP')
                ->maxLength(20)
                ->required()
                ->live(onBlur: true)
                ->rules([self::ipv4Rule()]),

            TextInput::make('end_ip')
                ->label('End IP')
                ->maxLength(20)
                ->required()
                ->rules([
                    self::ipv4Rule(),
                    fn (Get $get): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                        $end = is_string($value) ? ip2long($value) : false;
                        $start = ip2long((string) ($get('start_ip') ?? ''));
                        if ($end === false || $start === false) {
                            // Format errors are surfaced by `ipv4Rule`;
                            // bail out here so we don't double-report.
                            return;
                        }
                        if ($end < $start) {
                            $fail('The end IP address should be larger than the start one, or equal for single IP check!');
                        }
                    },
                ]),

            TextInput::make('theory_upspeed')
                ->label('Theory Up Speed')
                ->numeric()
                ->minValue(0)
                ->required(),

            TextInput::make('practical_upspeed')
                ->label('Practical Up Speed')
                ->numeric()
                ->minValue(0)
                ->required(),

            TextInput::make('theory_downspeed')
                ->label('Theory Down Speed')
                ->numeric()
                ->minValue(0)
                ->required(),

            TextInput::make('practical_downspeed')
                ->label('Practical Down Speed')
                ->numeric()
                ->minValue(0)
                ->required(),

            TextInput::make('flagpic')
                ->label('Flag Picture filename')
                ->helperText('Filename under public/pic/location/, e.g. "us.gif". Leave blank for none.')
                ->maxLength(50),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('location_main')->label('Main')->searchable(),
                TextColumn::make('location_sub')->label('Sub')->limit(40),
                TextColumn::make('start_ip')->label('Start IP')->sortable(),
                TextColumn::make('end_ip')->label('End IP')->sortable(),
                TextColumn::make('theory_upspeed')->label('T.U')->alignEnd(),
                TextColumn::make('practical_upspeed')->label('P.U')->alignEnd(),
                TextColumn::make('theory_downspeed')->label('T.D')->alignEnd(),
                TextColumn::make('practical_downspeed')->label('P.D')->alignEnd(),
                TextColumn::make('hit')->label('Hits')->alignEnd()->sortable(),
            ])
            ->defaultSort('name', 'asc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLocations::route('/'),
            'create' => CreateLocation::route('/create'),
            'edit' => EditLocation::route('/{record}/edit'),
        ];
    }

    /**
     * Returns a closure rule reproducing the legacy
     * `validip_format()` check (which proxies to
     * {@see Validators::isIpv4Format}). The closure is wrapped in
     * an outer closure so Filament's rule pipeline can re-bind it
     * per-field at validation time. Renders the same "Invalid IP
     * Address Format" wording the legacy script emitted on a
     * malformed input.
     */
    private static function ipv4Rule(): Closure
    {
        return static function (): Closure {
            return static function (string $attribute, mixed $value, Closure $fail): void {
                if (! is_string($value) || ! Validators::isIpv4Format($value)) {
                    $fail('Invalid IP Address Format!');
                }
            };
        };
    }
}

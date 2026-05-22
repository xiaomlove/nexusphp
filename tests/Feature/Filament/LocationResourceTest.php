<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\System\LocationResource;
use App\Filament\Resources\System\LocationResource\Pages\CreateLocation;
use App\Filament\Resources\System\LocationResource\Pages\EditLocation;
use App\Filament\Resources\System\LocationResource\Pages\ListLocations;
use App\Models\Location;
use App\Models\User;
use Livewire\Livewire;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for the Filament `LocationResource` (replaces
 * `public/location.php`). Pins the auth gate, the IP-address
 * cross-field validation contract migrated from the legacy
 * `validip_format()` + `ip2long()` checks, and the standard
 * Filament list / create / edit Livewire roundtrips.
 */
class LocationResourceTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    /** @var array<int,int> */
    private array $createdLocationIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/nexusphp/locations';
    }

    protected function tearDown(): void
    {
        if (! empty($this->createdLocationIds)) {
            NexusDB::table('locations')
                ->whereIn('id', $this->createdLocationIds)
                ->delete();
        }
        $this->createdLocationIds = [];

        parent::tearDown();
    }

    public function test_can_access_returns_false_for_non_sysop(): void
    {
        $user = $this->createLegacyUser(overrides: ['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $this->assertFalse(LocationResource::canAccess());
    }

    public function test_can_access_returns_true_for_sysop(): void
    {
        $sysop = $this->createLegacyUser(overrides: ['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        $this->assertTrue(LocationResource::canAccess());
    }

    public function test_sysop_can_render_the_list_page(): void
    {
        $sysop = $this->createLegacyUser(overrides: ['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        $row = $this->makeLocation(['name' => 'list-test-loc']);
        $this->createdLocationIds[] = $row->id;

        Livewire::test(ListLocations::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$row]);
    }

    public function test_sysop_can_create_a_location(): void
    {
        $sysop = $this->createLegacyUser(overrides: ['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        $name = 'filament-loc-'.bin2hex(random_bytes(3));

        Livewire::test(CreateLocation::class)
            ->fillForm([
                'name' => $name,
                'location_main' => 'Eurasia',
                'location_sub' => 'Russia',
                'start_ip' => '10.0.0.0',
                'end_ip' => '10.0.0.255',
                'theory_upspeed' => 100,
                'practical_upspeed' => 80,
                'theory_downspeed' => 100,
                'practical_downspeed' => 90,
                'flagpic' => '',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $row = Location::query()->where('name', $name)->first();
        $this->assertNotNull($row);
        $this->createdLocationIds[] = $row->id;
        $this->assertSame('10.0.0.0', $row->start_ip);
        $this->assertSame('10.0.0.255', $row->end_ip);
    }

    public function test_create_rejects_invalid_ipv4_format(): void
    {
        $sysop = $this->createLegacyUser(overrides: ['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        Livewire::test(CreateLocation::class)
            ->fillForm([
                'name' => 'bad-ip',
                'location_main' => 'Foo',
                'location_sub' => 'Bar',
                'start_ip' => 'not-an-ip',
                'end_ip' => '10.0.0.1',
                'theory_upspeed' => 1,
                'practical_upspeed' => 1,
                'theory_downspeed' => 1,
                'practical_downspeed' => 1,
            ])
            ->call('create')
            ->assertHasFormErrors(['start_ip']);
    }

    public function test_create_rejects_end_ip_smaller_than_start_ip(): void
    {
        $sysop = $this->createLegacyUser(overrides: ['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        Livewire::test(CreateLocation::class)
            ->fillForm([
                'name' => 'reverse-range',
                'location_main' => 'Foo',
                'location_sub' => 'Bar',
                'start_ip' => '10.0.0.100',
                'end_ip' => '10.0.0.10',
                'theory_upspeed' => 1,
                'practical_upspeed' => 1,
                'theory_downspeed' => 1,
                'practical_downspeed' => 1,
            ])
            ->call('create')
            ->assertHasFormErrors(['end_ip']);
    }

    public function test_create_accepts_equal_start_and_end_ip(): void
    {
        // Legacy comment: "or equal for single IP check" — a
        // single-IP entry was always allowed.
        $sysop = $this->createLegacyUser(overrides: ['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        $name = 'single-ip-'.bin2hex(random_bytes(3));

        Livewire::test(CreateLocation::class)
            ->fillForm([
                'name' => $name,
                'location_main' => 'Pin',
                'location_sub' => 'Single',
                'start_ip' => '10.0.0.42',
                'end_ip' => '10.0.0.42',
                'theory_upspeed' => 1,
                'practical_upspeed' => 1,
                'theory_downspeed' => 1,
                'practical_downspeed' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $row = Location::query()->where('name', $name)->first();
        $this->assertNotNull($row);
        $this->createdLocationIds[] = $row->id;
    }

    public function test_sysop_can_edit_an_existing_location(): void
    {
        $sysop = $this->createLegacyUser(overrides: ['class' => User::CLASS_SYSOP]);
        $this->actingAs($sysop, 'nexus-web');

        $row = $this->makeLocation([
            'name' => 'edit-before',
            'theory_upspeed' => 10,
        ]);
        $this->createdLocationIds[] = $row->id;

        Livewire::test(EditLocation::class, ['record' => $row->id])
            ->fillForm([
                'name' => 'edit-after',
                'theory_upspeed' => 999,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $reloaded = Location::query()->find($row->id);
        $this->assertSame('edit-after', $reloaded->name);
        $this->assertSame(999, (int) $reloaded->theory_upspeed);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function makeLocation(array $overrides = []): Location
    {
        return Location::create(array_merge([
            'name' => 'test-loc-'.bin2hex(random_bytes(3)),
            'location_main' => 'Earth',
            'location_sub' => 'Default',
            'flagpic' => '',
            'start_ip' => '127.0.0.0',
            'end_ip' => '127.0.0.255',
            'theory_upspeed' => 10,
            'practical_upspeed' => 10,
            'theory_downspeed' => 10,
            'practical_downspeed' => 10,
        ], $overrides));
    }
}

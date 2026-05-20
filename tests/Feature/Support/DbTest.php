<?php

namespace Tests\Feature\Support;

use App\Support\Db;
use Illuminate\Support\Carbon;
use Nexus\Database\NexusDB;
use Tests\FeatureTestCase;

/**
 * Pins down `App\Support\Db::sumOf()` against the legacy
 * `get_row_sum($table, $field, $suffix)` contract.
 *
 * `tags` is used as the test target because the schema is trivial
 * (`id`, `name` UNIQUE, `color`, `priority` INT, timestamps) and there
 * are no foreign-key constraints — see the migration in
 * `database/migrations/2022_03_07_012545_create_tags_table.php`.
 * The outer DatabaseTransactions wrapper rolls every row back on
 * teardown, so the table state survives only for the duration of one
 * test case.
 */
class DbTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        NexusDB::table('tags')->delete();
    }

    public function test_sum_of_returns_zero_when_table_is_empty(): void
    {
        $this->assertSame(0, Db::sumOf('tags', 'priority'));
    }

    public function test_sum_of_returns_zero_when_where_filter_matches_nothing(): void
    {
        $this->seedTags([10, 20, 30]);

        $this->assertSame(0, Db::sumOf('tags', 'priority', 'WHERE priority > 9999'));
    }

    public function test_sum_of_computes_total_across_all_rows(): void
    {
        $this->seedTags([10, 20, 30]);

        $this->assertEquals(60, Db::sumOf('tags', 'priority'));
    }

    public function test_sum_of_honours_where_suffix(): void
    {
        $this->seedTags([10, 20, 30]);

        $this->assertEquals(50, Db::sumOf('tags', 'priority', 'WHERE priority > 15'));
    }

    public function test_sum_of_returns_zero_when_summed_column_is_only_nulls(): void
    {
        NexusDB::table('tags')->insert([
            'name' => 't_null_only_'.bin2hex(random_bytes(3)),
            'color' => '#000000',
            'priority' => 0,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
        NexusDB::table('tags')->update(['priority' => null]);

        $this->assertSame(0, Db::sumOf('tags', 'priority'));
    }

    public function test_legacy_get_row_sum_shim_delegates_to_support_db(): void
    {
        $this->seedTags([7, 13, 21]);

        $direct = Db::sumOf('tags', 'priority');
        $shim = get_row_sum('tags', 'priority');

        $this->assertEquals(41, $direct);
        $this->assertSame($direct, $shim);
    }

    public function test_legacy_get_row_sum_shim_forwards_suffix(): void
    {
        $this->seedTags([7, 13, 21]);

        $direct = Db::sumOf('tags', 'priority', 'WHERE priority >= 13');
        $shim = get_row_sum('tags', 'priority', 'WHERE priority >= 13');

        $this->assertEquals(34, $direct);
        $this->assertSame($direct, $shim);
    }

    /**
     * @param  array<int>  $priorities
     */
    private function seedTags(array $priorities): void
    {
        $now = Carbon::now();
        $rows = [];
        foreach ($priorities as $i => $p) {
            $rows[] = [
                'name' => 'tag_'.$i.'_'.bin2hex(random_bytes(3)),
                'color' => '#000000',
                'priority' => $p,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        NexusDB::table('tags')->insert($rows);
    }
}

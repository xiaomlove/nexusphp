<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TeamsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('teams')->delete();
        
        \DB::table('teams')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'HDS',
                'sort_index' => 0,
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'CHD',
                'sort_index' => 0,
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'MySiLU',
                'sort_index' => 0,
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'WiKi',
                'sort_index' => 0,
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'Other',
                'sort_index' => 0,
            ),
        ));
        
        
    }
}
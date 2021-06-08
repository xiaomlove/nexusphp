<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class StandardsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('standards')->delete();
        
        \DB::table('standards')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => '1080p',
                'sort_index' => 0,
            ),
            1 => 
            array (
                'id' => 2,
                'name' => '1080i',
                'sort_index' => 0,
            ),
            2 => 
            array (
                'id' => 3,
                'name' => '720p',
                'sort_index' => 0,
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'SD',
                'sort_index' => 0,
            ),
        ));
        
        
    }
}
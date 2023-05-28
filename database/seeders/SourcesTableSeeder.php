<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SourcesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('sources')->delete();
        
        \DB::table('sources')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'Blu-ray',
                'sort_index' => 0,
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'HD DVD',
                'sort_index' => 0,
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'DVD',
                'sort_index' => 0,
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'HDTV',
                'sort_index' => 0,
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'TV',
                'sort_index' => 0,
            ),
            5 => 
            array (
                'id' => 6,
                'name' => 'Other',
                'sort_index' => 0,
            ),
        ));
        
        
    }
}
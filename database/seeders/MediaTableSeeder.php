<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MediaTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('media')->delete();
        
        \DB::table('media')->insert(array (
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
                'sort_index' => 1,
            ),
            2 => 
            array (
                'id' => 4,
                'name' => 'MiniBD',
                'sort_index' => 4,
            ),
            3 => 
            array (
                'id' => 5,
                'name' => 'HDTV',
                'sort_index' => 5,
            ),
            4 => 
            array (
                'id' => 6,
                'name' => 'DVDR',
                'sort_index' => 6,
            ),
            5 => 
            array (
                'id' => 7,
                'name' => 'Encode',
                'sort_index' => 3,
            ),
            6 => 
            array (
                'id' => 3,
                'name' => 'Remux',
                'sort_index' => 2,
            ),
            7 => 
            array (
                'id' => 8,
                'name' => 'CD',
                'sort_index' => 7,
            ),
            8 => 
            array (
                'id' => 9,
                'name' => 'Track',
                'sort_index' => 9,
            ),
        ));
        
        
    }
}
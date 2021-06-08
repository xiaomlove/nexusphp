<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AudiocodecsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('audiocodecs')->delete();
        
        \DB::table('audiocodecs')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'FLAC',
                'image' => '',
                'sort_index' => 0,
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'APE',
                'image' => '',
                'sort_index' => 0,
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'DTS',
                'image' => '',
                'sort_index' => 0,
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'MP3',
                'image' => '',
                'sort_index' => 0,
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'OGG',
                'image' => '',
                'sort_index' => 0,
            ),
            5 => 
            array (
                'id' => 6,
                'name' => 'AAC',
                'image' => '',
                'sort_index' => 0,
            ),
            6 => 
            array (
                'id' => 7,
                'name' => 'Other',
                'image' => '',
                'sort_index' => 0,
            ),
        ));
        
        
    }
}
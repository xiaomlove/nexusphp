<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DownloadspeedTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('downloadspeed')->delete();
        
        \DB::table('downloadspeed')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => '64kbps',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => '128kbps',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => '256kbps',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => '512kbps',
            ),
            4 => 
            array (
                'id' => 5,
                'name' => '768kbps',
            ),
            5 => 
            array (
                'id' => 6,
                'name' => '1Mbps',
            ),
            6 => 
            array (
                'id' => 7,
                'name' => '1.5Mbps',
            ),
            7 => 
            array (
                'id' => 8,
                'name' => '2Mbps',
            ),
            8 => 
            array (
                'id' => 9,
                'name' => '3Mbps',
            ),
            9 => 
            array (
                'id' => 10,
                'name' => '4Mbps',
            ),
            10 => 
            array (
                'id' => 11,
                'name' => '5Mbps',
            ),
            11 => 
            array (
                'id' => 12,
                'name' => '6Mbps',
            ),
            12 => 
            array (
                'id' => 13,
                'name' => '7Mbps',
            ),
            13 => 
            array (
                'id' => 14,
                'name' => '8Mbps',
            ),
            14 => 
            array (
                'id' => 15,
                'name' => '9Mbps',
            ),
            15 => 
            array (
                'id' => 16,
                'name' => '10Mbps',
            ),
            16 => 
            array (
                'id' => 17,
                'name' => '48Mbps',
            ),
            17 => 
            array (
                'id' => 18,
                'name' => '100Mbit',
            ),
        ));
        
        
    }
}
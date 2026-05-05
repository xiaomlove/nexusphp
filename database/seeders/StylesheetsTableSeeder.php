<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class StylesheetsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('stylesheets')->delete();
        
        \DB::table('stylesheets')->insert(array (
            0 => 
            array (
                'id' => 2,
                'uri' => 'styles/BlueGene/',
                'name' => 'Blue Gene',
                'addicode' => '',
                'designer' => 'Zantetsu',
                'comment' => 'HDBits clone',
            ),
            1 => 
            array (
                'id' => 3,
                'uri' => 'styles/BlasphemyOrange/',
                'name' => 'Blasphemy Orange',
                'addicode' => '',
                'designer' => 'Zantetsu',
                'comment' => 'Bit-HDTV clone',
            ),
            2 => 
            array (
                'id' => 4,
                'uri' => 'styles/Classic/',
                'name' => 'Classic',
                'addicode' => '',
                'designer' => 'Zantetsu',
                'comment' => 'TBSource original mod',
            ),
            3 => 
            array (
                'id' => 6,
                'uri' => 'styles/DarkPassion/',
                'name' => 'Dark Passion',
                'addicode' => '',
                'designer' => 'Zantetsu',
                'comment' => '',
            ),
            4 => 
            array (
                'id' => 7,
                'uri' => 'styles/BambooGreen/',
                'name' => 'Bamboo Green',
                'addicode' => '',
                'designer' => 'Xia Zuojie',
                'comment' => 'Baidu Hi clone',
            ),
        ));
        
        
    }
}
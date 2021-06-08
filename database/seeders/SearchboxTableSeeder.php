<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SearchboxTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('searchbox')->delete();
        
        \DB::table('searchbox')->insert(array (
            0 => 
            array (
                'id' => 4,
                'name' => 'chd',
                'showsubcat' => 1,
                'showsource' => 0,
                'showmedium' => 1,
                'showcodec' => 1,
                'showstandard' => 1,
                'showprocessing' => 0,
                'showteam' => 1,
                'showaudiocodec' => 0,
                'catsperrow' => 10,
                'catpadding' => 7,
                'custom_fields' => '',
                'custom_fields_display_name' => '',
                'custom_fields_display' => '',
            ),
        ));
        
        
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CodecsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('codecs')->delete();
        
        \DB::table('codecs')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'H.264',
                'sort_index' => 0,
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'VC-1',
                'sort_index' => 0,
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'Xvid',
                'sort_index' => 0,
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'MPEG-2',
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
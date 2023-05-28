<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProcessingsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('processings')->delete();
        
        \DB::table('processings')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'Raw',
                'sort_index' => 0,
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'Encode',
                'sort_index' => 0,
            ),
        ));
        
        
    }
}
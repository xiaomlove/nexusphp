<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class TorrentsStateTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('torrents_state')->delete();
        
        \DB::table('torrents_state')->insert(array (
            0 => 
            array (
                'global_sp_state' => 1,
            ),
        ));
        
        
    }
}
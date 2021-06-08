<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class BannedemailsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('bannedemails')->delete();
        
        \DB::table('bannedemails')->insert(array (
            0 => 
            array (
                'id' => 1,
                'value' => '@test.com',
            ),
        ));
        
        
    }
}
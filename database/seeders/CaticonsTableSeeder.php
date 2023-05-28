<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CaticonsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('caticons')->delete();
        
        \DB::table('caticons')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'SceneTorrents mod',
                'folder' => 'scenetorrents/',
                'cssfile' => 'pic/category/chd/scenetorrents/catsprites.css',
                'multilang' => 'yes',
                'secondicon' => 'no',
                'designer' => 'NexusPHP',
                'comment' => 'Modified from SceneTorrents',
            ),
        ));
        
        
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AllowedemailsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('allowedemails')->delete();
        
        \DB::table('allowedemails')->insert(array (
            0 => 
            array (
                'id' => 1,
                'value' => '@st.zju.edu.cn @gstu.zju.edu.cn @fa.zju.edu.cn @zuaa.zju.edu.cn',
            ),
        ));
        
        
    }
}
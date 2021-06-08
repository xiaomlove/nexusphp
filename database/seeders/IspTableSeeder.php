<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class IspTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('isp')->delete();
        
        \DB::table('isp')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => '中国电信',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => '中国网通',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => '中国铁通',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => '中国移动',
            ),
            4 => 
            array (
                'id' => 5,
                'name' => '中国联通',
            ),
            5 => 
            array (
                'id' => 6,
                'name' => '中国教育网',
            ),
            6 => 
            array (
                'id' => 20,
                'name' => 'Other',
            ),
        ));
        
        
    }
}
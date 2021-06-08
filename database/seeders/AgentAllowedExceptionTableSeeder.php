<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AgentAllowedExceptionTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('agent_allowed_exception')->delete();
        
        \DB::table('agent_allowed_exception')->insert(array (
            0 => 
            array (
                'family_id' => 16,
            'name' => 'uTorrent 1.80B (Build 6838)',
                'peer_id' => '-UT180B-',
            'agent' => 'uTorrent/180B(6838)',
                'comment' => 'buggy build that always seeding bad request',
            ),
        ));
        
        
    }
}
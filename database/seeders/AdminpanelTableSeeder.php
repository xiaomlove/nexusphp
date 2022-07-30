<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AdminpanelTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {


        \DB::table('adminpanel')->delete();

        \DB::table('adminpanel')->insert(array (
            0 =>
            array (
                'id' => 1,
                'name' => 'Add user',
                'url' => 'adduser.php',
                'info' => 'Create new user account',
            ),
            1 =>
            array (
                'id' => 3,
                'name' => 'Reset Users Password',
                'url' => 'reset.php',
                'info' => 'Rest lost Passwords',
            ),
            2 =>
            array (
                'id' => 4,
                'name' => 'Mass PM',
                'url' => 'staffmess.php',
                'info' => 'Send PM to all users',
            ),
            3 =>
            array (
                'id' => 6,
                'name' => 'Poll overview',
                'url' => 'polloverview.php',
                'info' => 'View poll votes',
            ),
            4 =>
            array (
                'id' => 7,
                'name' => 'Warned users',
                'url' => 'warned.php',
                'info' => 'See all warned users on tracker',
            ),
//            5 =>
//            array (
//                'id' => 8,
//                'name' => 'FreeLeech',
//                'url' => 'freeleech.php',
//                'info' => 'Set ALL Torrents At Special State.',
//            ),
            6 =>
            array (
                'id' => 9,
                'name' => 'FAQ Management',
                'url' => 'faqmanage.php',
                'info' => 'Edit/Add/Delete FAQ Page',
            ),
            7 =>
            array (
                'id' => 10,
                'name' => 'Rules Management',
                'url' => 'modrules.php',
                'info' => 'Edit/Add/Delete RULES Page',
            ),
            8 =>
            array (
                'id' => 11,
                'name' => 'Category Manage',
                'url' => 'catmanage.php',
                'info' => 'Manage torrents categories at your site',
            ),
            9 =>
            array (
                'id' => 12,
                'name' => 'Custom Field Manage',
                'url' => 'fields.php',
                'info' => 'Manage custom fields',
            ),
        ));


    }
}

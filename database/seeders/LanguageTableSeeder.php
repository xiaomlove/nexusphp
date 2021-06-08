<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class LanguageTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('language')->delete();
        
        \DB::table('language')->insert(array (
            0 => 
            array (
                'id' => 1,
                'lang_name' => 'Bulgarian',
                'flagpic' => 'bulgaria.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            1 => 
            array (
                'id' => 2,
                'lang_name' => 'Croatian',
                'flagpic' => 'croatia.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            2 => 
            array (
                'id' => 3,
                'lang_name' => 'Czech',
                'flagpic' => 'czechrep.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            3 => 
            array (
                'id' => 4,
                'lang_name' => 'Danish',
                'flagpic' => 'denmark.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            4 => 
            array (
                'id' => 5,
                'lang_name' => 'Dutch',
                'flagpic' => 'netherlands.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            5 => 
            array (
                'id' => 6,
                'lang_name' => 'English',
                'flagpic' => 'uk.gif',
                'sub_lang' => 1,
                'rule_lang' => 1,
                'site_lang' => 1,
                'site_lang_folder' => 'en',
                'trans_state' => 'up-to-date',
            ),
            6 => 
            array (
                'id' => 7,
                'lang_name' => 'Estonian',
                'flagpic' => 'estonia.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            7 => 
            array (
                'id' => 8,
                'lang_name' => 'Finnish',
                'flagpic' => 'finland.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            8 => 
            array (
                'id' => 9,
                'lang_name' => 'French',
                'flagpic' => 'france.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            9 => 
            array (
                'id' => 10,
                'lang_name' => 'German',
                'flagpic' => 'germany.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            10 => 
            array (
                'id' => 11,
                'lang_name' => 'Greek',
                'flagpic' => 'greece.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            11 => 
            array (
                'id' => 12,
                'lang_name' => 'Hebrew',
                'flagpic' => 'israel.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            12 => 
            array (
                'id' => 13,
                'lang_name' => 'Hungarian',
                'flagpic' => 'hungary.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            13 => 
            array (
                'id' => 14,
                'lang_name' => 'Italian',
                'flagpic' => 'italy.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            14 => 
            array (
                'id' => 15,
                'lang_name' => '日本語',
                'flagpic' => 'japan.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            15 => 
            array (
                'id' => 16,
                'lang_name' => '한국어',
                'flagpic' => 'southkorea.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => 'ko',
                'trans_state' => 'unavailable',
            ),
            16 => 
            array (
                'id' => 17,
                'lang_name' => 'Norwegian',
                'flagpic' => 'norway.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            17 => 
            array (
                'id' => 18,
                'lang_name' => 'Other',
                'flagpic' => 'jollyroger.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            18 => 
            array (
                'id' => 19,
                'lang_name' => 'Polish',
                'flagpic' => 'poland.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            19 => 
            array (
                'id' => 20,
                'lang_name' => 'Portuguese',
                'flagpic' => 'portugal.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            20 => 
            array (
                'id' => 21,
                'lang_name' => 'Romanian',
                'flagpic' => 'romania.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            21 => 
            array (
                'id' => 22,
                'lang_name' => 'Russian',
                'flagpic' => 'russia.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            22 => 
            array (
                'id' => 23,
                'lang_name' => 'Serbian',
                'flagpic' => 'serbia.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            23 => 
            array (
                'id' => 24,
                'lang_name' => 'Slovak',
                'flagpic' => 'slovakia.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            24 => 
            array (
                'id' => 25,
                'lang_name' => '简体中文',
                'flagpic' => 'china.gif',
                'sub_lang' => 1,
                'rule_lang' => 1,
                'site_lang' => 1,
                'site_lang_folder' => 'chs',
                'trans_state' => 'up-to-date',
            ),
            25 => 
            array (
                'id' => 26,
                'lang_name' => 'Spanish',
                'flagpic' => 'spain.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            26 => 
            array (
                'id' => 27,
                'lang_name' => 'Swedish',
                'flagpic' => 'sweden.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            27 => 
            array (
                'id' => 28,
                'lang_name' => '繁體中文',
                'flagpic' => 'hongkong.gif',
                'sub_lang' => 1,
                'rule_lang' => 1,
                'site_lang' => 1,
                'site_lang_folder' => 'cht',
                'trans_state' => 'up-to-date',
            ),
            28 => 
            array (
                'id' => 29,
                'lang_name' => 'Turkish',
                'flagpic' => 'turkey.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            29 => 
            array (
                'id' => 30,
                'lang_name' => 'Slovenian',
                'flagpic' => 'slovenia.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => '',
                'trans_state' => 'unavailable',
            ),
            30 => 
            array (
                'id' => 31,
                'lang_name' => 'Thai',
                'flagpic' => 'thailand.gif',
                'sub_lang' => 1,
                'rule_lang' => 0,
                'site_lang' => 0,
                'site_lang_folder' => 'th',
                'trans_state' => 'unavailable',
            ),
        ));
        
        
    }
}
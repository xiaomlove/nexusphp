<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CategoriesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('categories')->delete();
        
        \DB::table('categories')->insert(array (
            0 => 
            array (
                'id' => 401,
                'mode' => 4,
                'class_name' => 'c_movies',
                'name' => 'Movies',
                'image' => 'catsprites.png',
                'sort_index' => 0,
                'icon_id' => 1,
            ),
            1 => 
            array (
                'id' => 402,
                'mode' => 4,
                'class_name' => 'c_tvseries',
                'name' => 'TV Series',
                'image' => 'catsprites.png',
                'sort_index' => 3,
                'icon_id' => 1,
            ),
            2 => 
            array (
                'id' => 403,
                'mode' => 4,
                'class_name' => 'c_tvshows',
                'name' => 'TV Shows',
                'image' => 'catsprites.png',
                'sort_index' => 4,
                'icon_id' => 1,
            ),
            3 => 
            array (
                'id' => 404,
                'mode' => 4,
                'class_name' => 'c_doc',
                'name' => 'Documentaries',
                'image' => 'catsprites.png',
                'sort_index' => 1,
                'icon_id' => 1,
            ),
            4 => 
            array (
                'id' => 405,
                'mode' => 4,
                'class_name' => 'c_anime',
                'name' => 'Animations',
                'image' => 'catsprites.png',
                'sort_index' => 2,
                'icon_id' => 1,
            ),
            5 => 
            array (
                'id' => 406,
                'mode' => 4,
                'class_name' => 'c_mv',
                'name' => 'Music Videos',
                'image' => 'catsprites.png',
                'sort_index' => 5,
                'icon_id' => 1,
            ),
            6 => 
            array (
                'id' => 407,
                'mode' => 4,
                'class_name' => 'c_sports',
                'name' => 'Sports',
                'image' => 'catsprites.png',
                'sort_index' => 6,
                'icon_id' => 1,
            ),
            7 => 
            array (
                'id' => 408,
                'mode' => 4,
                'class_name' => 'c_hqaudio',
                'name' => 'HQ Audio',
                'image' => 'catsprites.png',
                'sort_index' => 8,
                'icon_id' => 1,
            ),
            8 => 
            array (
                'id' => 409,
                'mode' => 4,
                'class_name' => 'c_misc',
                'name' => 'Misc',
                'image' => 'catsprites.png',
                'sort_index' => 7,
                'icon_id' => 1,
            ),
        ));
        
        
    }
}
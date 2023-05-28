<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SecondiconsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('secondicons')->delete();
        
        \DB::table('secondicons')->insert(array (
            0 => 
            array (
                'id' => 1,
                'source' => 0,
                'medium' => 1,
                'codec' => 1,
                'standard' => 0,
                'processing' => 0,
                'team' => 0,
                'audiocodec' => 0,
                'name' => 'Blu-ray/H.264',
                'class_name' => NULL,
                'image' => 'bdh264.png',
            ),
            1 => 
            array (
                'id' => 2,
                'source' => 0,
                'medium' => 1,
                'codec' => 2,
                'standard' => 0,
                'processing' => 0,
                'team' => 0,
                'audiocodec' => 0,
                'name' => 'Blu-ray/VC-1',
                'class_name' => NULL,
                'image' => 'bdvc1.png',
            ),
            2 => 
            array (
                'id' => 3,
                'source' => 0,
                'medium' => 1,
                'codec' => 4,
                'standard' => 0,
                'processing' => 0,
                'team' => 0,
                'audiocodec' => 0,
                'name' => 'Blu-ray/MPEG-2',
                'class_name' => NULL,
                'image' => 'bdmpeg2.png',
            ),
            3 => 
            array (
                'id' => 4,
                'source' => 0,
                'medium' => 2,
                'codec' => 1,
                'standard' => 0,
                'processing' => 0,
                'team' => 0,
                'audiocodec' => 0,
                'name' => 'HD DVD/H.264',
                'class_name' => NULL,
                'image' => 'hddvdh264.png',
            ),
            4 => 
            array (
                'id' => 5,
                'source' => 0,
                'medium' => 2,
                'codec' => 2,
                'standard' => 0,
                'processing' => 0,
                'team' => 0,
                'audiocodec' => 0,
                'name' => 'HD DVD/VC-1',
                'class_name' => NULL,
                'image' => 'hddvdvc1.png',
            ),
            5 => 
            array (
                'id' => 6,
                'source' => 0,
                'medium' => 2,
                'codec' => 4,
                'standard' => 0,
                'processing' => 0,
                'team' => 0,
                'audiocodec' => 0,
                'name' => 'HD DVD/MPEG-2',
                'class_name' => NULL,
                'image' => 'hddvdmpeg2.png',
            ),
            6 => 
            array (
                'id' => 7,
                'source' => 0,
                'medium' => 3,
                'codec' => 1,
                'standard' => 0,
                'processing' => 0,
                'team' => 0,
                'audiocodec' => 0,
                'name' => 'Remux/H.264',
                'class_name' => NULL,
                'image' => 'remuxh264.png',
            ),
            7 => 
            array (
                'id' => 8,
                'source' => 0,
                'medium' => 3,
                'codec' => 2,
                'standard' => 0,
                'processing' => 0,
                'team' => 0,
                'audiocodec' => 0,
                'name' => 'Remux/VC-1',
                'class_name' => NULL,
                'image' => 'remuxvc1.png',
            ),
            8 => 
            array (
                'id' => 9,
                'source' => 0,
                'medium' => 3,
                'codec' => 4,
                'standard' => 0,
                'processing' => 0,
                'team' => 0,
                'audiocodec' => 0,
                'name' => 'Remux/MPEG-2',
                'class_name' => NULL,
                'image' => 'remuxmpeg2.png',
            ),
            9 => 
            array (
                'id' => 10,
                'source' => 0,
                'medium' => 4,
                'codec' => 0,
                'standard' => 0,
                'processing' => 0,
                'team' => 0,
                'audiocodec' => 0,
                'name' => 'AVCHD',
                'class_name' => NULL,
                'image' => 'avchd.png',
            ),
            10 => 
            array (
                'id' => 11,
                'source' => 0,
                'medium' => 5,
                'codec' => 1,
                'standard' => 0,
                'processing' => 0,
                'team' => 0,
                'audiocodec' => 0,
                'name' => 'HDTV/H.264',
                'class_name' => NULL,
                'image' => 'hdtvh264.png',
            ),
            11 => 
            array (
                'id' => 12,
                'source' => 0,
                'medium' => 5,
                'codec' => 4,
                'standard' => 0,
                'processing' => 0,
                'team' => 0,
                'audiocodec' => 0,
                'name' => 'HDTV/MPEG-2',
                'class_name' => NULL,
                'image' => 'hdtvmpeg2.png',
            ),
            12 => 
            array (
                'id' => 13,
                'source' => 0,
                'medium' => 6,
                'codec' => 0,
                'standard' => 0,
                'processing' => 0,
                'team' => 0,
                'audiocodec' => 0,
                'name' => 'DVDR',
                'class_name' => NULL,
                'image' => 'dvdr.png',
            ),
            13 => 
            array (
                'id' => 14,
                'source' => 0,
                'medium' => 7,
                'codec' => 1,
                'standard' => 0,
                'processing' => 0,
                'team' => 0,
                'audiocodec' => 0,
                'name' => 'Rip/H.264',
                'class_name' => NULL,
                'image' => 'riph264.png',
            ),
            14 => 
            array (
                'id' => 15,
                'source' => 0,
                'medium' => 7,
                'codec' => 3,
                'standard' => 0,
                'processing' => 0,
                'team' => 0,
                'audiocodec' => 0,
                'name' => 'Rip/Xvid',
                'class_name' => NULL,
                'image' => 'ripxvid.png',
            ),
            15 => 
            array (
                'id' => 16,
                'source' => 0,
                'medium' => 8,
                'codec' => 5,
                'standard' => 0,
                'processing' => 0,
                'team' => 0,
                'audiocodec' => 0,
                'name' => 'CD/FLAC',
                'class_name' => NULL,
                'image' => 'cdflac.png',
            ),
            16 => 
            array (
                'id' => 17,
                'source' => 0,
                'medium' => 8,
                'codec' => 6,
                'standard' => 0,
                'processing' => 0,
                'team' => 0,
                'audiocodec' => 0,
                'name' => 'CD/APE',
                'class_name' => NULL,
                'image' => 'cdape.png',
            ),
            17 => 
            array (
                'id' => 18,
                'source' => 0,
                'medium' => 8,
                'codec' => 7,
                'standard' => 0,
                'processing' => 0,
                'team' => 0,
                'audiocodec' => 0,
                'name' => 'CD/DTS',
                'class_name' => NULL,
                'image' => 'cddts.png',
            ),
            18 => 
            array (
                'id' => 19,
                'source' => 0,
                'medium' => 8,
                'codec' => 9,
                'standard' => 0,
                'processing' => 0,
                'team' => 0,
                'audiocodec' => 0,
                'name' => 'CD/Other',
                'class_name' => NULL,
                'image' => 'cdother.png',
            ),
            19 => 
            array (
                'id' => 20,
                'source' => 0,
                'medium' => 9,
                'codec' => 5,
                'standard' => 0,
                'processing' => 0,
                'team' => 0,
                'audiocodec' => 0,
                'name' => 'Extract/FLAC',
                'class_name' => NULL,
                'image' => 'extractflac.png',
            ),
            20 => 
            array (
                'id' => 21,
                'source' => 0,
                'medium' => 9,
                'codec' => 7,
                'standard' => 0,
                'processing' => 0,
                'team' => 0,
                'audiocodec' => 0,
                'name' => 'Extract/DTS',
                'class_name' => NULL,
                'image' => 'extractdts.png',
            ),
            21 => 
            array (
                'id' => 22,
                'source' => 0,
                'medium' => 9,
                'codec' => 8,
                'standard' => 0,
                'processing' => 0,
                'team' => 0,
                'audiocodec' => 0,
                'name' => 'Extract/AC-3',
                'class_name' => NULL,
                'image' => 'extractac3.png',
            ),
        ));
        
        
    }
}
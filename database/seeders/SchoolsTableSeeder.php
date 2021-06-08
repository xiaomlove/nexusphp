<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SchoolsTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('schools')->delete();
        
        \DB::table('schools')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => '南京农业大学',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => '中山大学',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => '中国石油大学',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => '云南大学',
            ),
            4 => 
            array (
                'id' => 5,
                'name' => '河海大学',
            ),
            5 => 
            array (
                'id' => 6,
                'name' => '南开大学',
            ),
            6 => 
            array (
                'id' => 7,
                'name' => '兰州大学',
            ),
            7 => 
            array (
                'id' => 8,
                'name' => '合肥工业大学',
            ),
            8 => 
            array (
                'id' => 9,
                'name' => '上海大学',
            ),
            9 => 
            array (
                'id' => 10,
                'name' => '安徽大学',
            ),
            10 => 
            array (
                'id' => 11,
                'name' => '中国海洋大学',
            ),
            11 => 
            array (
                'id' => 12,
                'name' => '复旦大学',
            ),
            12 => 
            array (
                'id' => 13,
                'name' => '西北大学',
            ),
            13 => 
            array (
                'id' => 14,
                'name' => '郑州大学',
            ),
            14 => 
            array (
                'id' => 15,
                'name' => '四川大学',
            ),
            15 => 
            array (
                'id' => 16,
                'name' => '华中科技大学',
            ),
            16 => 
            array (
                'id' => 17,
                'name' => '天津大学',
            ),
            17 => 
            array (
                'id' => 18,
                'name' => '山东大学',
            ),
            18 => 
            array (
                'id' => 19,
                'name' => '中央民族大学',
            ),
            19 => 
            array (
                'id' => 20,
                'name' => '苏州大学',
            ),
            20 => 
            array (
                'id' => 21,
                'name' => '重庆大学',
            ),
            21 => 
            array (
                'id' => 22,
                'name' => '东北农业大学',
            ),
            22 => 
            array (
                'id' => 23,
                'name' => '北京工业大学',
            ),
            23 => 
            array (
                'id' => 24,
                'name' => '湖南师范大学',
            ),
            24 => 
            array (
                'id' => 25,
                'name' => '东北大学',
            ),
            25 => 
            array (
                'id' => 26,
                'name' => '电子科技大学',
            ),
            26 => 
            array (
                'id' => 27,
                'name' => '西安电子科技大学',
            ),
            27 => 
            array (
                'id' => 28,
                'name' => '北京化工大学',
            ),
            28 => 
            array (
                'id' => 29,
                'name' => '南京航空航天大学',
            ),
            29 => 
            array (
                'id' => 30,
                'name' => '南京理工大学',
            ),
            30 => 
            array (
                'id' => 31,
                'name' => '西北工业大学',
            ),
            31 => 
            array (
                'id' => 32,
                'name' => '天津医科大学',
            ),
            32 => 
            array (
                'id' => 33,
                'name' => '北京林业大学',
            ),
            33 => 
            array (
                'id' => 34,
                'name' => '华南师范大学',
            ),
            34 => 
            array (
                'id' => 35,
                'name' => '浙江大学',
            ),
            35 => 
            array (
                'id' => 36,
                'name' => '长安大学',
            ),
            36 => 
            array (
                'id' => 37,
                'name' => '武汉理工大学',
            ),
            37 => 
            array (
                'id' => 38,
                'name' => '河北工业大学',
            ),
            38 => 
            array (
                'id' => 39,
                'name' => '南京师范大学',
            ),
            39 => 
            array (
                'id' => 40,
                'name' => '中国农业大学',
            ),
            40 => 
            array (
                'id' => 41,
                'name' => '厦门大学',
            ),
            41 => 
            array (
                'id' => 42,
                'name' => '第二军医大学',
            ),
            42 => 
            array (
                'id' => 43,
                'name' => '北京理工大学',
            ),
            43 => 
            array (
                'id' => 44,
                'name' => '北京大学',
            ),
            44 => 
            array (
                'id' => 45,
                'name' => '上海外国语大学',
            ),
            45 => 
            array (
                'id' => 46,
                'name' => '北京科技大学',
            ),
            46 => 
            array (
                'id' => 47,
                'name' => '西北农林科技大学',
            ),
            47 => 
            array (
                'id' => 48,
                'name' => '中南大学',
            ),
            48 => 
            array (
                'id' => 49,
                'name' => '华南理工大学',
            ),
            49 => 
            array (
                'id' => 50,
                'name' => '武汉大学',
            ),
            50 => 
            array (
                'id' => 51,
                'name' => '福州大学',
            ),
            51 => 
            array (
                'id' => 52,
                'name' => '同济大学',
            ),
            52 => 
            array (
                'id' => 53,
                'name' => '中国传媒大学',
            ),
            53 => 
            array (
                'id' => 54,
                'name' => '湖南大学',
            ),
            54 => 
            array (
                'id' => 55,
                'name' => '上海财经大学',
            ),
            55 => 
            array (
                'id' => 56,
                'name' => '国防科学技术大学',
            ),
            56 => 
            array (
                'id' => 57,
                'name' => '吉林大学',
            ),
            57 => 
            array (
                'id' => 58,
                'name' => '大连理工大学',
            ),
            58 => 
            array (
                'id' => 59,
                'name' => '中国人民大学',
            ),
            59 => 
            array (
                'id' => 60,
                'name' => '上海交通大学',
            ),
            60 => 
            array (
                'id' => 61,
                'name' => '西安交通大学',
            ),
            61 => 
            array (
                'id' => 62,
                'name' => '江南大学',
            ),
            62 => 
            array (
                'id' => 63,
                'name' => '南京大学',
            ),
            63 => 
            array (
                'id' => 64,
                'name' => '南昌大学',
            ),
            64 => 
            array (
                'id' => 65,
                'name' => '太原理工大学',
            ),
            65 => 
            array (
                'id' => 66,
                'name' => '中国地质大学',
            ),
            66 => 
            array (
                'id' => 67,
                'name' => '清华大学',
            ),
            67 => 
            array (
                'id' => 68,
                'name' => '广西大学',
            ),
            68 => 
            array (
                'id' => 69,
                'name' => '中国矿业大学',
            ),
            69 => 
            array (
                'id' => 70,
                'name' => '四川农业大学',
            ),
            70 => 
            array (
                'id' => 71,
                'name' => '东北师范大学',
            ),
            71 => 
            array (
                'id' => 72,
                'name' => '哈尔滨工业大学',
            ),
            72 => 
            array (
                'id' => 73,
                'name' => '北京航空航天大学',
            ),
            73 => 
            array (
                'id' => 74,
                'name' => '北京交通大学',
            ),
            74 => 
            array (
                'id' => 75,
                'name' => '西南交通大学',
            ),
            75 => 
            array (
                'id' => 76,
                'name' => '中国科学技术大学',
            ),
            76 => 
            array (
                'id' => 77,
                'name' => '北京外国语大学',
            ),
            77 => 
            array (
                'id' => 78,
                'name' => '北京邮电大学',
            ),
            78 => 
            array (
                'id' => 79,
                'name' => '西安建筑科技大学',
            ),
            79 => 
            array (
                'id' => 80,
                'name' => '新疆大学',
            ),
            80 => 
            array (
                'id' => 81,
                'name' => '东南大学',
            ),
            81 => 
            array (
                'id' => 82,
                'name' => '对外经济贸易大学',
            ),
            82 => 
            array (
                'id' => 83,
                'name' => '北京中医药大学',
            ),
            83 => 
            array (
                'id' => 84,
                'name' => '暨南大学',
            ),
            84 => 
            array (
                'id' => 85,
                'name' => '北京语言大学',
            ),
            85 => 
            array (
                'id' => 86,
                'name' => '华中师范大学',
            ),
            86 => 
            array (
                'id' => 87,
                'name' => '北京师范大学',
            ),
            87 => 
            array (
                'id' => 88,
                'name' => '哈尔滨工程大学',
            ),
            88 => 
            array (
                'id' => 89,
                'name' => '内蒙古大学',
            ),
            89 => 
            array (
                'id' => 90,
                'name' => '东华大学',
            ),
            90 => 
            array (
                'id' => 91,
                'name' => '解放军信息工程大学',
            ),
            91 => 
            array (
                'id' => 92,
                'name' => '上海交通大学医学院 ',
            ),
            92 => 
            array (
                'id' => 93,
                'name' => '华东理工大学',
            ),
            93 => 
            array (
                'id' => 94,
                'name' => '第四军医大学',
            ),
            94 => 
            array (
                'id' => 95,
                'name' => '大连海事大学',
            ),
            95 => 
            array (
                'id' => 96,
                'name' => '华东师范大学',
            ),
            96 => 
            array (
                'id' => 97,
                'name' => '辽宁大学',
            ),
            97 => 
            array (
                'id' => 98,
                'name' => '深圳大学',
            ),
            98 => 
            array (
                'id' => 99,
                'name' => '中央音乐学院',
            ),
            99 => 
            array (
                'id' => 100,
                'name' => '中国协和医科大学',
            ),
        ));
        
        
    }
}
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CountriesTableSeeder extends Seeder
{

    /**
     * Auto generated seed file
     *
     * @return void
     */
    public function run()
    {
        

        \DB::table('countries')->delete();
        
        \DB::table('countries')->insert(array (
            0 => 
            array (
                'id' => 1,
                'name' => 'Sweden',
                'flagpic' => 'sweden.gif',
            ),
            1 => 
            array (
                'id' => 2,
                'name' => 'United States of America',
                'flagpic' => 'usa.gif',
            ),
            2 => 
            array (
                'id' => 3,
                'name' => 'Russia',
                'flagpic' => 'russia.gif',
            ),
            3 => 
            array (
                'id' => 4,
                'name' => 'Finland',
                'flagpic' => 'finland.gif',
            ),
            4 => 
            array (
                'id' => 5,
                'name' => 'Canada',
                'flagpic' => 'canada.gif',
            ),
            5 => 
            array (
                'id' => 6,
                'name' => 'France',
                'flagpic' => 'france.gif',
            ),
            6 => 
            array (
                'id' => 7,
                'name' => 'Germany',
                'flagpic' => 'germany.gif',
            ),
            7 => 
            array (
                'id' => 8,
                'name' => '中国',
                'flagpic' => 'china.gif',
            ),
            8 => 
            array (
                'id' => 9,
                'name' => 'Italy',
                'flagpic' => 'italy.gif',
            ),
            9 => 
            array (
                'id' => 10,
                'name' => 'Denmark',
                'flagpic' => 'denmark.gif',
            ),
            10 => 
            array (
                'id' => 11,
                'name' => 'Norway',
                'flagpic' => 'norway.gif',
            ),
            11 => 
            array (
                'id' => 12,
                'name' => 'United Kingdom',
                'flagpic' => 'uk.gif',
            ),
            12 => 
            array (
                'id' => 13,
                'name' => 'Ireland',
                'flagpic' => 'ireland.gif',
            ),
            13 => 
            array (
                'id' => 14,
                'name' => 'Poland',
                'flagpic' => 'poland.gif',
            ),
            14 => 
            array (
                'id' => 15,
                'name' => 'Netherlands',
                'flagpic' => 'netherlands.gif',
            ),
            15 => 
            array (
                'id' => 16,
                'name' => 'Belgium',
                'flagpic' => 'belgium.gif',
            ),
            16 => 
            array (
                'id' => 17,
                'name' => 'Japan',
                'flagpic' => 'japan.gif',
            ),
            17 => 
            array (
                'id' => 18,
                'name' => 'Brazil',
                'flagpic' => 'brazil.gif',
            ),
            18 => 
            array (
                'id' => 19,
                'name' => 'Argentina',
                'flagpic' => 'argentina.gif',
            ),
            19 => 
            array (
                'id' => 20,
                'name' => 'Australia',
                'flagpic' => 'australia.gif',
            ),
            20 => 
            array (
                'id' => 21,
                'name' => 'New Zealand',
                'flagpic' => 'newzealand.gif',
            ),
            21 => 
            array (
                'id' => 23,
                'name' => 'Spain',
                'flagpic' => 'spain.gif',
            ),
            22 => 
            array (
                'id' => 24,
                'name' => 'Portugal',
                'flagpic' => 'portugal.gif',
            ),
            23 => 
            array (
                'id' => 25,
                'name' => 'Mexico',
                'flagpic' => 'mexico.gif',
            ),
            24 => 
            array (
                'id' => 26,
                'name' => 'Singapore',
                'flagpic' => 'singapore.gif',
            ),
            25 => 
            array (
                'id' => 70,
                'name' => 'India',
                'flagpic' => 'india.gif',
            ),
            26 => 
            array (
                'id' => 65,
                'name' => 'Albania',
                'flagpic' => 'albania.gif',
            ),
            27 => 
            array (
                'id' => 29,
                'name' => 'South Africa',
                'flagpic' => 'southafrica.gif',
            ),
            28 => 
            array (
                'id' => 30,
                'name' => 'South Korea',
                'flagpic' => 'southkorea.gif',
            ),
            29 => 
            array (
                'id' => 31,
                'name' => 'Jamaica',
                'flagpic' => 'jamaica.gif',
            ),
            30 => 
            array (
                'id' => 32,
                'name' => 'Luxembourg',
                'flagpic' => 'luxembourg.gif',
            ),
            31 => 
            array (
                'id' => 34,
                'name' => 'Belize',
                'flagpic' => 'belize.gif',
            ),
            32 => 
            array (
                'id' => 35,
                'name' => 'Algeria',
                'flagpic' => 'algeria.gif',
            ),
            33 => 
            array (
                'id' => 36,
                'name' => 'Angola',
                'flagpic' => 'angola.gif',
            ),
            34 => 
            array (
                'id' => 37,
                'name' => 'Austria',
                'flagpic' => 'austria.gif',
            ),
            35 => 
            array (
                'id' => 38,
                'name' => 'Yugoslavia',
                'flagpic' => 'yugoslavia.gif',
            ),
            36 => 
            array (
                'id' => 39,
                'name' => 'Western Samoa',
                'flagpic' => 'westernsamoa.gif',
            ),
            37 => 
            array (
                'id' => 40,
                'name' => 'Malaysia',
                'flagpic' => 'malaysia.gif',
            ),
            38 => 
            array (
                'id' => 41,
                'name' => 'Dominican Republic',
                'flagpic' => 'dominicanrep.gif',
            ),
            39 => 
            array (
                'id' => 42,
                'name' => 'Greece',
                'flagpic' => 'greece.gif',
            ),
            40 => 
            array (
                'id' => 43,
                'name' => 'Guatemala',
                'flagpic' => 'guatemala.gif',
            ),
            41 => 
            array (
                'id' => 44,
                'name' => 'Israel',
                'flagpic' => 'israel.gif',
            ),
            42 => 
            array (
                'id' => 45,
                'name' => 'Pakistan',
                'flagpic' => 'pakistan.gif',
            ),
            43 => 
            array (
                'id' => 46,
                'name' => 'Czech Republic',
                'flagpic' => 'czechrep.gif',
            ),
            44 => 
            array (
                'id' => 47,
                'name' => 'Serbia',
                'flagpic' => 'serbia.gif',
            ),
            45 => 
            array (
                'id' => 48,
                'name' => 'Seychelles',
                'flagpic' => 'seychelles.gif',
            ),
            46 => 
            array (
                'id' => 50,
                'name' => 'Puerto Rico',
                'flagpic' => 'puertorico.gif',
            ),
            47 => 
            array (
                'id' => 51,
                'name' => 'Chile',
                'flagpic' => 'chile.gif',
            ),
            48 => 
            array (
                'id' => 52,
                'name' => 'Cuba',
                'flagpic' => 'cuba.gif',
            ),
            49 => 
            array (
                'id' => 53,
                'name' => 'Congo',
                'flagpic' => 'congo.gif',
            ),
            50 => 
            array (
                'id' => 54,
                'name' => 'Afghanistan',
                'flagpic' => 'afghanistan.gif',
            ),
            51 => 
            array (
                'id' => 55,
                'name' => 'Turkey',
                'flagpic' => 'turkey.gif',
            ),
            52 => 
            array (
                'id' => 56,
                'name' => 'Uzbekistan',
                'flagpic' => 'uzbekistan.gif',
            ),
            53 => 
            array (
                'id' => 57,
                'name' => 'Switzerland',
                'flagpic' => 'switzerland.gif',
            ),
            54 => 
            array (
                'id' => 58,
                'name' => 'Kiribati',
                'flagpic' => 'kiribati.gif',
            ),
            55 => 
            array (
                'id' => 59,
                'name' => 'Philippines',
                'flagpic' => 'philippines.gif',
            ),
            56 => 
            array (
                'id' => 60,
                'name' => 'Burkina Faso',
                'flagpic' => 'burkinafaso.gif',
            ),
            57 => 
            array (
                'id' => 61,
                'name' => 'Nigeria',
                'flagpic' => 'nigeria.gif',
            ),
            58 => 
            array (
                'id' => 62,
                'name' => 'Iceland',
                'flagpic' => 'iceland.gif',
            ),
            59 => 
            array (
                'id' => 63,
                'name' => 'Nauru',
                'flagpic' => 'nauru.gif',
            ),
            60 => 
            array (
                'id' => 64,
                'name' => 'Slovenia',
                'flagpic' => 'slovenia.gif',
            ),
            61 => 
            array (
                'id' => 66,
                'name' => 'Turkmenistan',
                'flagpic' => 'turkmenistan.gif',
            ),
            62 => 
            array (
                'id' => 67,
                'name' => 'Bosnia Herzegovina',
                'flagpic' => 'bosniaherzegovina.gif',
            ),
            63 => 
            array (
                'id' => 68,
                'name' => 'Andorra',
                'flagpic' => 'andorra.gif',
            ),
            64 => 
            array (
                'id' => 69,
                'name' => 'Lithuania',
                'flagpic' => 'lithuania.gif',
            ),
            65 => 
            array (
                'id' => 71,
                'name' => 'Netherlands Antilles',
                'flagpic' => 'nethantilles.gif',
            ),
            66 => 
            array (
                'id' => 72,
                'name' => 'Ukraine',
                'flagpic' => 'ukraine.gif',
            ),
            67 => 
            array (
                'id' => 73,
                'name' => 'Venezuela',
                'flagpic' => 'venezuela.gif',
            ),
            68 => 
            array (
                'id' => 74,
                'name' => 'Hungary',
                'flagpic' => 'hungary.gif',
            ),
            69 => 
            array (
                'id' => 75,
                'name' => 'Romania',
                'flagpic' => 'romania.gif',
            ),
            70 => 
            array (
                'id' => 76,
                'name' => 'Vanuatu',
                'flagpic' => 'vanuatu.gif',
            ),
            71 => 
            array (
                'id' => 77,
                'name' => 'Vietnam',
                'flagpic' => 'vietnam.gif',
            ),
            72 => 
            array (
                'id' => 78,
                'name' => 'Trinidad & Tobago',
                'flagpic' => 'trinidadandtobago.gif',
            ),
            73 => 
            array (
                'id' => 79,
                'name' => 'Honduras',
                'flagpic' => 'honduras.gif',
            ),
            74 => 
            array (
                'id' => 80,
                'name' => 'Kyrgyzstan',
                'flagpic' => 'kyrgyzstan.gif',
            ),
            75 => 
            array (
                'id' => 81,
                'name' => 'Ecuador',
                'flagpic' => 'ecuador.gif',
            ),
            76 => 
            array (
                'id' => 82,
                'name' => 'Bahamas',
                'flagpic' => 'bahamas.gif',
            ),
            77 => 
            array (
                'id' => 83,
                'name' => 'Peru',
                'flagpic' => 'peru.gif',
            ),
            78 => 
            array (
                'id' => 84,
                'name' => 'Cambodia',
                'flagpic' => 'cambodia.gif',
            ),
            79 => 
            array (
                'id' => 85,
                'name' => 'Barbados',
                'flagpic' => 'barbados.gif',
            ),
            80 => 
            array (
                'id' => 86,
                'name' => 'Bangladesh',
                'flagpic' => 'bangladesh.gif',
            ),
            81 => 
            array (
                'id' => 87,
                'name' => 'Laos',
                'flagpic' => 'laos.gif',
            ),
            82 => 
            array (
                'id' => 88,
                'name' => 'Uruguay',
                'flagpic' => 'uruguay.gif',
            ),
            83 => 
            array (
                'id' => 89,
                'name' => 'Antigua Barbuda',
                'flagpic' => 'antiguabarbuda.gif',
            ),
            84 => 
            array (
                'id' => 90,
                'name' => 'Paraguay',
                'flagpic' => 'paraguay.gif',
            ),
            85 => 
            array (
                'id' => 93,
                'name' => 'Thailand',
                'flagpic' => 'thailand.gif',
            ),
            86 => 
            array (
                'id' => 92,
                'name' => 'Union of Soviet Socialist Republics',
                'flagpic' => 'ussr.gif',
            ),
            87 => 
            array (
                'id' => 94,
                'name' => 'Senegal',
                'flagpic' => 'senegal.gif',
            ),
            88 => 
            array (
                'id' => 95,
                'name' => 'Togo',
                'flagpic' => 'togo.gif',
            ),
            89 => 
            array (
                'id' => 96,
                'name' => 'North Korea',
                'flagpic' => 'northkorea.gif',
            ),
            90 => 
            array (
                'id' => 97,
                'name' => 'Croatia',
                'flagpic' => 'croatia.gif',
            ),
            91 => 
            array (
                'id' => 98,
                'name' => 'Estonia',
                'flagpic' => 'estonia.gif',
            ),
            92 => 
            array (
                'id' => 99,
                'name' => 'Colombia',
                'flagpic' => 'colombia.gif',
            ),
            93 => 
            array (
                'id' => 100,
                'name' => 'Lebanon',
                'flagpic' => 'lebanon.gif',
            ),
            94 => 
            array (
                'id' => 101,
                'name' => 'Latvia',
                'flagpic' => 'latvia.gif',
            ),
            95 => 
            array (
                'id' => 102,
                'name' => 'Costa Rica',
                'flagpic' => 'costarica.gif',
            ),
            96 => 
            array (
                'id' => 103,
                'name' => 'Egypt',
                'flagpic' => 'egypt.gif',
            ),
            97 => 
            array (
                'id' => 104,
                'name' => 'Bulgaria',
                'flagpic' => 'bulgaria.gif',
            ),
            98 => 
            array (
                'id' => 105,
                'name' => 'Isla de Muerte',
                'flagpic' => 'jollyroger.gif',
            ),
            99 => 
            array (
                'id' => 107,
                'name' => 'Pirates',
                'flagpic' => 'jollyroger.gif',
            ),
        ));
        
        
    }
}
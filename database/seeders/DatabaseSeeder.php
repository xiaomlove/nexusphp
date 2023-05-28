<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(AdminpanelTableSeeder::class);
        $this->call(AgentAllowedExceptionTableSeeder::class);
        $this->call(AgentAllowedFamilyTableSeeder::class);
        $this->call(AllowedemailsTableSeeder::class);
        $this->call(AudiocodecsTableSeeder::class);
        $this->call(BannedemailsTableSeeder::class);
        $this->call(CategoriesTableSeeder::class);
        $this->call(CaticonsTableSeeder::class);
        $this->call(CodecsTableSeeder::class);
        $this->call(CountriesTableSeeder::class);
        $this->call(DownloadspeedTableSeeder::class);
        $this->call(FaqTableSeeder::class);
        $this->call(IspTableSeeder::class);
        $this->call(LanguageTableSeeder::class);
        $this->call(MediaTableSeeder::class);
        $this->call(ModpanelTableSeeder::class);
        $this->call(ProcessingsTableSeeder::class);
        $this->call(RulesTableSeeder::class);
        $this->call(SchoolsTableSeeder::class);
        $this->call(SearchboxTableSeeder::class);
        $this->call(SecondiconsTableSeeder::class);
        $this->call(SourcesTableSeeder::class);
        $this->call(StandardsTableSeeder::class);
        $this->call(StylesheetsTableSeeder::class);
        $this->call(SysoppanelTableSeeder::class);
        $this->call(TeamsTableSeeder::class);
        $this->call(TorrentsStateTableSeeder::class);
        $this->call(UploadspeedTableSeeder::class);
        $this->call(TagsTableSeeder::class);
    }
}

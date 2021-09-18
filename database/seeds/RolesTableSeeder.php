<?php

use Illuminate\Database\Seeder;
    use Illuminate\Support\Facades\DB;

    class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
        DB::table('roles')->truncate();

        DB::table('roles')->insert([
            [
                'name'           => 'administrator'
            ],
            [
                'name'           => 'tutor'
            ],
            [
                'name'           => 'user'
            ],
        ]);

        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }
}

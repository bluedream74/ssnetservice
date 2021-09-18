<?php

    use Carbon\Carbon;
    use Illuminate\Database\Seeder;
    use Illuminate\Support\Facades\DB;

    class UsersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
        DB::table('users')->truncate();

        DB::table('users')->insert([
            [
                'name'           => '管理者',
                'role_id'        => 1,
                'is_active'      => 1,
                'email'          => 'admin@admin.com',
                'password'       => Hash::make('admin123'),
                'avatar'         => null,
                'remember_token' => str_random(10),
                'created_at'     => Carbon::now()->subWeek(1),
                'updated_at'     => Carbon::now()->subWeek(1),
            ]
        ]);

        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }
}

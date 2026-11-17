<?php

namespace Database\Seeders;

use App\Console\Commands\SyncRoles;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Artisan::call(SyncRoles::class);

        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        if (! User::whereEmail('admin@example.com')->exists()) {
            $admin = User::factory()->create([
                'firstname' => 'Default',
                'lastname' => 'Admin',
                'email' => 'admin@example.com',
            ]);

            Artisan::call(SyncRoles::class, [
                'users' => [$admin->id],
                '--supes' => true,
                '--silent' => true,
                '--no-interaction' => true,
            ]);
        }
    }
}

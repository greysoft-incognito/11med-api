<?php

namespace Database\Seeders;

use App\Console\Commands\SyncRoles;
use App\Enums\Role;
use App\Models\User;
use App\Services\Staff\StaffCatalogue;
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
        $this->call(DrugClassSeeder::class);

        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        if (! User::whereEmail('admin@example.com')->exists()) {
            $admin = User::factory()->create([
                'firstname' => 'Default',
                'lastname' => 'Admin',
                'email' => 'admin@example.com',
            ]);

            $admin->assignRole(Role::Admin->value);
            $admin->assignRole(app(StaffCatalogue::class)->superAdmin());
        }
    }
}

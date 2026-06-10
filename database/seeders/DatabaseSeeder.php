<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Roles & permissions must exist before users can be assigned roles.
        $this->call(RolePermissionSeeder::class);

        if (! (bool) env('SEED_DEMO_USERS', ! app()->isProduction())) {
            return;
        }

        $superAdmin = User::updateOrCreate(['email' => 'superadmin@example.com'], [
            'name' => 'Super Admin',
            'password' => Hash::make('password'),
        ]);
        $superAdmin->syncRoles('SuperAdmin');

        $admin = User::updateOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Admin',
            'password' => Hash::make('password'),
        ]);
        $admin->syncRoles('Admin');

        $user = User::updateOrCreate(['email' => 'user@example.com'], [
            'name' => 'User',
            'password' => Hash::make('password'),
        ]);
        $user->syncRoles('User');

        $this->call(ProductSeeder::class);
    }
}

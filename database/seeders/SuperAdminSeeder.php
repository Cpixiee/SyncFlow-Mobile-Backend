<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\LoginUser;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create SuperAdmin user (updateOrCreate untuk idempotent seeding)
        LoginUser::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'password' => Hash::make('admin123'), // Password simple untuk testing
                'role' => 'superadmin',
                'photo_url' => 'https://ui-avatars.com/api/?name=Super+Admin&background=dc2626&color=ffffff',
                'employee_id' => 'SA001',
                'phone' => '+628123456789',
                'email' => 'superadmin@syncflow.com',
                'position' => 'manager',
                'department' => 'IT',
                'password_changed' => true, // SuperAdmin sudah ganti password
                'password_changed_at' => now(),
            ]
        );

        $this->command->info('SuperAdmin created successfully!');
        $this->command->info('Username: superadmin');
        $this->command->info('Password: admin123');
    }
}

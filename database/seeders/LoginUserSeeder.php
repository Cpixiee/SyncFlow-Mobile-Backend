<?php

namespace Database\Seeders;

use App\Models\LoginUser;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LoginUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create test users for different roles (skip superadmin as it's already created by SuperAdminSeeder)
        
        LoginUser::updateOrCreate(
            ['username' => 'admin'],
            [
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'photo_url' => 'https://example.com/photos/admin.jpg',
                'employee_id' => 'EMP002',
                'phone' => '+628123456788',
                'email' => 'admin@syncflow.com',
                'position' => 'supervisor',
                'department' => 'IT',
                'password_changed' => true,
                'password_changed_at' => now(),
            ]
        );

        LoginUser::updateOrCreate(
            ['username' => 'operator'],
            [
                'password' => Hash::make('admin123'),
                'role' => 'operator',
                'photo_url' => 'https://example.com/photos/operator.jpg',
                'employee_id' => 'EMP003',
                'phone' => '+628123456787',
                'email' => 'operator@syncflow.com',
                'position' => 'staff',
                'department' => 'Operations',
                'password_changed' => false, // Belum ganti password
            ]
        );

        LoginUser::updateOrCreate(
            ['username' => 'wit urrohman'],
            [
                'password' => Hash::make('admin123'),
                'role' => 'operator',
                'photo_url' => 'https://example.com/photos/pixiee.jpg',
                'employee_id' => '101233948893',
                'phone' => '+628123456789',
                'email' => 'salwit0109@gmail.com',
                'position' => 'manager',
                'department' => 'IT',
                'password_changed' => true,
                'password_changed_at' => now(),
            ]
        );
    }
}

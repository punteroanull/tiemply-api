<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get role IDs
        $adminRole = Role::where('name', 'Administrator')->first();
        $managerRole = Role::where('name', 'Manager')->first();
        $employeeRole = Role::where('name', 'Employee')->first();

        if (!$adminRole || !$managerRole || !$employeeRole) {
            $this->command->error('Required roles not found. Please run RoleSeeder first.');
            return;
        }

        // Create admin user
        User::updateOrCreate(
            ['email' => 'admin@tiemply.com'],
            [
                'name' => 'System Administrator',
                'email' => 'admin@tiemply.com',
                'password' => Hash::make('password'),
                'identification_number' => '12345678A',
                'birth_date' => '1980-01-01',
                'phone' => '600123456',
                'address' => 'Admin Street 1, City',
                'role_id' => $adminRole->id,
                'email_verified_at' => now(),
            ]
        );

        // Create manager user
        $manager = User::updateOrCreate(
            ['email' => 'manager@tiemply.com'],
            [
                'name' => 'Company Manager',
                'email' => 'manager@tiemply.com',
                'password' => Hash::make('password'),
                'identification_number' => '87654321B',
                'birth_date' => '1985-05-15',
                'phone' => '600654321',
                'address' => 'Manager Street 2, City',
                'role_id' => $managerRole->id,
                'email_verified_at' => now(),
            ]
        );

        // Store manager's ID for reference in CompanySeeder
        $this->command->info("Manager user created with ID: {$manager->id}");

        // Create 5 employee users
        for ($i = 1; $i <= 5; $i++) {
            $employee = User::updateOrCreate(
                ['email' => "employee{$i}@tiemply.com"],
                [
                    'name' => "Employee {$i}",
                    'email' => "employee{$i}@tiemply.com",
                    'password' => Hash::make('password'),
                    'identification_number' => "1234{$i}XYZ",
                    'birth_date' => fake()->date('Y-m-d', '-30 years'),
                    'phone' => fake()->phoneNumber(),
                    'address' => fake()->address(),
                    'role_id' => $employeeRole->id,
                    'email_verified_at' => now(),
                ]
            );

            $this->command->info("Employee user {$i} created with ID: {$employee->id}");
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
        /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create base roles
        $roles = [
            [
                'name' => 'Administrator',
                'description' => 'Full system administrator with all privileges',
                'is_exempt' => true,
                'access_level' => 5,
            ],
            [
                'name' => 'Manager',
                'description' => 'Company manager with employee management privileges',
                'is_exempt' => true,
                'access_level' => 4,
            ],
            [
                'name' => 'Employee',
                'description' => 'Regular employee with basic access',
                'is_exempt' => false,
                'access_level' => 1,
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                $role
            );
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Run the seeders in correct order to handle dependencies
        $this->call([
            RoleSeeder::class,           // First, create roles
            UserSeeder::class,           // Then, create users with roles
            CompanySeeder::class,        // Create companies
            EmployeeSeeder::class,       // Link users to companies as employees
            AbsenceTypeSeeder::class,    // Create absence types
            WorkLogSeeder::class,        // Generate work logs for employees
            AbsenceRequestSeeder::class, // Create absence requests 
            AbsenceSeeder::class,        // Create absences from approved requests
        ]);
    }
}

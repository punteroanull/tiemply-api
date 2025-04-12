<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the demo company
        $demoCompany = Company::where('name', 'Tiemply Demo Company')->first();
        
        if (!$demoCompany) {
            $this->command->error('Demo company not found. Please run CompanySeeder first.');
            return;
        }

        // Get the manager user
        $managerUser = User::where('email', 'manager@tiemply.com')->first();
        
        if (!$managerUser) {
            $this->command->error('Manager user not found. Please run UserSeeder first.');
            return;
        }

        // Create manager as an employee (for demo purposes)
        $managerEmployee = Employee::updateOrCreate(
            [
                'company_id' => $demoCompany->id,
                'user_id' => $managerUser->id,
            ],
            [
                'company_id' => $demoCompany->id,
                'user_id' => $managerUser->id,
                'contract_start_time' => '09:00',
                'contract_end_time' => '17:00',
                'remaining_vacation_days' => $demoCompany->max_vacation_days,
                'active' => true,
            ]
        );
        
        $this->command->info("Manager added as employee with ID: {$managerEmployee->id}");

        // Get employee role users
        $employeeRole = Role::where('name', 'Employee')->first();
        
        if (!$employeeRole) {
            $this->command->error('Employee role not found. Please run RoleSeeder first.');
            return;
        }
        
        $employeeUsers = User::where('role_id', $employeeRole->id)
            ->where('email', 'like', 'employee%@tiemply.com')
            ->limit(5)
            ->get();
        
        // Create different shifts for employees
        $shifts = [
            ['09:00', '17:00'], // standard
            ['08:00', '16:00'], // early
            ['10:00', '18:00'], // late
            ['07:00', '15:00'], // morning
            ['12:00', '20:00'], // afternoon
        ];
        
        foreach ($employeeUsers as $index => $user) {
            // Assign to demo company
            $shiftIndex = $index % count($shifts);
            $shift = $shifts[$shiftIndex];
            
            $employee = Employee::updateOrCreate(
                [
                    'company_id' => $demoCompany->id,
                    'user_id' => $user->id,
                ],
                [
                    'company_id' => $demoCompany->id,
                    'user_id' => $user->id,
                    'contract_start_time' => $shift[0],
                    'contract_end_time' => $shift[1],
                    'remaining_vacation_days' => $demoCompany->max_vacation_days - $index, // Some variation
                    'active' => true,
                ]
            );
            
            $this->command->info("Employee {$user->name} added to company with ID: {$employee->id}");
        }
    }
}

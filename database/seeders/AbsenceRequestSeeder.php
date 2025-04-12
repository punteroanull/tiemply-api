<?php

namespace Database\Seeders;

use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class AbsenceRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all active employees
        $employees = Employee::where('active', true)->get();
        
        if ($employees->isEmpty()) {
            $this->command->error('No active employees found. Please run EmployeeSeeder first.');
            return;
        }
        
        // Get absence types
        $vacationType = AbsenceType::where('code', 'vacation')->first();
        $sickLeaveType = AbsenceType::where('code', 'sick_leave')->first();
        $unpaidLeaveType = AbsenceType::where('code', 'unpaid_leave')->first();
        
        if (!$vacationType || !$sickLeaveType || !$unpaidLeaveType) {
            $this->command->error('Required absence types not found. Please run AbsenceTypeSeeder first.');
            return;
        }
        
        // Get a manager user for approvals
        $manager = User::whereHas('role', function ($query) {
            $query->where('name', 'Manager');
        })->first();
        
        if (!$manager) {
            $this->command->error('Manager user not found. Please run UserSeeder first.');
            return;
        }
        
        // Generate different statuses
        $statuses = ['pending', 'approved', 'rejected'];
        
        // For each employee
        foreach ($employees as $index => $employee) {
            // Create a vacation request (upcoming)
            $startDate = Carbon::now()->addDays(rand(14, 30));
            $endDate = (clone $startDate)->addDays(rand(1, 10));
            
            AbsenceRequest::create([
                'employee_id' => $employee->id,
                'absence_type_id' => $vacationType->id,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'is_partial' => false,
                'notes' => 'Planned vacation',
                'status' => 'pending',
            ]);
            
            // Create vacation requests with random statuses
            for ($i = 0; $i < 2; $i++) {
                $status = $statuses[rand(0, 2)];
                $startDate = Carbon::now()->subDays(rand(60, 90));
                $endDate = (clone $startDate)->addDays(rand(3, 7));
                
                $request = AbsenceRequest::create([
                    'employee_id' => $employee->id,
                    'absence_type_id' => $vacationType->id,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'is_partial' => false,
                    'notes' => 'Previous vacation request',
                    'status' => $status,
                ]);
                
                // If approved or rejected, add reviewer info
                if ($status !== 'pending') {
                    $request->reviewed_by = $manager->id;
                    $request->reviewed_at = Carbon::now()->subDays(rand(1, 10));
                    
                    if ($status === 'rejected') {
                        $request->rejection_reason = 'Too many employees already on leave during this period.';
                    }
                    
                    $request->save();
                }
            }
            
            // Create a sick leave request (50% chance)
            if (rand(0, 1) === 1) {
                $sickStartDate = Carbon::now()->subDays(rand(1, 15));
                $sickEndDate = (clone $sickStartDate)->addDays(rand(1, 3));
                
                AbsenceRequest::create([
                    'employee_id' => $employee->id,
                    'absence_type_id' => $sickLeaveType->id,
                    'start_date' => $sickStartDate->toDateString(),
                    'end_date' => $sickEndDate->toDateString(),
                    'is_partial' => false,
                    'notes' => 'Sick leave due to illness',
                    'status' => 'approved',
                    'reviewed_by' => $manager->id,
                    'reviewed_at' => Carbon::now(),
                ]);
            }
            
            // Create a partial day request (30% chance)
            if (rand(1, 10) <= 3) {
                $partialDate = Carbon::now()->addDays(rand(7, 14));
                
                AbsenceRequest::create([
                    'employee_id' => $employee->id,
                    'absence_type_id' => $unpaidLeaveType->id,
                    'start_date' => $partialDate->toDateString(),
                    'end_date' => $partialDate->toDateString(),
                    'is_partial' => true,
                    'start_time' => '14:00',
                    'end_time' => '17:00',
                    'notes' => 'Personal appointment',
                    'status' => 'pending',
                ]);
            }
            
            $this->command->info("Absence requests created for employee ID: {$employee->id}");
        }
    }
}

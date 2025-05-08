<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\WorkLog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class WorkLogSeeder extends Seeder
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
        
        // Generate work logs for the last 7 days
        $startDate = Carbon::now()->subDays(7);
        $endDate = Carbon::now();
        
        foreach ($employees as $employee) {
            $currentDate = clone $startDate;
            
            while ($currentDate->lte($endDate)) {
                // Skip weekends
                if ($currentDate->isWeekend()) {
                    $currentDate->addDay();
                    continue;
                }
                
                // Get contract times
                $contractStartTime = Carbon::createFromFormat('H:i', Carbon::parse($employee->contract_start_time)->format('H:i'));
                $contractEndTime = Carbon::createFromFormat('H:i', Carbon::parse($employee->contract_end_time)->format('H:i'));
                
                // Random deviation from contract time (minutes)
                $checkInDeviation = rand(-15, 15);
                $checkOutDeviation = rand(-15, 15);
                
                // Create check-in log
                $checkInDate = clone $currentDate;
                $checkInTime = (clone $contractStartTime)
                    ->setDate(
                        $checkInDate->year,
                        $checkInDate->month,
                        $checkInDate->day
                    )
                    ->addMinutes($checkInDeviation);
                
                $checkInId = WorkLog::create([
                    'employee_id' => $employee->id,
                    'date' => $checkInDate->toDateString(),
                    'time' => $checkInTime->format('H:i:s'),
                    'type' => 'check_in',
                    'ip_address' => fake()->ipv4(),
                    'notes' => rand(1, 10) === 1 ? fake()->sentence() : null, // 10% chance of having notes
                ]);
                
                // Create check-out log
                $checkOutDate = clone $currentDate;
                $checkOutTime = (clone $contractEndTime)
                    ->setDate(
                        $checkOutDate->year,
                        $checkOutDate->month,
                        $checkOutDate->day
                    )
                    ->addMinutes($checkOutDeviation);
                
                    $checkOutId = WorkLog::create([
                    'employee_id' => $employee->id,
                    'date' => $checkOutDate->toDateString(),
                    'time' => $checkOutTime->format('H:i:s'),
                    'type' => 'check_out',
                    'category' => 'shift_end', // Default category for check-out
                    'paired_log_id' => $checkInId->id, // This will be set later if needed
                    'ip_address' => fake()->ipv4(),
                    'notes' => rand(1, 10) === 1 ? fake()->sentence() : null, // 10% chance of having notes
                ]);
                
                $currentDate->addDay();
            }
            
            $this->command->info("Work logs created for employee ID: {$employee->id}");
        }
    }
}

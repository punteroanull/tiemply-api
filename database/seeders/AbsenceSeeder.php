<?php

namespace Database\Seeders;

use App\Models\Absence;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\Employee;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class AbsenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create absences for approved absence requests
        $this->createAbsencesFromApprovedRequests();
        
        // Create some additional absences that didn't need approval
        $this->createDirectAbsences();
    }
    
    /**
     * Create absence records for all approved absence requests.
     */
    private function createAbsencesFromApprovedRequests(): void
    {
        $approvedRequests = AbsenceRequest::where('status', 'approved')->get();
        
        if ($approvedRequests->isEmpty()) {
            $this->command->error('No approved absence requests found. Please run AbsenceRequestSeeder first.');
            return;
        }
        
        foreach ($approvedRequests as $request) {
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            $isPartial = $request->is_partial;
            $startTime = $request->start_time;
            $endTime = $request->end_time;
            
            $currentDate = clone $startDate;
            
            while ($currentDate->lte($endDate)) {
                // Skip weekends if company uses business days
                if ($request->employee->company->vacation_type === 'business_days' && $currentDate->isWeekend()) {
                    $currentDate->addDay();
                    continue;
                }
                
                Absence::create([
                    'employee_id' => $request->employee_id,
                    'absence_type_id' => $request->absence_type_id,
                    'request_id' => $request->id,
                    'date' => $currentDate->toDateString(),
                    'is_partial' => $isPartial,
                    'start_time' => $isPartial ? $startTime : null,
                    'end_time' => $isPartial ? $endTime : null,
                    'notes' => $request->notes,
                ]);
                
                $currentDate->addDay();
            }
            
            $this->command->info("Absences created for approved request ID: {$request->id}");
        }
    }
    
    /**
     * Create some direct absences (without requests).
     */
    private function createDirectAbsences(): void
    {
        // Get sick leave type (typically doesn't need approval)
        $sickLeaveType = AbsenceType::where('code', 'sick_leave')->first();
        
        if (!$sickLeaveType) {
            $this->command->error('Sick leave absence type not found. Please run AbsenceTypeSeeder first.');
            return;
        }
        
        // Get all active employees
        $employees = Employee::where('active', true)->get();
        
        if ($employees->isEmpty()) {
            $this->command->error('No active employees found. Please run EmployeeSeeder first.');
            return;
        }
        
        // Add some random sick days
        foreach ($employees as $employee) {
            // 50% chance of having a direct sick leave
            if (rand(0, 1) === 1) {
                $sickDate = Carbon::now()->subDays(rand(1, 30));
                
                Absence::create([
                    'employee_id' => $employee->id,
                    'absence_type_id' => $sickLeaveType->id,
                    'request_id' => null, // Direct absence without request
                    'date' => $sickDate->toDateString(),
                    'is_partial' => false,
                    'notes' => 'Sick day - reported via phone',
                ]);
                
                $this->command->info("Direct absence created for employee ID: {$employee->id}");
            }
        }
    }
}

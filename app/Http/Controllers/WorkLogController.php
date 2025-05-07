<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\WorkLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;

class WorkLogController extends Controller
{
    /**
     * Store a newly created work log entry in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|uuid|exists:employees,id',
            'type' => 'required|in:check_in,check_out',
            'notes' => 'nullable|string|max:500',
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        
        // Verify user can log for this employee
        if (auth()->id() !== $employee->user_id && Gate::denies('createFor', [WorkLog::class, $employee])) {
            return response()->json(['message' => 'Unauthorized to create work logs for this employee'], 403);
        }

        // Use current date and time if not provided
        $now = Carbon::now();
        $validated['date'] = $now->toDateString();
        $validated['time'] = $now->toTimeString();
        
        // Add IP address if available
        $validated['ip_address'] = $request->ip();
        
        // Check if there's already a log of the same type for today
        $existingLog = WorkLog::where('employee_id', $employee->id)
            ->where('date', $validated['date'])
            ->where('type', $validated['type'])
            ->exists();
            
        if ($existingLog && $validated['type'] === 'check_in') {
            return response()->json(['message' => 'Employee has already checked in today'], 422);
        }
        
        // If checking out, make sure there's a check-in first
        if ($validated['type'] === 'check_out') {
            $checkIn = WorkLog::where('employee_id', $employee->id)
                ->where('date', $validated['date'])
                ->where('type', 'check_in')
                ->exists();
                
            if (!$checkIn) {
                return response()->json(['message' => 'Employee must check in before checking out'], 422);
            }
        }

        $workLog = WorkLog::create($validated);

        return response()->json($workLog, 201);
    }

    /**
     * Display the specified work log.
     *
     * @param  \App\Models\WorkLog  $workLog
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(WorkLog $workLog)
    {
        Gate::authorize('view', $workLog);

        return response()->json($workLog);
    }

    /**
     * Update the specified work log in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\WorkLog  $workLog
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, WorkLog $workLog)
    {
        Gate::authorize('update', $workLog);

        $validated = $request->validate([
            'date' => 'sometimes|required|date',
            'time' => 'sometimes|required|date_format:H:i:s',
            'notes' => 'nullable|string|max:500',
        ]);

        $workLog->update($validated);

        return response()->json($workLog);
    }

    /**
     * Remove the specified work log from storage.
     *
     * @param  \App\Models\WorkLog  $workLog
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(WorkLog $workLog)
    {
        Gate::authorize('delete', $workLog);

        $workLog->delete();

        return response()->json(null, 204);
    }
    
    /**
     * Get daily report for an employee.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function dailyReport(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|uuid|exists:employees,id',
            'date' => 'sometimes|required|date',
        ]);
        
        $employee = Employee::findOrFail($request->employee_id);
        Gate::authorize('view', $employee);
        
        $date = $request->date ?? Carbon::now()->toDateString();
        
        $logs = WorkLog::where('employee_id', $employee->id)
            ->where('date', $date)
            ->orderBy('time')
            ->get();
        
        $checkIn = $logs->where('type', 'check_in')->first();
        $checkOut = $logs->where('type', 'check_out')->first();
        
        $hoursWorked = null;
        if ($checkIn && $checkOut) {
            $start = Carbon::parse($checkIn->time);
            $end = Carbon::parse($checkOut->time);
            $hoursWorked = $end->diffInMinutes($start) / 60;
        }
        
        return response()->json([
            'date' => $date,
            'employee' => $employee->load('user'),
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'hours_worked' => $hoursWorked,
            'all_logs' => $logs,
        ]);
    }
    
    /**
     * Get monthly report for an employee.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function monthlyReport(Request $request)
    {
        $request->validate([
            'employee_id' => 'required|uuid|exists:employees,id',
            'year' => 'required|integer|min:2000|max:' . (date('Y') + 1),
            'month' => 'required|integer|min:1|max:12',
        ]);
        
        $employee = Employee::findOrFail($request->employee_id);
        Gate::authorize('view', $employee);
        
        $startDate = Carbon::createFromDate($request->year, $request->month, 1)->startOfMonth();
        $endDate = clone $startDate;
        $endDate->endOfMonth();
        
        $logs = WorkLog::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('date')
            ->orderBy('time')
            ->get();
        
        $dailyLogs = [];
        $totalHours = 0;
        
        // Group logs by date
        foreach ($logs->groupBy('date') as $date => $dayLogs) {
            $checkIn = $dayLogs->where('type', 'check_in')->first();
            $checkOut = $dayLogs->where('type', 'check_out')->first();
            
            $hoursWorked = null;
            if ($checkIn && $checkOut) {
                $start = Carbon::parse($checkIn->time);
                $end = Carbon::parse($checkOut->time);
                $hoursWorked = $end->diffInMinutes($start) / 60;
                $totalHours += $hoursWorked;
            }
            
            $dailyLogs[] = [
                'date' => $date,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'hours_worked' => $hoursWorked,
            ];
        }
        
        return response()->json([
            'year' => $request->year,
            'month' => $request->month,
            'employee' => $employee->load('user'),
            'daily_logs' => $dailyLogs,
            'total_hours' => $totalHours,
            'work_days' => count($dailyLogs),
        ]);
    }

 /**
 * Employee checkin.
 *
 * @param  \Illuminate\Http\Request  $request
 * @return \Illuminate\Http\JsonResponse
 */
    public function checkIn(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|uuid|exists:employees,id',
            'notes' => 'nullable|string|max:500',
            'location' => 'nullable|string', // Optional location data
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        
        // Verify user can check in for this employee
        if (auth()->id() !== $employee->user_id && Gate::denies('createFor', [WorkLog::class, $employee])) {
            return response()->json(['message' => 'Unauthorized to check in for this employee'], 403);
        }

        // Check if there's already a check-in for today
        $today = Carbon::now()->toDateString();
        $existingCheckIn = WorkLog::where('employee_id', $employee->id)
            ->where('date', $today)
            ->where('type', 'check_in')
            ->exists();
            
        if ($existingCheckIn) {
            return response()->json(['message' => 'Employee has already checked in today'], 422);
        }

        // Create check-in record
        $workLog = WorkLog::create([
            'employee_id' => $employee->id,
            'date' => $today,
            'time' => Carbon::now()->toTimeString(),
            'type' => 'check_in',
            'ip_address' => $request->ip(),
            'location' => $validated['location'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'message' => 'Check-in successful',
            'work_log' => $workLog
        ], 201);
    }

/**
 * employee check-out.
 *
 * @param  \Illuminate\Http\Request  $request
 * @return \Illuminate\Http\JsonResponse
 */
    public function checkOut(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|uuid|exists:employees,id',
            'notes' => 'nullable|string|max:500',
            'location' => 'nullable|string', // Optional location data
        ]);

        $employee = Employee::findOrFail($validated['employee_id']);
        
        // Verify user can check out for this employee
        if (auth()->id() !== $employee->user_id && Gate::denies('createFor', [WorkLog::class, $employee])) {
            return response()->json(['message' => 'Unauthorized to check out for this employee'], 403);
        }

        // Check if there's a check-in for today
        $today = Carbon::now()->toDateString();
        $checkIn = WorkLog::where('employee_id', $employee->id)
            ->where('date', $today)
            ->where('type', 'check_in')
            ->first();
            
        if (!$checkIn) {
            return response()->json(['message' => 'Employee must check in before checking out'], 422);
        }
        
        // Check if there's already a check-out for today
        $existingCheckOut = WorkLog::where('employee_id', $employee->id)
            ->where('date', $today)
            ->where('type', 'check_out')
            ->exists();
            
        if ($existingCheckOut) {
            return response()->json(['message' => 'Employee has already checked out today'], 422);
        }

        // Create check-out record
        $workLog = WorkLog::create([
            'employee_id' => $employee->id,
            'date' => $today,
            'time' => Carbon::now()->toTimeString(),
            'type' => 'check_out',
            'ip_address' => $request->ip(),
            'location' => $validated['location'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);
        
        // Calculate hours worked
        $checkInTime = Carbon::parse($checkIn->time);
        $checkOutTime = Carbon::parse($workLog->time);
        $hoursWorked = $checkOutTime->diffInMinutes($checkInTime) / 60;

        return response()->json([
            'message' => 'Check-out successful',
            'work_log' => $workLog,
            'hours_worked' => $hoursWorked
        ], 201);
    }
}
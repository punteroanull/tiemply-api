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
        'category' => 'nullable|in:shift_start,break_end,offsite_end',
        'notes' => 'nullable|string|max:500',
    ]);

    $employee = Employee::findOrFail($validated['employee_id']);
        
    // Verify user can check in for this employee
    if (auth()->id() !== $employee->user_id && Gate::denies('createFor', [WorkLog::class, $employee])) {
        return response()->json(['message' => 'Unauthorized to check in for this employee'], 403);
    }
    $category = $validated['category'] ?? 'shift_start'; // Default category for check-in
    $pairedCheckId = null;

    switch ($category) {
        case 'shift_start':
            // Check if there's already a check-in for today without a check-out
            $today = Carbon::now()->toDateString();
            $existingShiftStart = WorkLog::where('employee_id', $employee->id)
            ->where('date', $today)
            ->where('type', 'check_in')
            ->where('category', 'shift_start')
            ->whereDoesntHave('pairedLog', function ($query) {
                $query->where('type', 'check_out')
                      ->where('category', 'shift_end');
            })
            ->exists();
                
            if ($existingShiftStart) {
                return response()->json([
                    'message' => 'Employee has already started his shift',
                ], 422);
            }

            break;
        case 'break_end':
            // Check if there's already a check-out (-1 day or today) for this category without a paired log
            $today = Carbon::now()->subDays(1)->toDateString();

            $existingBreak = WorkLog::where('employee_id', $employee->id)
                ->where('date', '>=', $today)
                ->where('type', 'check_out')
                ->where('category', 'break_start')
                ->whereDoesntHave('pairedLog', function ($query) {
                    $query->where('type', 'check_in')
                          ->where('category', 'break_end');
                })
                ->first();

            if (!$existingBreak) {
                return response()->json([
                    'message' => 'No matching check-out found for break',
                ], 422);
            }
            $pairedCheckId = $existingBreak->id;
            break;
        case 'offsite_end':

            // Check if there's already a check-out (-1 day or today) for this category without a paired log
            $today = Carbon::now()->subDays(1)->toDateString();

            $existingOffsite = WorkLog::where('employee_id', $employee->id)
                ->where('date', '>=', $today)
                ->where('type', 'check_out')
                ->where('category', 'offsite_start')
                ->whereDoesntHave('pairedLog', function ($query) {
                    $query->where('type', 'check_in')
                          ->where('category', 'offsite_end');
                })
                ->first();

            if (!$existingOffsite) {
                return response()->json([
                    'message' => 'No matching check-out found for an offsite',
                ], 422);
            }
            $pairedCheckId = $existingOffsite->id;
            break;
        default:
            $validated['category'] = 'shift_start'; // Default category for check-in
    }
    
    // Create the check-in record
    $workLog = WorkLog::create([
        'employee_id' => $validated['employee_id'],
        'date' => now()->toDateString(),
        'time' => now()->toTimeString(),
        'type' => 'check_in',
        'category' => $validated['category'] ?? 'shift_start',
        'ip_address' => $request->ip(),
        'notes' => $validated['notes'] ?? null,
        'paired_log_id' => $pairedCheckId,
    ]);

    return response()->json([
        'message' => 'Check-in successful',
        'work_log' => $workLog
    ], 201);
}

/**
 * Record a check-out event
 * Note: I substract 1 day from the current date to handle night shifts that may check out the next day.
 */
public function checkOut(Request $request)
{
    $validated = $request->validate([
        'employee_id' => 'required|uuid|exists:employees,id',
        'category' => 'nullable|in:shift_end,break_start,offsite_start',
        'notes' => 'nullable|string|max:500',
    ]);
    $category = $validated['category'] ?? 'shift_end'; // Default category for check-out
    $employee = Employee::findOrFail($validated['employee_id']);
    // Check if there's a check-in for today (or yesterday, in case of night shifts) without a check-out for this category
    $today = Carbon::now()->subDays(1)->toDateString();

    $existingShift = WorkLog::where('employee_id', $employee->id)
    ->where('date', '>=', $today)
    ->where('type', 'check_in')
    ->where('category', 'shift_start')
    ->whereDoesntHave('pairedLog', function ($query) {
        $query->where('type', 'check_out')
              ->where('category', 'shift_end');
    })
    ->first();

    $shiftId = null;
    //If there's no check-in with category shift_start without pairedlog, there's no need to check for break_start or offsite_start
    if (!$existingShift) {
        return response()->json([
            'message' => 'No matching check-in found. Please check in first.',
        ], 422);
    }
    switch ($category) {
        case 'shift_end':
            $existingBreakOrOffsite = $existingBreak = WorkLog::where('employee_id', $employee->id)
            ->where('date', '>=', $today)
            ->where('type', 'check_out')
            ->whereIn('category', ['break_start','offsite_start'])
            ->whereDoesntHave('pairedLog', function ($query) {
                $query->where('type', 'check_in')
                      ->whereIn('category', ['break_end','offsite_end']);
            })->exists();

            if ($existingBreakOrOffsite) {
                return response()->json([
                    'message' => 'Employee has already checked out for a break or offsite, must return first to end the shift',
                ], 422);
            }
            // Check if there is not a check-out for this category already without a paired log
            $shiftId = $existingShift ? $existingShift->id : null;
            break;
        case 'break_start':
            // Check if there is not a check-out for this category already without a paired log 
            $existingBreak = WorkLog::where('employee_id', $employee->id)
                ->where('date', '>=', $today)
                ->where('type', 'check_out')
                ->where('category', 'break_start')
                ->whereDoesntHave('pairedLog', function ($query) {
                    $query->where('type', 'check_in')
                          ->where('category', 'break_end');
                })
                ->first();

            if ($existingBreak) {
                return response()->json([
                    'message' => 'Employee has already checked out for a break',
                ], 422);
            } else {
                // Check if there is not a check-out for this category already without a paired log 
                $existingOffsite = WorkLog::where('employee_id', $employee->id)
                ->where('date', '>=', $today)
                ->where('type', 'check_out')
                ->where('category', 'offsite_start')
                ->whereDoesntHave('pairedLog', function ($query) {
                    $query->where('type', 'check_in')
                        ->where('category', 'offsite_end');
                })
                ->first();

                if ($existingOffsite) {
                    return response()->json([
                        'message' => 'Employee has already checked out for an offsite',
                    ], 422);
                }
            }
            
            $shiftId = null; // As we are checking out for a break, we don't need to pair it with the shift start log
            break;
        case 'offsite_start':
            // Check if there is not a check-out for this category already without a paired log 
            $existingOffsite = WorkLog::where('employee_id', $employee->id)
                ->where('date', '>=', $today)
                ->where('type', 'check_out')
                ->where('category', 'offsite_start')
                ->whereDoesntHave('pairedLog', function ($query) {
                    $query->where('type', 'check_in')
                          ->where('category', 'offsite_end');
                })
                ->first();

            if ($existingOffsite) {
                return response()->json([
                    'message' => 'Employee has already checked out for an offsite',
                ], 422);
            } else {
                // Check if there is not a check-out for this category already without a paired log 
                $existingBreak = WorkLog::where('employee_id', $employee->id)
                ->where('date', '>=', $today)
                ->where('type', 'check_out')
                ->where('category', 'break_start')
                ->whereDoesntHave('pairedLog', function ($query) {
                    $query->where('type', 'check_in')
                        ->where('category', 'break_end');
                })
                ->first();

                if ($existingBreak) {
                    return response()->json([
                      'message' => 'Employee has already checked out for a break',
                    ], 422);
                }
            }
            
            $shiftId = null; // As we are checking out for an offsite, we don't need to pair it with the shift start log
            break;
        default:
            $validated['category'] = 'shift_end'; // Default category for check-out
    }

    

    // Create the check-out record
    $workLog = WorkLog::create([
        'employee_id' => $validated['employee_id'],
        'date' => now()->toDateString(),
        'time' => now()->toTimeString(),
        'type' => 'check_out',
        'category' => $validated['category'],
        'ip_address' => $request->ip(),
        'notes' => $validated['notes'] ?? null,
        'location' => $request->input('location') ?? null, // Optional location field
        'paired_log_id' => $shiftId,
    ]);

    return response()->json([
        'message' => 'Check-out successful',
        'work_log' => $workLog
    ], 201);
}

/**
 * Get employee's current status
 */
public function getEmployeeStatus(string $employeeId)
{
    $employee = Employee::findOrFail($employeeId);
    
    // Get the most recent work log entry
    $lastLog = WorkLog::where('employee_id', $employeeId)
        ->whereDate('date', now()->toDateString())
        ->latest('time')
        ->first();
        
    $status = $lastLog && $lastLog->type === 'check_in' ? 'in' : 'out';
    $category = $lastLog ? $lastLog->category : null;
    
    return response()->json([
        'employee' => $employee->load('user'),
        'status' => $status,
        'category' => $category,
        'last_activity' => $lastLog ? [
            'time' => $lastLog->time,
            'notes' => $lastLog->notes
        ] : null
    ], 200);
}

/**
 * Get daily work hours summary
 */
public function summary(Request $request)
{
    $request->validate([
        'employee_id' => 'required|uuid|exists:employees,id',
        'date' => 'sometimes|required|date',
    ]);
    
    $employee = Employee::findOrFail($request->employee_id);
    $date = $request->date ?? now()->toDateString();
    
    // Get all logs for the day
    $logs = WorkLog::where('employee_id', $employee->id)
        ->whereDate('date', $date)
        ->orderBy('time')
        ->get();
        
    // Group check-ins and check-outs into pairs
    $pairs = [];
    $currentCheckIn = null;
    
    foreach ($logs as $log) {
        if ($log->type === 'check_in') {
            $currentCheckIn = $log;
        } elseif ($log->type === 'check_out' && $currentCheckIn) {
            $pairs[] = [
                'check_in' => $currentCheckIn,
                'check_out' => $log,
                'duration_minutes' => Carbon::parse($currentCheckIn->time)
                    ->diffInMinutes(Carbon::parse($log->time))
            ];
            $currentCheckIn = null;
        }
    }
    
    // Calculate total working time
    $totalMinutes = collect($pairs)->sum('duration_minutes');
    $hours = floor($totalMinutes / 60);
    $minutes = $totalMinutes % 60;
    
    return response()->json([
        'date' => $date,
        'employee' => $employee->load('user'),
        'entries' => $pairs,
        'total_time' => [
            'hours' => $hours,
            'minutes' => $minutes,
            'formatted' => sprintf('%02d:%02d', $hours, $minutes)
        ],
        'is_currently_checked_in' => $currentCheckIn !== null
    ], 200);
}
}
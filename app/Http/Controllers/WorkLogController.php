<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\WorkLog;
use App\Models\Absence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

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
            'category' => 'nullable|in:shift_start,shift_end,break_start,break_end,offsite_start,offsite_end',
            'notes' => 'nullable|string|max:500',
        ]);

        switch ($request->type) {
            case 'check_in':
                checkIn($request);
                break;
            case 'check_out':
                checkOut($request);
                break;
            default:
                return response()->json(['message' => 'Invalid type'], 422);
        }
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
     * Record a checout event 
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */

    //TODO: Try to optimize this method, I think I can group the queries to reduce the number of queries to the database.

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
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */

    //TODO: Try to optimize this method, I think I can group the queries to reduce the number of queries to the database.
    
    public function checkOut(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|uuid|exists:employees,id',
            'category' => 'nullable|in:shift_end,break_start,offsite_start',
            'notes' => 'nullable|string|max:500',
        ]);
        $category = $validated['category'] ?? 'shift_end'; // Default category for check-out
        
        $employee = Employee::findOrFail($validated['employee_id']);

        // Verify user can check in for this employee
        if (auth()->id() !== $employee->user_id && Gate::denies('createFor', [WorkLog::class, $employee])) {
            return response()->json(['message' => 'Unauthorized to check in for this employee'], 403);
        }

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
                $existingBreakOrOffsite = WorkLog::where('employee_id', $employee->id)
                    ->where('date', '>=', $today)
                    ->where('type', 'check_out')
                    ->whereIn('category', ['break_start', 'offsite_start'])
                    ->whereNotExists(function ($query) {
                        $query->select(\DB::raw(1))
                            ->from('work_logs as paired')
                            ->whereRaw('paired.paired_log_id = work_logs.id')
                            ->where('paired.type', 'check_in')
                            ->whereIn('paired.category', ['break_end', 'offsite_end']);
                    })
                    ->exists();
                    
                if ($existingBreakOrOffsite) {
                    return response()->json([
                        'message' => 'Employee has already checked out for a break or offsite, must return first to end the shift',
                        'data' => $existingBreakOrOffsite,
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
                    ->whereNotExists(function ($query) {
                        $query->select(\DB::raw(1))
                            ->from('work_logs as paired')
                            ->whereRaw('paired.paired_log_id = work_logs.id')
                            ->where('paired.type', 'check_in')
                            ->where('paired.category', 'break_end');
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
                        ->whereNotExists(function ($query) {
                            $query->select(\DB::raw(1))
                                ->from('work_logs as paired')
                                ->whereRaw('paired.paired_log_id = work_logs.id')
                                ->where('paired.type', 'check_in')
                                ->where('paired.category', 'offsite_end');
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
                        ->whereNotExists(function ($query) {
                            $query->select(\DB::raw(1))
                                ->from('work_logs as paired')
                                ->whereRaw('paired.paired_log_id = work_logs.id')
                                ->where('paired.type', 'check_in')
                                ->where('paired.category', 'offsite_end');
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
                        ->whereNotExists(function ($query) {
                            $query->select(\DB::raw(1))
                                ->from('work_logs as paired')
                                ->whereRaw('paired.paired_log_id = work_logs.id')
                                ->where('paired.type', 'check_in')
                                ->where('paired.category', 'break_end');
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
        Gate::authorize('view', $employee);
        error_log('employeeId: ' . $employeeId);
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
     * Get daily report for an employee.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function dailyReport($employeeId, $period = null)
    {
       
        /*
        validator($request->route()->parameters(), [
            'employee_id' => 'required|uuid|exists:employees,id',
            'date' => 'sometimes|required|date',
            ])->validate();
        */
        $employee = null;
        try {
            $employee = Employee::findOrFail($employeeId);
        } catch (\Exception $e) {
            Log::error('Error finding employee: ' . $e->getMessage());
            return response()->json(['message' => 'Employee not found'], 404);
        }
        // Verificar si el usuario puede ver este empleado
        Gate::authorize('view', $employee);
        $date = $period != null ? Carbon::parse($period) : Carbon::now();
        //dd($date);

        // Get all logs for the day
        $logs = WorkLog::where('employee_id', $employee->id)
            ->where('date', $date)
            ->orderBy('time')
            ->get();

        // Process work periods using the paired_log_id relationship
        $workSessions = [];
        $totalWorkMinutes = 0;

        // Get the main shift information
        $shiftStart = $logs->where('type', 'check_in')
            ->where('category', 'shift_start')
            ->first();

        $shiftEnd = $logs->where('type', 'check_out')
            ->where('category', 'shift_end')
            ->first();

        // Process all paired check-in/check-out logs
        foreach ($logs as $log) {
            // Only process check-ins as the starting point of a pair
            if ($log->type === 'check_in') {
                // Find corresponding check-out (either this log is paired to a check-out or vice versa)
                $pairedLog = null;

                if ($log->paired_log_id) {
                    // This check-in is linked to a check-out
                    $pairedLog = $logs->firstWhere('id', $log->paired_log_id);
                } else {
                    // Check if this check-in is referenced by a check-out
                    $pairedLog = $logs->firstWhere('paired_log_id', $log->id);
                }

                if ($pairedLog && $pairedLog->type === 'check_out') {
                    $start = Carbon::parse($log->time);
                    $end = Carbon::parse($pairedLog->time);

                    // Only count if check-out is after check-in
                    if ($end->gt($start)) {
                        $durationMinutes = $end->diffInMinutes($start);

                        // Only add to total time if this is not a break or offsite period
                        if (
                            !in_array($log->category, ['break_end', 'offsite_end']) &&
                            !in_array($pairedLog->category, ['break_start', 'offsite_start'])
                        ) {
                            $totalWorkMinutes += $durationMinutes;
                        }

                        $workSessions[] = [
                            'start_log' => $log,
                            'end_log' => $pairedLog,
                            'duration_minutes' => $durationMinutes,
                            'duration_formatted' => sprintf('%02d:%02d', floor($durationMinutes / 60), $durationMinutes % 60),
                            'type' => $this->getSessionType($log->category, $pairedLog->category)
                        ];
                    }
                }
            }
        }

        // Calculate break time
        $breakTime = collect($workSessions)
            ->filter(function ($session) {
                return $session['type'] === 'break';
            })
            ->sum('duration_minutes');

        // Calculate offsite time
        $offsiteTime = collect($workSessions)
            ->filter(function ($session) {
                return $session['type'] === 'offsite';
            })
            ->sum('duration_minutes');

        // Check if the day is still in progress
        $inProgress = $logs->where('type', 'check_in')
            ->count() > $logs->where('type', 'check_out')
            ->count();

        // Determine overall day status
        $dayStatus = 'absent';
        if ($shiftStart && $shiftEnd) {
            $dayStatus = 'completed';
        } else if ($shiftStart) {
            $dayStatus = 'in_progress';
        } else if ($date > Carbon::now()->toDateString()) {
            $dayStatus = 'future';
        }

        // Get contract hours for comparison
        $contractMinutes = 0;
        if ($employee->contract_start_time && $employee->contract_end_time) {
            $contractStart = Carbon::parse($employee->contract_start_time);
            $contractEnd = Carbon::parse($employee->contract_end_time);
            $contractMinutes = $contractEnd->diffInMinutes($contractStart);
        }

        // Format total work time
        $totalHours = floor($totalWorkMinutes / 60);
        $totalMinutes = $totalWorkMinutes % 60;

        // Calculate variance from expected work time
        $timeVariance = $totalWorkMinutes - $contractMinutes;

        return response()->json([
            'date' => $date,
            'employee' => $employee->load('user'),
            'status' => $dayStatus,
            'shift' => [
                'start' => $shiftStart,
                'end' => $shiftEnd,
                'total_minutes' => $shiftStart && $shiftEnd ?
                    Carbon::parse($shiftStart->time)->diffInMinutes(Carbon::parse($shiftEnd->time)) : null
            ],
            'work_sessions' => $workSessions,
            'break_time' => [
                'minutes' => $breakTime,
                'formatted' => sprintf('%02d:%02d', floor($breakTime / 60), $breakTime % 60)
            ],
            'offsite_time' => [
                'minutes' => $offsiteTime,
                'formatted' => sprintf('%02d:%02d', floor($offsiteTime / 60), $offsiteTime % 60)
            ],
            'total_work_time' => [
                'minutes' => $totalWorkMinutes,
                'formatted' => sprintf('%02d:%02d', $totalHours, $totalMinutes)
            ],
            'contract_work_time' => [
                'minutes' => $contractMinutes,
                'formatted' => sprintf('%02d:%02d', floor($contractMinutes / 60), $contractMinutes % 60)
            ],
            'time_variance' => [
                'minutes' => $timeVariance,
                'formatted' => sprintf(
                    '%s%02d:%02d',
                    $timeVariance < 0 ? '-' : '+',
                    floor(abs($timeVariance) / 60),
                    abs($timeVariance) % 60
                )
            ],
            'in_progress' => $inProgress,
            'all_logs' => $logs
        ], 200);
    }

    /**
     * Helper method to determine session type based on categories
     * 
     * @param string $startCategory
     * @param string $endCategory
     * @return string
     */
    private function getSessionType($startCategory, $endCategory)
    {
        if ($startCategory === 'shift_start' && $endCategory === 'shift_end') {
            return 'shift';
        } else if ($startCategory === 'break_end' && $endCategory === 'break_start') {
            return 'work';
        } else if ($startCategory === 'break_start' && $endCategory === 'break_end') {
            return 'break';
        } else if ($startCategory === 'offsite_end' && $endCategory === 'offsite_start') {
            return 'work';
        } else if ($startCategory === 'offsite_start' && $endCategory === 'offsite_end') {
            return 'offsite';
        }

        return 'work'; // Default
    }

    /**
     * Get weekly report for an employee.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function weeklyReport($employeeId, $period = null)
    {
        $validator = Validator::make([
            'employee_id' => $employeeId,
            'period' => $period,
        ], [
            'employee_id' => ['required', 'uuid'],
            'period' => ['nullable', 'date_format:Y-m-d'],
        ]);
    
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $employee = null;
        try {
            $employee = Employee::findOrFail($employeeId);
        } catch (\Exception $e) {
            Log::error('Error finding employee: ' . $e->getMessage());
            return response()->json(['message' => 'Employee not found'], 404);
        }

        Gate::authorize('view', $employee);

        $date = $period != null ? Carbon::parse($period) : Carbon::now();
        $year = $date->year;
        $month = $date->month;
        $day = $date->day;
        // Determine start and end of month
        $weekStart = Carbon::createFromDate($date)->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();
        $currentDate = Carbon::now();


        // Get all work logs for the week
        $logs = WorkLog::where('employee_id', $employee->id)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        // Get absences for the week
        $absences = Absence::where('employee_id', $employee->id)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->with('absenceType')
            ->get();

        // Calculate contract minutes per day
        $contractMinutesPerDay = 0;
        if ($employee->contract_start_time && $employee->contract_end_time) {
            $contractStart = Carbon::parse($employee->contract_start_time);
            $contractEnd = Carbon::parse($employee->contract_end_time);
            $contractMinutesPerDay = $contractEnd->diffInMinutes($contractStart);
        }

        // Process each day of the week
        $dailyReports = [];
        $weeklyStats = [
            'total_work_minutes' => 0,
            'total_break_minutes' => 0,
            'total_offsite_minutes' => 0,
            'days_worked' => 0,
            'days_absent' => 0,
            'days_incomplete' => 0,
            'expected_work_minutes' => 0
        ];

        for ($day = 0; $day < 7; $day++) {
            $currentDate = $weekStart->copy()->addDays($day);
            $dateString = $currentDate->toDateString();
            $isWeekend = $currentDate->isWeekend();
            $isFuture = $currentDate->gt(Carbon::now());

            // Only count weekdays in expected work time
            if (!$isWeekend && !$isFuture) {
                $weeklyStats['expected_work_minutes'] += $contractMinutesPerDay;
            }

            // Get logs for current day
            $dayLogs = $logs->where('date', $dateString);

            // Get absences for current day
            $dayAbsences = $absences->where('date', $dateString);

            // Analyze day's work sessions
            $workMinutes = 0;
            $breakMinutes = 0;
            $offsiteMinutes = 0;
            $workSessions = [];
            $hasStarted = false;
            $hasCompleted = false;

            // Check if shift started and ended
            $shiftStart = $dayLogs->where('type', 'check_in')
                ->where('category', 'shift_start')
                ->first();

            $shiftEnd = $dayLogs->where('type', 'check_out')
                ->where('category', 'shift_end')
                ->first();

            $hasStarted = $shiftStart !== null;
            $hasCompleted = $shiftStart !== null && $shiftEnd !== null;

            // Process paired logs
            foreach ($dayLogs as $log) {
                if ($log->type === 'check_in') {
                    $pairedLog = null;

                    if ($log->paired_log_id) {
                        $pairedLog = $dayLogs->firstWhere('id', $log->paired_log_id);
                    } else {
                        $pairedLog = $dayLogs->firstWhere('paired_log_id', $log->id);
                    }

                    if ($pairedLog && $pairedLog->type === 'check_out') {
                        $start = Carbon::parse($log->time);
                        $end = Carbon::parse($pairedLog->time);

                        if ($end->gt($start)) {
                            $sessionType = $this->getSessionType($log->category, $pairedLog->category);
                            $durationMinutes = $end->diffInMinutes($start);

                            $workSessions[] = [
                                'type' => $sessionType,
                                'start' => $log->time,
                                'end' => $pairedLog->time,
                                'duration_minutes' => $durationMinutes
                            ];

                            // Add to appropriate counter
                            if ($sessionType === 'break') {
                                $breakMinutes += $durationMinutes;
                            } else if ($sessionType === 'offsite') {
                                $offsiteMinutes += $durationMinutes;
                            } else {
                                $workMinutes += $durationMinutes;
                            }
                        }
                    }
                }
            }

            // Determine day status
            $dayStatus = 'absent';
            $absenceInfo = null;

            if ($dayAbsences->count() > 0) {
                $dayStatus = 'absent';
                $absence = $dayAbsences->first();
                $absenceInfo = [
                    'id' => $absence->id,
                    'type' => $absence->absenceType->name,
                    'code' => $absence->absenceType->code,
                    'is_paid' => $absence->absenceType->is_paid
                ];
                $weeklyStats['days_absent']++;
            } else if ($hasCompleted) {
                $dayStatus = 'completed';
                $weeklyStats['days_worked']++;
                $weeklyStats['total_work_minutes'] += $workMinutes;
                $weeklyStats['total_break_minutes'] += $breakMinutes;
                $weeklyStats['total_offsite_minutes'] += $offsiteMinutes;
            } else if ($hasStarted) {
                $dayStatus = 'incomplete';
                $weeklyStats['days_incomplete']++;
                $weeklyStats['total_work_minutes'] += $workMinutes;
                $weeklyStats['total_break_minutes'] += $breakMinutes;
                $weeklyStats['total_offsite_minutes'] += $offsiteMinutes;
            } else if ($isWeekend) {
                $dayStatus = 'weekend';
            } else if ($isFuture) {
                $dayStatus = 'upcoming';
            }

            $dailyReports[] = [
                'date' => $dateString,
                'day_name' => $currentDate->format('l'),
                'is_weekend' => $isWeekend,
                'status' => $dayStatus,
                'shift' => [
                    'started' => $hasStarted,
                    'completed' => $hasCompleted,
                    'start_time' => $shiftStart ? $shiftStart->time : null,
                    'end_time' => $shiftEnd ? $shiftEnd->time : null
                ],
                'work_time' => [
                    'minutes' => $workMinutes,
                    'formatted' => sprintf('%02d:%02d', floor($workMinutes / 60), $workMinutes % 60)
                ],
                'break_time' => [
                    'minutes' => $breakMinutes,
                    'formatted' => sprintf('%02d:%02d', floor($breakMinutes / 60), $breakMinutes % 60)
                ],
                'offsite_time' => [
                    'minutes' => $offsiteMinutes,
                    'formatted' => sprintf('%02d:%02d', floor($offsiteMinutes / 60), $offsiteMinutes % 60)
                ],
                'sessions' => $workSessions,
                'absence' => $absenceInfo,
                'log_count' => count($dayLogs)
            ];
        }

        // Calculate weekly variance
        $weekVariance = $weeklyStats['total_work_minutes'] - $weeklyStats['expected_work_minutes'];

        // Format total times
        $formattedTimes = [];
        foreach (['total_work_minutes', 'total_break_minutes', 'total_offsite_minutes', 'expected_work_minutes'] as $key) {
            $minutes = $weeklyStats[$key];
            $formattedTimes[$key] = [
                'minutes' => $minutes,
                'hours' => round($minutes / 60, 1),
                'formatted' => sprintf('%02d:%02d', floor($minutes / 60), $minutes % 60)
            ];
        }

        return response()->json([
            'week_start' => $weekStart->toDateString(),
            'week_end' => $weekEnd->toDateString(),
            'employee' => $employee->load('user'),
            'days' => $dailyReports,
            'summary' => [
                'work_time' => $formattedTimes['total_work_minutes'],
                'break_time' => $formattedTimes['total_break_minutes'],
                'offsite_time' => $formattedTimes['total_offsite_minutes'],
                'expected_work_time' => $formattedTimes['expected_work_minutes'],
                'time_variance' => [
                    'minutes' => $weekVariance,
                    'hours' => round($weekVariance / 60, 1),
                    'formatted' => sprintf(
                        '%s%02d:%02d',
                        $weekVariance < 0 ? '-' : '+',
                        floor(abs($weekVariance) / 60),
                        abs($weekVariance) % 60
                    )
                ],
                'days_worked' => $weeklyStats['days_worked'],
                'days_absent' => $weeklyStats['days_absent'],
                'days_incomplete' => $weeklyStats['days_incomplete'],
                'productivity' => $weeklyStats['expected_work_minutes'] > 0
                    ? round(($weeklyStats['total_work_minutes'] / $weeklyStats['expected_work_minutes']) * 100)
                    : 0
            ]
        ]);
    }

    /**
     * Get monthly report for an employee.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function monthlyReport($employeeId, $year = null, $month = null)
    {
        $validator = Validator::make([
            'year' => $year,
            'month' => $month,
        ], [
            'year' => ['nullable', 'integer', 'min:2000'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);
    
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $employee = null;
        try {
            $employee = Employee::findOrFail($employeeId);
        } catch (\Exception $e) {
            Log::error('Error finding employee: ' . $e->getMessage());
            return response()->json(['message' => 'Employee not found'], 404);
        }

        Gate::authorize('view', $employee);

        $year = $year ?? Carbon::now()->year;
        $month = $month ?? Carbon::now()->month;    
        // Determine start and end of month
        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();
        $currentDate = Carbon::now();

        // Get all work logs for the month
        $logs = WorkLog::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        // Get absences for the month
        $absences = Absence::where('employee_id', $employee->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->with('absenceType')
            ->get();

        // Get contract minutes per day
        $contractMinutesPerDay = 0;
        if ($employee->contract_start_time && $employee->contract_end_time) {
            $contractStart = Carbon::parse($employee->contract_start_time);
            $contractEnd = Carbon::parse($employee->contract_end_time);
            $contractMinutesPerDay = $contractEnd->diffInMinutes($contractStart);
        }

        // Monthly statistics
        $monthlyStats = [
            'total_work_minutes' => 0,
            'total_break_minutes' => 0,
            'total_offsite_minutes' => 0,
            'business_days' => 0,
            'days_worked' => 0,
            'days_absent' => 0,
            'days_incomplete' => 0,
            'absence_types' => []
        ];

        // Daily reports
        $dailyReports = [];

        // Process each day of the month
        for ($day = 0; $day < $endDate->day; $day++) {
            $currentDay = $startDate->copy()->addDays($day);
            $dateString = $currentDay->toDateString();
            $isWeekend = $currentDay->isWeekend();
            $isFuture = $currentDay->gt($currentDate);

            // Count business days
            if (!$isWeekend && !$isFuture) {
                $monthlyStats['business_days']++;
            }

            // Get day's logs
            $dayLogs = $logs->where('date', $dateString);

            // Get day's absences
            $dayAbsences = $absences->where('date', $dateString);

            // Check work session metrics
            $dayWorkMinutes = 0;
            $dayBreakMinutes = 0;
            $dayOffsiteMinutes = 0;
            $workSessions = [];

            // Check if shift started and ended
            $shiftStart = $dayLogs->where('type', 'check_in')
                ->where('category', 'shift_start')
                ->first();

            $shiftEnd = $dayLogs->where('type', 'check_out')
                ->where('category', 'shift_end')
                ->first();

            $hasStarted = $shiftStart !== null;
            $hasCompleted = $shiftStart !== null && $shiftEnd !== null;

            // Process paired logs
            foreach ($dayLogs as $log) {
                if ($log->type === 'check_in') {
                    $pairedLog = null;

                    if ($log->paired_log_id) {
                        $pairedLog = $dayLogs->firstWhere('id', $log->paired_log_id);
                    } else {
                        $pairedLog = $dayLogs->firstWhere('paired_log_id', $log->id);
                    }

                    if ($pairedLog && $pairedLog->type === 'check_out') {
                        $start = Carbon::parse($log->time);
                        $end = Carbon::parse($pairedLog->time);

                        if ($end->gt($start)) {
                            $sessionType = $this->getSessionType($log->category, $pairedLog->category);
                            $durationMinutes = $end->diffInMinutes($start);

                            // Add to appropriate counter
                            if ($sessionType === 'break') {
                                $dayBreakMinutes += $durationMinutes;
                            } else if ($sessionType === 'offsite') {
                                $dayOffsiteMinutes += $durationMinutes;
                            } else {
                                $dayWorkMinutes += $durationMinutes;
                            }
                        }
                    }
                }
            }

            // Determine day status
            $dayStatus = 'absent';
            $absenceInfo = null;

            if ($dayAbsences->count() > 0) {
                $dayStatus = 'absent';
                $absence = $dayAbsences->first();
                $absenceType = $absence->absenceType;

                $absenceInfo = [
                    'id' => $absence->id,
                    'type' => $absenceType->name,
                    'code' => $absenceType->code,
                    'is_paid' => $absenceType->is_paid
                ];

                // Track absence types for summary
                $absenceKey = $absenceType->code;
                if (!isset($monthlyStats['absence_types'][$absenceKey])) {
                    $monthlyStats['absence_types'][$absenceKey] = [
                        'code' => $absenceType->code,
                        'name' => $absenceType->name,
                        'count' => 0,
                        'is_paid' => $absenceType->is_paid
                    ];
                }
                $monthlyStats['absence_types'][$absenceKey]['count']++;
                $monthlyStats['days_absent']++;
            } else if ($hasCompleted) {
                $dayStatus = 'completed';
                $monthlyStats['days_worked']++;
                $monthlyStats['total_work_minutes'] += $dayWorkMinutes;
                $monthlyStats['total_break_minutes'] += $dayBreakMinutes;
                $monthlyStats['total_offsite_minutes'] += $dayOffsiteMinutes;
            } else if ($hasStarted) {
                $dayStatus = 'incomplete';
                $monthlyStats['days_incomplete']++;
                $monthlyStats['total_work_minutes'] += $dayWorkMinutes;
                $monthlyStats['total_break_minutes'] += $dayBreakMinutes;
                $monthlyStats['total_offsite_minutes'] += $dayOffsiteMinutes;
            } else if ($isWeekend) {
                $dayStatus = 'weekend';
            } else if ($isFuture) {
                $dayStatus = 'upcoming';
            }

            $dailyReports[] = [
                'date' => $dateString,
                'day' => $currentDay->day,
                'day_name' => $currentDay->format('l'),
                'is_weekend' => $isWeekend,
                'status' => $dayStatus,
                'shift' => [
                    'started' => $hasStarted,
                    'completed' => $hasCompleted,
                    'start_time' => $shiftStart ? $shiftStart->time : null,
                    'end_time' => $shiftEnd ? $shiftEnd->time : null
                ],
                'work_minutes' => $dayWorkMinutes,
                'break_minutes' => $dayBreakMinutes,
                'offsite_minutes' => $dayOffsiteMinutes,
                'absence' => $absenceInfo
            ];
        }

        // Calculate expected work minutes and variance
        $expectedWorkMinutes = $monthlyStats['business_days'] * $contractMinutesPerDay;
        $monthVariance = $monthlyStats['total_work_minutes'] - $expectedWorkMinutes;

        // Format total times
        $formattedTimes = [];
        foreach (['total_work_minutes', 'total_break_minutes', 'total_offsite_minutes'] as $key) {
            $minutes = $monthlyStats[$key];
            $formattedTimes[$key] = [
                'minutes' => $minutes,
                'hours' => round($minutes / 60, 1),
                'formatted' => sprintf('%02d:%02d', floor($minutes / 60), $minutes % 60)
            ];
        }

        // Format absence types as array
        $absenceTypesArray = array_values($monthlyStats['absence_types']);

        return response()->json([
            'year' => $year,
            'month' => $month,
            'month_name' => $startDate->format('F'),
            'employee' => $employee->load('user'),
            'days' => $dailyReports,
            'summary' => [
                'business_days' => $monthlyStats['business_days'],
                'days_worked' => $monthlyStats['days_worked'],
                'days_absent' => $monthlyStats['days_absent'],
                'days_incomplete' => $monthlyStats['days_incomplete'],
                'absence_types' => $absenceTypesArray,
                'work_time' => $formattedTimes['total_work_minutes'],
                'break_time' => $formattedTimes['total_break_minutes'],
                'offsite_time' => $formattedTimes['total_offsite_minutes'],
                'expected_work_time' => [
                    'minutes' => $expectedWorkMinutes,
                    'hours' => round($expectedWorkMinutes / 60, 1),
                    'formatted' => sprintf('%02d:%02d', floor($expectedWorkMinutes / 60), $expectedWorkMinutes % 60)
                ],
                'time_variance' => [
                    'minutes' => $monthVariance,
                    'hours' => round($monthVariance / 60, 1),
                    'formatted' => sprintf(
                        '%s%02d:%02d',
                        $monthVariance < 0 ? '-' : '+',
                        floor(abs($monthVariance) / 60),
                        abs($monthVariance) % 60
                    )
                ],
                'attendance_rate' => $monthlyStats['business_days'] > 0
                    ? round((($monthlyStats['days_worked'] + $monthlyStats['days_incomplete']) / $monthlyStats['business_days']) * 100)
                    : 0,
                'completion_rate' => ($monthlyStats['days_worked'] + $monthlyStats['days_incomplete']) > 0
                    ? round(($monthlyStats['days_worked'] / ($monthlyStats['days_worked'] + $monthlyStats['days_incomplete'])) * 100)
                    : 0
            ]
        ]);
    }
}
<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Absence;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AbsenceController extends Controller
{
    /**
     * Display a listing of the absences.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $request->validate([
            'employee_id' => 'sometimes|required|uuid|exists:employees,id',
            'company_id' => 'sometimes|required|uuid|exists:companies,id',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'absence_type_id' => 'sometimes|required|uuid|exists:absence_types,id',
        ]);
        
        if ($request->has('company_id')) {
            Gate::authorize('viewAnyForCompany', [Absence::class, $request->company_id]);
            
            $query = Absence::whereHas('employee', function ($q) use ($request) {
                $q->where('company_id', $request->company_id);
            });
        } elseif ($request->has('employee_id')) {
            $employee = Employee::findOrFail($request->employee_id);
            Gate::authorize('view', $employee);
            
            $query = Absence::where('employee_id', $request->employee_id);
        } else {
            Gate::authorize('viewAny', Absence::class);
            
            $query = Absence::query();
        }
        
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        } elseif ($request->has('start_date')) {
            $query->where('date', '>=', $request->start_date);
        } elseif ($request->has('end_date')) {
            $query->where('date', '<=', $request->end_date);
        }
        
        if ($request->has('absence_type_id')) {
            $query->where('absence_type_id', $request->absence_type_id);
        }
        
        $absences = $query->with(['employee.user', 'absenceType', 'request'])
            ->orderBy('date')
            ->get();

        return response()->json($absences);
    }

    /**
     * Store a newly created absence in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Absence::class);

        $validated = $request->validate([
            'employee_id' => 'required|uuid|exists:employees,id',
            'absence_type_id' => 'required|uuid|exists:absence_types,id',
            'request_id' => 'nullable|uuid|exists:absence_requests,id',
            'date' => 'required|date',
            'is_partial' => 'boolean',
            'start_time' => 'required_if:is_partial,true|nullable|date_format:H:i',
            'end_time' => 'required_if:is_partial,true|nullable|date_format:H:i|after:start_time',
            'notes' => 'nullable|string|max:500',
        ]);

        $absence = Absence::create($validated);
        $absence->load(['employee.user', 'absenceType', 'request']);

        return response()->json($absence, 201);
    }

    /**
     * Display the specified absence.
     *
     * @param  \App\Models\Absence  $absence
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Absence $absence)
    {
        Gate::authorize('view', $absence);

        $absence->load(['employee.user', 'absenceType', 'request']);

        return response()->json($absence);
    }

    /**
     * Update the specified absence in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Absence  $absence
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Absence $absence)
    {
        Gate::authorize('update', $absence);

        // Don't allow updating absences that were created by a request
        if ($absence->request_id) {
            return response()->json([
                'message' => 'Cannot update absence that is linked to a request.',
            ], 422);
        }

        $validated = $request->validate([
            'date' => 'sometimes|required|date',
            'is_partial' => 'boolean',
            'start_time' => 'required_if:is_partial,true|nullable|date_format:H:i',
            'end_time' => 'required_if:is_partial,true|nullable|date_format:H:i|after:start_time',
            'notes' => 'nullable|string|max:500',
        ]);

        $absence->update($validated);
        $absence->load(['employee.user', 'absenceType', 'request']);

        return response()->json($absence);
    }

    /**
     * Remove the specified absence from storage.
     *
     * @param  \App\Models\Absence  $absence
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Absence $absence)
    {
        Gate::authorize('delete', $absence);

        // Don't allow deleting absences that were created by a request
        if ($absence->request_id) {
            return response()->json([
                'message' => 'Cannot delete absence that is linked to a request.',
            ], 422);
        }

        $absence->delete();

        return response()->json(null, 204);
    }
    
    /**
     * Get employee absence calendar.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function calendar(Request $request)
    {
        $request->validate([
            'employee_id' => 'sometimes|required|uuid|exists:employees,id',
            'company_id' => 'sometimes|required|uuid|exists:companies,id',
            'year' => 'required|integer|min:2000|max:' . (date('Y') + 1),
            'month' => 'required|integer|min:1|max:12',
        ]);
        
        if ($request->has('company_id')) {
            Gate::authorize('viewAnyForCompany', [Absence::class, $request->company_id]);
            
            $query = Absence::whereHas('employee', function ($q) use ($request) {
                $q->where('company_id', $request->company_id);
            });
        } elseif ($request->has('employee_id')) {
            $employee = Employee::findOrFail($request->employee_id);
            Gate::authorize('view', $employee);
            
            $query = Absence::where('employee_id', $request->employee_id);
        } else {
            Gate::authorize('viewAny', Absence::class);
            
            $query = Absence::query();
        }
        
        $startDate = \Carbon\Carbon::createFromDate($request->year, $request->month, 1)->startOfMonth();
        $endDate = clone $startDate;
        $endDate->endOfMonth();
        
        $absences = $query->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->with(['employee.user', 'absenceType'])
            ->get();
            
        // Group absences by date
        $calendarData = [];
        foreach ($absences as $absence) {
            $date = $absence->date->format('Y-m-d');
            
            if (!isset($calendarData[$date])) {
                $calendarData[$date] = [
                    'date' => $date,
                    'absences' => [],
                ];
            }
            
            $calendarData[$date]['absences'][] = [
                'id' => $absence->id,
                'employee' => [
                    'id' => $absence->employee->id,
                    'name' => $absence->employee->user->name,
                ],
                'type' => [
                    'id' => $absence->absenceType->id,
                    'name' => $absence->absenceType->name,
                    'code' => $absence->absenceType->code,
                ],
                'is_partial' => $absence->is_partial,
                'start_time' => $absence->start_time,
                'end_time' => $absence->end_time,
            ];
        }
        
        return response()->json([
            'year' => $request->year,
            'month' => $request->month,
            'calendar' => array_values($calendarData),
        ]);
    }
}
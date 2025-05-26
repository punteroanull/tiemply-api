<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the employees.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $request->validate([
            'company_id' => 'sometimes|required|uuid|exists:companies,id',
        ]);
        
        if ($request->has('company_id')) {
            $company = Company::findOrFail($request->company_id);
            Gate::authorize('view', $company);
            
            $employees = $company->employees()->with('user')->get();
        } else {
            Gate::authorize('viewAny', Employee::class);
            
            $employees = Employee::with(['user', 'company'])->get();
        }

        return response()->json($employees);
    }

    /**
     * Store a newly created employee in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|uuid|exists:companies,id',
            'user_id' => 'required|uuid|exists:users,id',
            'contract_start_time' => 'required|date_format:H:i',
            'contract_end_time' => 'required|date_format:H:i|after:contract_start_time',
            'remaining_vacation_days' => 'required|integer|min:0',
            'active' => 'boolean',
        ]);

        $company = Company::findOrFail($validated['company_id']);
        Gate::authorize('update', $company);

        // Check if employee already exists
        $exists = Employee::where('company_id', $validated['company_id'])
            ->where('user_id', $validated['user_id'])
            ->exists();
            
        if ($exists) {
            return response()->json([
                'message' => 'User is already an employee of this company.',
            ], 422);
        }

        $employee = Employee::create($validated);
        $employee->load(['user', 'company']);

        return response()->json($employee, 201);
    }

    /**
     * Display the specified employee.
     *
     * @param  \App\Models\Employee  $employee
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Employee $employee)
    {
        Gate::authorize('view', $employee);

        $employee->load(['user', 'company']);

        return response()->json($employee);
    }

    /**
     * Update the specified employee in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Employee  $employee
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Employee $employee)
    {
        Gate::authorize('update', $employee);

        $validated = $request->validate([
            'contract_start_time' => 'sometimes|required|date_format:H:i',
            'contract_end_time' => 'sometimes|required|date_format:H:i|after:contract_start_time',
            'remaining_vacation_days' => 'sometimes|required|integer|min:0',
            'active' => 'sometimes|boolean',
        ]);

        $employee->update($validated);
        $employee->load(['user', 'company']);

        return response()->json($employee);
    }

    /**
     * Remove the specified employee from storage.
     *
     * @param  \App\Models\Employee  $employee
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Employee $employee)
    {
        Gate::authorize('delete', $employee);

        $employee->delete();

        return response()->json(null, 204);
    }
    
    /**
     * Get the work logs for an employee.
     *
     * @param  \App\Models\Employee  $employee
     * @return \Illuminate\Http\JsonResponse
     */
    public function workLogs(Request $request, Employee $employee)
    {
        Gate::authorize('view', $employee);

        $request->validate([
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
        ]);

        $query = $employee->workLogs();
        
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        } elseif ($request->has('start_date')) {
            $query->where('date', '>=', $request->start_date);
        } elseif ($request->has('end_date')) {
            $query->where('date', '<=', $request->end_date);
        }
        
        $workLogs = $query->orderBy('date')->orderBy('time')->get();

        return response()->json($workLogs);
    }
    
    /**
     * Get the absences for an employee.
     *
     * @param  \App\Models\Employee  $employee
     * @return \Illuminate\Http\JsonResponse
     */
    public function absences(Request $request, Employee $employee)
    {
        Gate::authorize('view', $employee);

        $request->validate([
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'type' => 'sometimes|required|string|exists:absence_types,code',
        ]);

        $query = $employee->absences()->with('absenceType');
        
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        } elseif ($request->has('start_date')) {
            $query->where('date', '>=', $request->start_date);
        } elseif ($request->has('end_date')) {
            $query->where('date', '<=', $request->end_date);
        }
        
        if ($request->has('type')) {
            $query->whereHas('absenceType', function ($q) use ($request) {
                $q->where('code', $request->type);
            });
        }
        
        $absences = $query->orderBy('date')->get();

        return response()->json($absences);
    }
    
    /**
     * Get the absence requests for an employee.
     *
     * @param  \App\Models\Employee  $employee
     * @return \Illuminate\Http\JsonResponse
     */
    public function absenceRequests(Request $request, Employee $employee)
    {
        Gate::authorize('view', $employee);

        $request->validate([
            'status' => 'sometimes|required|in:pending,approved,rejected',
        ]);

        $query = $employee->absenceRequests()->with('absenceType');
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $absenceRequests = $query->orderBy('created_at', 'desc')->get();

        return response()->json($absenceRequests);
    }

    /**
     * Get the company settings for an employee.
     *
     * @param  int  $employeeId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCompanySettings($employeeId)
    {
        $employee = Employee::with('company')->findOrFail($employeeId);
        
        // Verificar que el usuario actual puede acceder a este empleado
        if (auth()->user()->id !== $employee->user_id) {
            abort(403, 'Unauthorized');
        }
        
        return response()->json([
            'geolocation_enabled' => $employee->company->geolocation_enabled,
            'geolocation_required' => $employee->company->geolocation_required,
            'geolocation_radius' => $employee->company->geolocation_radius,
            'office_latitude' => $employee->company->office_latitude,
            'office_longitude' => $employee->company->office_longitude,
            'office_address' => $employee->company->office_address,
        ]);
    }
}
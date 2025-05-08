<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\Absence;
use App\Models\Employee;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AbsenceRequestController extends Controller
{
    /**
     * Display a listing of the absence requests.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $request->validate([
            'status' => 'sometimes|required|in:pending,approved,rejected',
            'employee_id' => 'sometimes|required|uuid|exists:employees,id',
            'company_id' => 'sometimes|required|uuid|exists:companies,id',
        ]);
        
        if ($request->has('company_id')) {
            Gate::authorize('viewAnyForCompany', [AbsenceRequest::class, $request->company_id]);
            
            $query = AbsenceRequest::whereHas('employee', function ($q) use ($request) {
                $q->where('company_id', $request->company_id);
            });
        } else {
            Gate::authorize('viewAny', AbsenceRequest::class);
            
            $query = AbsenceRequest::query();
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        
        $absenceRequests = $query->with(['employee.user', 'absenceType', 'reviewer'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($absenceRequests);
    }

    /**
     * Store a newly created absence request in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|uuid|exists:employees,id',
            'absence_type_id' => 'required|uuid|exists:absence_types,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_partial' => 'boolean',
            'start_time' => 'required_if:is_partial,true|nullable|date_format:H:i',
            'end_time' => 'required_if:is_partial,true|nullable|date_format:H:i|after:start_time',
            'notes' => 'nullable|string|max:500',
        ]);
        
        $employee = Employee::findOrFail($validated['employee_id']);
        $absenceType = AbsenceType::findOrFail($validated['absence_type_id']);
        
        // Verify user can create request for this employee
        if (auth()->id() !== $employee->user_id && Gate::denies('createFor', [AbsenceRequest::class, $employee])) {
            return response()->json(['message' => 'Unauthorized to create absence requests for this employee'], 403);
        }
        
        // Create a model instance to use policy methods
        $absenceRequest = new AbsenceRequest($validated);
        $absenceRequest->employee_id = $employee->id;
        $absenceRequest->absence_type_id = $absenceType->id;
        
        // Check if request needs minimum notice (24 hours)
        if ($absenceRequest->needsMinimumNotice()) {
            $minRequestDate = Carbon::now()->addHours(24);
            
            if (Carbon::parse($validated['start_date'])->lt($minRequestDate)) {
                return response()->json([
                    'message' => 'Vacation requests for consecutive days must be made at least 24 hours in advance.',
                ], 422);
            }
        }
        
        // Check if request meets minimum consecutive days requirement
        if (!$absenceRequest->meetsConsecutiveDaysRequirement()) {
            return response()->json([
                'message' => 'Vacation requests using calendar days must be for at least 7 consecutive days.',
            ], 422);
        }
        
        // Check available vacation days if applicable
        if ($absenceType->affects_vacation_balance) {
            $daysRequested = $absenceRequest->getDaysCountAttribute();
            
            if ($daysRequested > $employee->remaining_vacation_days) {
                return response()->json([
                    'message' => 'Not enough vacation days available.',
                    'days_requested' => $daysRequested,
                    'days_available' => $employee->remaining_vacation_days,
                ], 422);
            }
        }
        
        // Set default status based on whether approval is required
        if (!$absenceType->requires_approval) {
            $validated['status'] = 'approved';
        }
        
        // Create the request
        $absenceRequest = AbsenceRequest::create($validated);
        
        // If auto-approved, create the absence records
        if (!$absenceType->requires_approval) {
            $this->createAbsenceRecords($absenceRequest);
            
            // Update vacation balance if applicable
            if ($absenceType->affects_vacation_balance) {
                $daysRequested = $absenceRequest->getDaysCountAttribute();
                $employee->remaining_vacation_days -= $daysRequested;
                $employee->save();
            }
        }
        
        $absenceRequest->load(['employee.user', 'absenceType']);
        
        return response()->json($absenceRequest, 201);
    }

    /**
     * Display the specified absence request.
     *
     * @param  \App\Models\AbsenceRequest  $absenceRequest
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(AbsenceRequest $absenceRequest)
    {
        Gate::authorize('view', $absenceRequest);

        $absenceRequest->load(['employee.user', 'absenceType', 'reviewer']);

        return response()->json($absenceRequest);
    }

    /**
     * Update the specified absence request in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\AbsenceRequest  $absenceRequest
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, AbsenceRequest $absenceRequest)
    {
        Gate::authorize('update', $absenceRequest);

        // Only pending requests can be updated
        if (!$absenceRequest->isPending()) {
            return response()->json([
                'message' => 'Only pending requests can be updated.',
            ], 422);
        }

        $validated = $request->validate([
            'start_date' => 'sometimes|required|date|after_or_equal:today',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'is_partial' => 'boolean',
            'start_time' => 'required_if:is_partial,true|nullable|date_format:H:i',
            'end_time' => 'required_if:is_partial,true|nullable|date_format:H:i|after:start_time',
            'notes' => 'nullable|string|max:500',
        ]);

        $absenceRequest->update($validated);
        $absenceRequest->load(['employee.user', 'absenceType']);

        return response()->json($absenceRequest);
    }
    
    /**
     * Review an absence request (approve or reject).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\AbsenceRequest  $absenceRequest
     * @return \Illuminate\Http\JsonResponse
     */
    public function review(Request $request, AbsenceRequest $absenceRequest)
    {
        Gate::authorize('review', $absenceRequest);

        // Only pending requests can be reviewed
        if (!$absenceRequest->isPending()) {
            return response()->json([
                'message' => 'Only pending requests can be reviewed.',
            ], 422);
        }

        $validated = $request->validate([
            'status' => 'required|in:approved,rejected',
            'rejection_reason' => 'required_if:status,rejected|nullable|string|max:500',
        ]);

        DB::transaction(function () use ($absenceRequest, $validated, $request) {
            $absenceRequest->status = $validated['status'];
            $absenceRequest->reviewed_by = auth()->id();
            $absenceRequest->reviewed_at = now();
            
            if ($validated['status'] === 'rejected') {
                $absenceRequest->rejection_reason = $validated['rejection_reason'];
            } else {
                // If approved, create absence records
                $this->createAbsenceRecords($absenceRequest);
                
                // Update vacation balance if applicable
                if ($absenceRequest->absenceType->affects_vacation_balance) {
                    $daysRequested = $absenceRequest->getDaysCountAttribute();
                    $employee = $absenceRequest->employee;
                    $employee->remaining_vacation_days -= $daysRequested;
                    $employee->save();
                }
            }
            
            $absenceRequest->save();
        });

        $absenceRequest->load(['employee.user', 'absenceType', 'reviewer']);

        return response()->json($absenceRequest);
    }

    /**
     * Approve an absence request.
     *
     * @param  \App\Models\AbsenceRequest  $absenceRequest
     * @return \Illuminate\Http\JsonResponse
     */
    public function approve(AbsenceRequest $request)
    {
        $httpRequest = new Request([
            'status' => 'approved'
        ]);
        
        return $this->review($httpRequest, $request);
    }

    /**
     * Reject an absence request.
     *
     * @param  \Illuminate\Http\Request  $httpRequest
     * @param  \App\Models\AbsenceRequest  $absenceRequest
     * @return \Illuminate\Http\JsonResponse
     */
    public function reject(Request $httpRequest, AbsenceRequest $request)
    {
        $validated = $httpRequest->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);
        
        $httpRequest = new Request([
            'status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason']
        ]);
        
        return $this->review($httpRequest, $request);
    }

    /**
     * Remove the specified absence request from storage.
     *
     * @param  \App\Models\AbsenceRequest  $absenceRequest
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(AbsenceRequest $absenceRequest)
    {
        Gate::authorize('delete', $absenceRequest);

        // Only pending requests can be deleted
        if (!$absenceRequest->isPending()) {
            return response()->json([
                'message' => 'Only pending requests can be deleted.',
            ], 422);
        }

        $absenceRequest->delete();

        return response()->json(null, 204);
    }


    /**
     * Get absence requests by employee.
     *
     * @param  \App\Models\Employee  $employee
     * @return \Illuminate\Http\JsonResponse
     */
    public function byEmployee(Employee $employee)
    {
        Gate::authorize('view', $employee);
        
        $requests = $employee->absenceRequests()
            ->with(['absenceType', 'reviewer'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($requests);
    }

    
    /**
     * Get pending absence requests by employee.
     *
     * @param  \App\Models\Employee  $employee
     * @return \Illuminate\Http\JsonResponse
     */
    public function pendingByEmployee(Employee $employee)
    {
        Gate::authorize('view', $employee);
        
        $requests = $employee->absenceRequests()
            ->where('status', 'pending')
            ->with(['absenceType', 'reviewer'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($requests);
    }

    /**
     * Get absence requests by company.
     *
     * @param  \App\Models\Company  $company
     * @return \Illuminate\Http\JsonResponse
     */
    public function byCompany(Company $company)
    {
        Gate::authorize('view', $company);
        
        $requests = AbsenceRequest::whereHas('employee', function ($query) use ($company) {
            $query->where('company_id', $company->id);
        })
        ->with(['employee.user', 'absenceType', 'reviewer'])
        ->orderBy('created_at', 'desc')
        ->get();
            
        return response()->json($requests);
    }

    /**
     * Get pending absence requests by company.
     *
     * @param  \App\Models\Company  $company
     * @return \Illuminate\Http\JsonResponse
     */
    public function pendingByCompany(Company $company)
    {
        Gate::authorize('view', $company);
        
        $requests = AbsenceRequest::whereHas('employee', function ($query) use ($company) {
            $query->where('company_id', $company->id);
        })
        ->where('status', 'pending')
        ->with(['employee.user', 'absenceType'])
        ->orderBy('created_at', 'desc')
        ->get();
            
        return response()->json($requests);
    }
    
    /**
     * Create absence records for an approved request.
     *
     * @param  \App\Models\AbsenceRequest  $absenceRequest
     * @return void
     */
    private function createAbsenceRecords(AbsenceRequest $absenceRequest)
    {
        $startDate = Carbon::parse($absenceRequest->start_date);
        $endDate = Carbon::parse($absenceRequest->end_date);
        $isPartial = $absenceRequest->is_partial;
        $startTime = $absenceRequest->start_time;
        $endTime = $absenceRequest->end_time;
        
        $currentDate = clone $startDate;
        
        while ($currentDate->lte($endDate)) {
            // Skip weekends if company uses business days
            if ($absenceRequest->employee->company->vacation_type === 'business_days' && $currentDate->isWeekend()) {
                $currentDate->addDay();
                continue;
            }
            
            Absence::create([
                'employee_id' => $absenceRequest->employee_id,
                'absence_type_id' => $absenceRequest->absence_type_id,
                'request_id' => $absenceRequest->id,
                'date' => $currentDate->toDateString(),
                'is_partial' => $isPartial,
                'start_time' => $isPartial ? $startTime : null,
                'end_time' => $isPartial ? $endTime : null,
                'notes' => $absenceRequest->notes,
            ]);
            
            $currentDate->addDay();
        }
    }
}
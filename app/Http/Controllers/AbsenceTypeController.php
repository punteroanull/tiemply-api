<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AbsenceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AbsenceTypeController extends Controller
{
    /**
     * Display a listing of the absence types.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $absenceTypes = AbsenceType::all();

        return response()->json($absenceTypes);
    }

    /**
     * Store a newly created absence type in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        Gate::authorize('create', AbsenceType::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:absence_types',
            'description' => 'nullable|string',
            'requires_approval' => 'boolean',
            'affects_vacation_balance' => 'boolean',
            'is_paid' => 'boolean',
        ]);

        $absenceType = AbsenceType::create($validated);

        return response()->json($absenceType, 201);
    }

    /**
     * Display the specified absence type.
     *
     * @param  \App\Models\AbsenceType  $absenceType
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(AbsenceType $absenceType)
    {
        return response()->json($absenceType);
    }

    /**
     * Update the specified absence type in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\AbsenceType  $absenceType
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, AbsenceType $absenceType)
    {
        Gate::authorize('update', $absenceType);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'code' => 'sometimes|required|string|max:50|unique:absence_types,code,' . $absenceType->id,
            'description' => 'nullable|string',
            'requires_approval' => 'boolean',
            'affects_vacation_balance' => 'boolean',
            'is_paid' => 'boolean',
        ]);

        $absenceType->update($validated);

        return response()->json($absenceType);
    }

    /**
     * Remove the specified absence type from storage.
     *
     * @param  \App\Models\AbsenceType  $absenceType
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(AbsenceType $absenceType)
    {
        Gate::authorize('delete', $absenceType);

        // Check if there are absences or requests associated with this type
        if ($absenceType->absences()->exists() || $absenceType->absenceRequests()->exists()) {
            return response()->json([
                'message' => 'Cannot delete absence type because it has associated absences or requests.',
            ], 409);
        }

        $absenceType->delete();

        return response()->json(null, 204);
    }
}
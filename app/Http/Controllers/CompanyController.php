<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CompanyController extends Controller
{
    /**
     * Display a listing of the companies.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // Only administrators can see all companies
        if (Gate::denies('viewAny', Company::class)) {
            // Regular users can only see companies they belong to
            $companies = auth()->user()->companies;
        } else {
            $companies = Company::all();
        }

        return response()->json($companies);
    }

    /**
     * Store a newly created company in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        Gate::authorize('create', Company::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'tax_id' => 'required|string|max:20|unique:companies',
            'contact_email' => 'required|email|max:255',
            'contact_person' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'vacation_type' => 'required|in:business_days,calendar_days',
            'max_vacation_days' => 'required|integer|min:1|max:365',
        ]);

        $company = Company::create($validated);

        return response()->json($company, 201);
    }

    /**
     * Display the specified company.
     *
     * @param  \App\Models\Company  $company
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Company $company)
    {
        Gate::authorize('view', $company);

        return response()->json($company);
    }

    /**
     * Update the specified company in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Company  $company
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Company $company)
    {
        Gate::authorize('update', $company);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'tax_id' => 'sometimes|required|string|max:20|unique:companies,tax_id,' . $company->id,
            'contact_email' => 'sometimes|required|email|max:255',
            'contact_person' => 'sometimes|required|string|max:255',
            'address' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'vacation_type' => 'sometimes|required|in:business_days,calendar_days',
            'max_vacation_days' => 'sometimes|required|integer|min:1|max:365',
        ]);

        $company->update($validated);

        return response()->json($company);
    }

    /**
     * Remove the specified company from storage.
     *
     * @param  \App\Models\Company  $company
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Company $company)
    {
        Gate::authorize('delete', $company);

        $company->delete();

        return response()->json(null, 204);
    }
    
    /**
     * Get all employees for a company.
     *
     * @param  \App\Models\Company  $company
     * @return \Illuminate\Http\JsonResponse
     */
    public function employees(Company $company)
    {
        Gate::authorize('view', $company);

        $employees = $company->employees()->with('user')->get();

        return response()->json($employees);
    }
}
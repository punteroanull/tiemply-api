<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\WorkLogController;
use App\Http\Controllers\AbsenceController;
use App\Http\Controllers\AbsenceRequestController;
use App\Http\Controllers\AbsenceTypeController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| API routes for tiemply. 
| 
*/

// Public routes
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/register', [AuthController::class, 'register'])->name('register');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me'])->name('user.profile');

    // Users routes
    Route::apiResource('users', UserController::class);

    // Companies routes
    Route::apiResource('companies', CompanyController::class);
    Route::get('/companies/{company}/employees', [CompanyController::class, 'employees'])->name('companies.employees');
    
    // Employees routes
    Route::apiResource('employees', EmployeeController::class);
    Route::get('/employees/{employee}/work-logs', [EmployeeController::class, 'workLogs'])->name('employees.work-logs');
    Route::get('/employees/{employee}/absences', [EmployeeController::class, 'absences'])->name('employees.absences');
    Route::get('/employees/{employee}/absence-requests', [EmployeeController::class, 'absenceRequests'])->name('employees.absence-requests');

    // Roles routes
    Route::apiResource('roles', RoleController::class);

    // WorkLogs routes
    Route::apiResource('worklogs', WorkLogController::class);
    Route::post('/worklogs/check-in', [WorkLogController::class, 'checkIn']);
    Route::post('/worklogs/check-out', [WorkLogController::class, 'checkOut']);
    Route::get('/worklogs/status/{employeeId}', [WorkLogController::class, 'getEmployeeStatus']);
    Route::get('/worklogs/daily-report/{employeeId}/{period?}', [WorkLogController::class, 'dailyReport']);
    Route::get('/worklogs/weekly-report/{employeeId}/{period?}', [WorkLogController::class, 'weeklyReport']);
    Route::get('/worklogs/monthly-report/{employeeId}/{year?}/{month?}', [WorkLogController::class, 'monthlyReport']);

    // Absences routes
    Route::apiResource('absences', AbsenceController::class);
    Route::get('/absences/employee/{employee}', [AbsenceController::class, 'byEmployee']);
    Route::get('/absences/employee/{employee}/type/{type}', action: [AbsenceController::class, 'byEmployeeAndType']);
    Route::get('/absences/employee/{employee}/period', [AbsenceController::class, 'byEmployeeAndPeriod']);
    
    // Requests routes
    Route::apiResource('requests', AbsenceRequestController::class);
    Route::get('/requests/employee/{employee}', [AbsenceRequestController::class, 'byEmployee']);
    Route::get('/requests/company/{company}', [AbsenceRequestController::class, 'byCompany']);
    Route::patch('/requests/{request}/approve', [AbsenceRequestController::class, 'approve']);
    Route::patch('/requests/{request}/reject', [AbsenceRequestController::class, 'reject']);
    Route::get('/requests/pending/company/{company}', [AbsenceRequestController::class, 'pendingByCompany']);
});
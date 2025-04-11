<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CompanyController;
use App\Http\Controllers\API\EmployeeController;
use App\Http\Controllers\API\RoleController;
use App\Http\Controllers\API\WorkLogController;
use App\Http\Controllers\API\AbsenceController;
use App\Http\Controllers\API\RequestController;
use App\Http\Controllers\API\UserController;
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
Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Users routes
    Route::apiResource('users', UserController::class);

    // Companies routes
    Route::apiResource('companies', CompanyController::class);
    
    // Employees routes
    Route::apiResource('employees', EmployeeController::class);
    Route::get('/employees/company/{company}', [EmployeeController::class, 'byCompany']);

    // Roles routes
    Route::apiResource('roles', RoleController::class);

    // WorkLogs routes
    Route::apiResource('worklogs', WorkLogController::class);
    Route::post('/worklogs/check-in', [WorkLogController::class, 'checkIn']);
    Route::post('/worklogs/check-out', [WorkLogController::class, 'checkOut']);
    Route::get('/worklogs/employee/{employee}', [WorkLogController::class, 'byEmployee']);
    Route::get('/worklogs/employee/{employee}/today', [WorkLogController::class, 'todayByEmployee']);
    Route::get('/worklogs/employee/{employee}/date/{date}', [WorkLogController::class, 'byEmployeeAndDate']);
    Route::get('/worklogs/employee/{employee}/period', [WorkLogController::class, 'byEmployeeAndPeriod']);

    // Absences routes
    Route::apiResource('absences', AbsenceController::class);
    Route::get('/absences/employee/{employee}', [AbsenceController::class, 'byEmployee']);
    Route::get('/absences/employee/{employee}/type/{type}', [AbsenceController::class, 'byEmployeeAndType']);
    Route::get('/absences/employee/{employee}/period', [AbsenceController::class, 'byEmployeeAndPeriod']);
    
    // Requests routes
    Route::apiResource('requests', RequestController::class);
    Route::get('/requests/employee/{employee}', [RequestController::class, 'byEmployee']);
    Route::get('/requests/company/{company}', [RequestController::class, 'byCompany']);
    Route::patch('/requests/{request}/approve', [RequestController::class, 'approve']);
    Route::patch('/requests/{request}/reject', [RequestController::class, 'reject']);
    Route::get('/requests/pending/company/{company}', [RequestController::class, 'pendingByCompany']);
});
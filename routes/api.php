<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\WorkLogController;
use App\Http\Controllers\AbsenceController;
use App\Http\Controllers\RequestController;
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
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Users routes
    //Route::apiResource('users', UserController::class);
    Route::get('/users', [UserController::class, 'index']); // admin only
    Route::post('/users', [UserController::class, 'store']); // admin only
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']); // admin only

    // Companies routes
    //Route::apiResource('companies', CompanyController::class);
    Route::get('/companies', [CompanyController::class, 'index']);
    Route::post('/companies', [CompanyController::class, 'store']); // admin only
    Route::get('/companies/{id}', [CompanyController::class, 'show']);
    Route::put('/companies/{id}', [CompanyController::class, 'update']);
    Route::delete('/companies/{id}', [CompanyController::class, 'destroy']); // admin only
    Route::get('/companies/{id}/employees', [CompanyController::class, 'employees']);
    
    // Employees routes
    //Route::apiResource('employees', EmployeeController::class);
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::get('/employees/{id}', [EmployeeController::class, 'show']);
    Route::put('/employees/{id}', [EmployeeController::class, 'update']);
    Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
    Route::get('/employees/{id}/worklogs', [EmployeeController::class, 'worklogs']);
    Route::get('/employees/{id}/absences', [EmployeeController::class, 'absences']);
    Route::get('/employees/{id}/requests', [EmployeeController::class, 'requests'])

    // Roles routes
    //Route::apiResource('roles', RoleController::class);
    Route::get('/roles', [RoleController::class, 'index']); // admin only
    Route::post('/roles', [RoleController::class, 'store']); // admin only
    Route::get('/roles/{id}', [RoleController::class, 'show']);
    Route::put('/roles/{id}', [RoleController::class, 'update']);
    Route::delete('/roles/{id}', [RoleController::class, 'destroy']); // admin only

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

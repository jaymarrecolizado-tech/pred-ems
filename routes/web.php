<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — DICT RO2 HRIS
|--------------------------------------------------------------------------
| Phase 1: auth + dashboard + employee profiles.
*/

Route::get('/', fn () => redirect()->route('dashboard'));

// Auth
Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('login.attempt');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // My Profile — self-service (personal info, photo, password)
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto'])->name('profile.photo.upload');
    Route::delete('/profile/photo', [ProfileController::class, 'removePhoto'])->name('profile.photo.remove');
    Route::get('/profile/password', [ProfileController::class, 'password'])->name('profile.password');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    // Audit trail viewer — admin/HR only
    Route::get('/audit-logs', [AuditLogController::class, 'index'])
        ->middleware('role:admin,hr')
        ->name('audit-logs.index');

    // Employee profiles — RBAC enforcement per the permission model.
    // Read access (directory + own profile) for staff roles; write access admin/HR only.
    // Note: '/employees/create' must be registered BEFORE '/employees/{employee}'.
    Route::get('/employees', [EmployeeController::class, 'index'])
        ->middleware('role:admin,hr,payroll,unit_head')
        ->name('employees.index');

    // Own 201-file: every authenticated employee may view their own record
    // (the controller enforces own-only for roles without view.employees).
    Route::get('/employees/create', [EmployeeController::class, 'create'])
        ->middleware('role:admin,hr')
        ->name('employees.create');
    Route::get('/employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');

    Route::middleware('role:admin,hr')->group(function () {
        Route::post('/employees', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
        Route::delete('/employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    });
});

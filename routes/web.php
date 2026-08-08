<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AttendanceAdminController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CoeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentRequestController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\LeaveController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ServiceRecordController;
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

    // In-system notifications (bell + inbox) — every authenticated user
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');

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

    // Leave module — self-service filing + approval workflow (Phase 2)
    Route::get('/leave', [LeaveController::class, 'index'])->name('leave.index');
    Route::get('/leave/create', [LeaveController::class, 'create'])->name('leave.create');
    Route::post('/leave', [LeaveController::class, 'store'])->name('leave.store');
    Route::post('/leave/{application}/cancel', [LeaveController::class, 'cancel'])->name('leave.cancel');

    Route::middleware('role:admin,hr')->group(function () {
        Route::get('/leave/approvals', [LeaveController::class, 'approvals'])->name('leave.approvals');
        Route::post('/leave/{application}/approve', [LeaveController::class, 'approve'])->name('leave.approve');
        Route::post('/leave/{application}/reject', [LeaveController::class, 'reject'])->name('leave.reject');
    });

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

    // Appointment Manager (Phase 3) — admin/HR maintain the service history
    // that drives the CSC Service Record. Employees never touch these.
    Route::middleware('role:admin,hr')->group(function () {
        Route::get('/employees/{employee}/appointments/create', [AppointmentController::class, 'create'])->name('appointments.create');
        Route::post('/employees/{employee}/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::get('/appointments/{appointment}/edit', [AppointmentController::class, 'edit'])->name('appointments.edit');
        Route::put('/appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
        Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])->name('appointments.destroy');
    });

    // Official documents (Phase 3 — CSC Form 212 + COE)
    Route::get('/employees/{employee}/service-record', [ServiceRecordController::class, 'show'])->name('employees.service-record');
    Route::get('/employees/{employee}/service-record/pdf', [ServiceRecordController::class, 'download'])->name('employees.service-record.pdf');
    Route::get('/employees/{employee}/coe', [CoeController::class, 'show'])->name('employees.coe');
    Route::get('/employees/{employee}/coe/pdf', [CoeController::class, 'download'])->name('employees.coe.pdf');

    // Document requests (Phase 3.5) — self-service request + HR fulfillment queue
    // Note: 'create'/'queue' must be registered before '/{documentRequest}'.
    Route::get('/documents/requests', [DocumentRequestController::class, 'index'])->name('documents.requests');
    Route::get('/documents/requests/create', [DocumentRequestController::class, 'create'])->name('documents.requests.create');
    Route::post('/documents/requests', [DocumentRequestController::class, 'store'])->name('documents.requests.store');
    Route::get('/documents/requests/queue', [DocumentRequestController::class, 'queue'])
        ->middleware('role:admin,hr')
        ->name('documents.requests.queue');
    Route::post('/documents/requests/{documentRequest}/cancel', [DocumentRequestController::class, 'cancel'])->name('documents.requests.cancel');
    Route::get('/documents/requests/{documentRequest}/download', [DocumentRequestController::class, 'download'])->name('documents.requests.download');
    Route::post('/documents/requests/{documentRequest}/issue', [DocumentRequestController::class, 'issue'])
        ->middleware('role:admin,hr')
        ->name('documents.requests.issue');
    Route::post('/documents/requests/{documentRequest}/reject', [DocumentRequestController::class, 'reject'])
        ->middleware('role:admin,hr')
        ->name('documents.requests.reject');

    // Payroll (Phase 6) — admin/HR/payroll manage runs; every role opens their own payslip
    Route::get('/my/payslips', [PayrollController::class, 'myPayslips'])->name('payroll.my');
    Route::get('/payroll/payslips/{payslip}', [PayrollController::class, 'payslipPdf'])->name('payroll.payslip');

    Route::middleware('role:admin,hr,payroll')->group(function () {
        Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
        Route::post('/payroll', [PayrollController::class, 'store'])->name('payroll.store');
        Route::get('/payroll/remittances', [PayrollController::class, 'remittances'])->name('payroll.remittances');
        Route::post('/payroll/remittances/{remittance}/remit', [PayrollController::class, 'markRemitted'])->name('payroll.remittances.remit');
        Route::get('/payroll/items/{item}/adjust', [PayrollController::class, 'adjust'])->name('payroll.adjust');
        Route::post('/payroll/items/{item}/adjust', [PayrollController::class, 'updateAdjustment'])->name('payroll.adjust.update');
        Route::get('/payroll/{period}', [PayrollController::class, 'show'])->name('payroll.show');
        Route::post('/payroll/{period}/generate', [PayrollController::class, 'generate'])->name('payroll.generate');
        Route::post('/payroll/{period}/finalize', [PayrollController::class, 'finalize'])->name('payroll.finalize');
    });

    // Reports & Audit (Phase 4) — admin/HR only
    Route::middleware('role:admin,hr')->group(function () {
        Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/headcount', [ReportController::class, 'headcount'])->name('reports.headcount');
        Route::get('/reports/leave-balances', [ReportController::class, 'leaveBalances'])->name('reports.leave-balances');
        Route::get('/reports/leave-utilization', [ReportController::class, 'leaveUtilization'])->name('reports.leave-utilization');
        Route::get('/reports/documents', [ReportController::class, 'documents'])->name('reports.documents');
        Route::get('/reports/attrition', [ReportController::class, 'attrition'])->name('reports.attrition');
    });

    // Attendance & DTR (Phase 5) — every employee punches from their device
    Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::post('/attendance/punch', [AttendanceController::class, 'punch'])->name('attendance.punch');
    Route::post('/attendance/corrections', [AttendanceController::class, 'requestCorrection'])->name('attendance.corrections.request');
    Route::get('/attendance/dtr', [AttendanceController::class, 'dtr'])->name('attendance.dtr');
    Route::get('/attendance/dtr/pdf', [AttendanceController::class, 'dtrPdf'])->name('attendance.dtr.pdf');

    Route::middleware('role:admin,hr')->group(function () {
        Route::get('/attendance/checkpoints', [AttendanceAdminController::class, 'checkpoints'])->name('attendance.checkpoints');
        Route::get('/attendance/checkpoints/{checkpoint}/edit', [AttendanceAdminController::class, 'editCheckpoint'])->name('attendance.checkpoints.edit');
        Route::post('/attendance/checkpoints', [AttendanceAdminController::class, 'storeCheckpoint'])->name('attendance.checkpoints.store');
        Route::put('/attendance/checkpoints/{checkpoint}', [AttendanceAdminController::class, 'updateCheckpoint'])->name('attendance.checkpoints.update');
        Route::delete('/attendance/checkpoints/{checkpoint}', [AttendanceAdminController::class, 'destroyCheckpoint'])->name('attendance.checkpoints.destroy');
        Route::get('/attendance/corrections', [AttendanceAdminController::class, 'corrections'])->name('attendance.corrections');
        Route::post('/attendance/corrections/{correction}/approve', [AttendanceAdminController::class, 'approveCorrection'])->name('attendance.corrections.approve');
        Route::post('/attendance/corrections/{correction}/reject', [AttendanceAdminController::class, 'rejectCorrection'])->name('attendance.corrections.reject');
        Route::get('/attendance/logs', [AttendanceAdminController::class, 'logs'])->name('attendance.logs');
        Route::post('/attendance/logs', [AttendanceAdminController::class, 'storeManualPunch'])->name('attendance.logs.store');
        Route::get('/attendance/settings', [AttendanceAdminController::class, 'settings'])->name('attendance.settings');
        Route::get('/attendance/schedules/{schedule}/edit', [AttendanceAdminController::class, 'editSchedule'])->name('attendance.schedules.edit');
        Route::post('/attendance/schedules', [AttendanceAdminController::class, 'storeSchedule'])->name('attendance.schedules.store');
        Route::put('/attendance/schedules/{schedule}', [AttendanceAdminController::class, 'updateSchedule'])->name('attendance.schedules.update');
        Route::delete('/attendance/schedules/{schedule}', [AttendanceAdminController::class, 'destroySchedule'])->name('attendance.schedules.destroy');
        Route::post('/attendance/holidays', [AttendanceAdminController::class, 'storeHoliday'])->name('attendance.holidays.store');
        Route::delete('/attendance/holidays/{holiday}', [AttendanceAdminController::class, 'destroyHoliday'])->name('attendance.holidays.destroy');
        Route::get('/attendance/employees/{employee}/dtr', [AttendanceAdminController::class, 'employeeDtr'])->name('attendance.employees.dtr');
        Route::get('/attendance/employees/{employee}/dtr/pdf', [AttendanceAdminController::class, 'employeeDtrPdf'])->name('attendance.employees.dtr.pdf');
    });
});

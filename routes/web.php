<?php

use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\EmployeeController;
use App\Controllers\AttendanceController;
use App\Controllers\SettingsController;
use App\Controllers\SalaryCalculationController;
use App\Controllers\LanguageController;
use App\Helpers\Router;

/** @var Router $router */

// Authentication
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/logout', [AuthController::class, 'logout']);

// Registration (Admin only - create new system users)
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);

// Dashboard
$router->get('/', [DashboardController::class, 'index']);
$router->get('/dashboard', [DashboardController::class, 'index']);

// Employees
$router->get('/employees', [EmployeeController::class, 'index']);
$router->get('/lang/{code}', [LanguageController::class, 'switch']);
$router->get('/employees/create', [EmployeeController::class, 'create']);
$router->post('/employees', [EmployeeController::class, 'store']);
$router->post('/employees/{id}/deactivate', [EmployeeController::class, 'deactivate']);
$router->post('/employees/{id}/delete', [EmployeeController::class, 'delete']);
$router->get('/employees/{id}/edit', [EmployeeController::class, 'edit']);
$router->post('/employees/{id}', [EmployeeController::class, 'update']);

// Attendance
$router->get('/attendance', [AttendanceController::class, 'index']);
$router->get('/attendance/employee/{id}/download', [AttendanceController::class, 'downloadEmployee']);
$router->get('/attendance/employee/{id}', [AttendanceController::class, 'employee']);

// Delete ALL attendance records belonging to the selected employee
$router->post('/attendance/{id}/delete', [AttendanceController::class, 'delete']);

// NGTeco Office Excel import
$router->post('/attendance/import/preview', [AttendanceController::class, 'upload']);
$router->post('/attendance/import/confirm', [AttendanceController::class, 'confirmImport']);
$router->get('/attendance/import/cancel', [AttendanceController::class, 'cancelImport']);

// Settings (Admin only - includes Users management)
$router->get('/settings', [SettingsController::class, 'index']);
$router->get('/settings/users/{id}/edit', [SettingsController::class, 'editUser']);
$router->post('/settings/users/{id}', [SettingsController::class, 'updateUser']);
$router->post('/settings/departments/{id}/overtime', [SettingsController::class, 'updateDepartmentOT']);

// Holidays (Admin only)
$router->get('/settings/holidays', [SettingsController::class, 'holidays']);
$router->post('/settings/holidays', [SettingsController::class, 'storeHoliday']);
$router->post('/settings/holidays/{id}', [SettingsController::class, 'updateHoliday']);
$router->post('/settings/holidays/{id}/delete', [SettingsController::class, 'deleteHoliday']);

// Salary Calculation
$router->get('/salary-calculation', [SalaryCalculationController::class, 'index']);

// Employee salary details
$router->get(
    '/salary-calculation/{id}/employee/{employeeId}',
    [SalaryCalculationController::class, 'employee']
);

// View generated salary calculation
$router->get(
    '/salary-calculation/{id}',
    [SalaryCalculationController::class, 'view']
);

// Delete one employee's salary calculation from a payroll run
$router->post(
    '/salary-calculation/{id}/line/{lineId}/delete',
    [SalaryCalculationController::class, 'deleteEmployee']
);

// Generate salary calculation
$router->post(
    '/salary-calculation/generate',
    [SalaryCalculationController::class, 'generate']
);

// Delete generated salary calculation
$router->post(
    '/salary-calculation/{id}/delete',
    [SalaryCalculationController::class, 'delete']
);

// Download payroll Excel summary
$router->get(
    '/salary-calculation/{id}/export',
    [SalaryCalculationController::class, 'exportExcel']
);

// Download individual payslip
$router->get(
    '/salary-calculation/{id}/payslip/{employeeId}',
    [SalaryCalculationController::class, 'payslip']
);

// Download individual payslip as PNG image
// IMPORTANT: This route must exist for the "Payslip Image" button.
$router->get(
    '/salary-calculation/{id}/payslip/{employeeId}/download',
    [SalaryCalculationController::class, 'downloadPayslipImage']
);

// Download all individual payslips as ZIP
$router->get(
    '/salary-calculation/{id}/payslips-zip',
    [SalaryCalculationController::class, 'payslipsZip']
);

// Future modules
// $router->get('/payroll-rules', ...);
// $router->get('/payroll-history', ...);
// $router->get('/reports', ...);
// $router->get('/audit-log', ...);

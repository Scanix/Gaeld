<?php

use App\Domains\Payroll\Controllers\EmployeeController;
use App\Domains\Payroll\Controllers\PayrollRunController;
use App\Domains\Payroll\Controllers\SalarySlipController;
use Illuminate\Support\Facades\Route;

Route::get('payroll/employees', [EmployeeController::class, 'index'])->name('payroll.employees.index');
Route::get('payroll/employees/{employee}', [EmployeeController::class, 'show'])->name('payroll.employees.show')->whereUuid('employee');

Route::get('/payroll/salary-slips', [SalarySlipController::class, 'index'])->name('payroll.salarySlips.index');
Route::get('/payroll/salary-slips/{slip}', [SalarySlipController::class, 'show'])->name('payroll.salarySlips.show');
Route::get('/payroll/salary-slips/{slip}/pdf', [SalarySlipController::class, 'downloadPdf'])->name('payroll.salarySlips.pdf');

// Payroll processing is a plan-gated feature: read access above stays
// available so historical slips remain consultable on any plan.
Route::middleware('feature:payroll')->group(function () {
    Route::get('payroll/employees/create', [EmployeeController::class, 'create'])->name('payroll.employees.create');
    Route::post('payroll/employees', [EmployeeController::class, 'store'])->name('payroll.employees.store');
    Route::get('payroll/employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('payroll.employees.edit');
    Route::match(['put', 'patch'], 'payroll/employees/{employee}', [EmployeeController::class, 'update'])->name('payroll.employees.update');
    Route::delete('payroll/employees/{employee}', [EmployeeController::class, 'destroy'])->name('payroll.employees.destroy');

    Route::post('/payroll/salary-slips/generate', [SalarySlipController::class, 'generate'])->name('payroll.salarySlips.generate');
    Route::post('/payroll/salary-slips/{slip}/post', [SalarySlipController::class, 'post'])->name('payroll.salarySlips.post');
    Route::post('/payroll/salary-slips/{slip}/unpost', [SalarySlipController::class, 'unpost'])->name('payroll.salarySlips.unpost');
    Route::delete('/payroll/salary-slips/{slip}', [SalarySlipController::class, 'destroy'])->name('payroll.salarySlips.destroy');

    Route::get('/payroll/run', [PayrollRunController::class, 'index'])->name('payroll.run');
    Route::post('/payroll/run/preview', [PayrollRunController::class, 'preview'])->name('payroll.run.preview');
    Route::post('/payroll/run', [PayrollRunController::class, 'generate'])->name('payroll.run.generate');
});

<?php

use App\Domains\Payroll\Controllers\EmployeeController;
use App\Domains\Payroll\Controllers\PayrollRunController;
use App\Domains\Payroll\Controllers\SalaryCertificateController;
use App\Domains\Payroll\Controllers\SalarySlipController;
use Illuminate\Support\Facades\Route;

Route::resource('payroll/employees', EmployeeController::class)->names('payroll.employees');
Route::get('/payroll/employees/{employee}/salary-certificate/{year}', [SalaryCertificateController::class, 'download'])
    ->whereNumber('year')
    ->name('payroll.salaryCertificate.download');

Route::get('/payroll/salary-slips', [SalarySlipController::class, 'index'])->name('payroll.salarySlips.index');
Route::get('/payroll/salary-slips/{slip}', [SalarySlipController::class, 'show'])->name('payroll.salarySlips.show');
Route::post('/payroll/salary-slips/generate', [SalarySlipController::class, 'generate'])->name('payroll.salarySlips.generate');
Route::post('/payroll/salary-slips/{slip}/post', [SalarySlipController::class, 'post'])->name('payroll.salarySlips.post');
Route::post('/payroll/salary-slips/{slip}/unpost', [SalarySlipController::class, 'unpost'])->name('payroll.salarySlips.unpost');
Route::delete('/payroll/salary-slips/{slip}', [SalarySlipController::class, 'destroy'])->name('payroll.salarySlips.destroy');
Route::get('/payroll/salary-slips/{slip}/pdf', [SalarySlipController::class, 'downloadPdf'])->name('payroll.salarySlips.pdf');

Route::get('/payroll/run', [PayrollRunController::class, 'index'])->name('payroll.run');
Route::post('/payroll/run/preview', [PayrollRunController::class, 'preview'])->name('payroll.run.preview');
Route::post('/payroll/run', [PayrollRunController::class, 'generate'])->name('payroll.run.generate');

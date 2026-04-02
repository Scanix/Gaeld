<?php

use App\Domains\Reporting\Controllers\ReportController;
use App\Domains\Reporting\Controllers\VatReportController;
use Illuminate\Support\Facades\Route;

Route::get('/reports/profit-and-loss', [ReportController::class, 'profitAndLoss'])->name('reports.pnl');
Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balanceSheet');
Route::get('/reports/profit-and-loss/export/{format}', [ReportController::class, 'exportProfitAndLoss'])->name('reports.pnl.export');
Route::get('/reports/balance-sheet/export/{format}', [ReportController::class, 'exportBalanceSheet'])->name('reports.balanceSheet.export');

// VAT Report
Route::get('/reports/vat', [VatReportController::class, 'vatReport'])->name('reports.vat');
Route::get('/reports/vat/export/{format}', [VatReportController::class, 'exportVatReport'])->name('reports.vat.export');
Route::post('/reports/vat/settlement', [VatReportController::class, 'postVatSettlement'])->name('reports.vat.settlement');

// Cash Flow Report
Route::get('/reports/cash-flow', [ReportController::class, 'cashFlow'])->name('reports.cashFlow');
Route::get('/reports/cash-flow/export/{format}', [ReportController::class, 'exportCashFlow'])->name('reports.cashFlow.export');

// Aging Report
Route::get('/reports/aging', [ReportController::class, 'aging'])->name('reports.aging');
Route::get('/reports/aging/export/{format}', [ReportController::class, 'exportAging'])->name('reports.aging.export');

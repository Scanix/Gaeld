<?php

use App\Domains\Reporting\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::get('/reports/profit-and-loss', [ReportController::class, 'profitAndLoss'])->name('reports.pnl');
Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->name('reports.balanceSheet');

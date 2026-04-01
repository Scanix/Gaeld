<?php

use App\Domains\Migration\Controllers\MigrationController;
use Illuminate\Support\Facades\Route;

Route::get('/migration', [MigrationController::class, 'index'])->name('migration.index');
Route::post('/migration', [MigrationController::class, 'store'])->name('migration.store');
Route::get('/migration/{session}', [MigrationController::class, 'show'])->name('migration.show');
Route::post('/migration/{session}/upload', [MigrationController::class, 'upload'])->name('migration.upload');
Route::post('/migration/{session}/execute', [MigrationController::class, 'execute'])->name('migration.execute');
Route::post('/migration/{session}/rollback', [MigrationController::class, 'rollback'])->name('migration.rollback');
Route::delete('/migration/{session}', [MigrationController::class, 'destroy'])->name('migration.destroy');

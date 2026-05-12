<?php

use App\Domains\Expenses\Controllers\ExpenseCategoryController;
use App\Domains\Organizations\Controllers\ActivityLogController;
use App\Domains\Organizations\Controllers\InvitationController;
use App\Domains\Organizations\Controllers\MemberController;
use App\Domains\Organizations\Controllers\OrganizationController;
use App\Domains\Organizations\Controllers\OrganizationSettingsController;
use Illuminate\Support\Facades\Route;

Route::resource('organizations', OrganizationController::class)->only(['index', 'create', 'show', 'store', 'destroy']);
Route::post('/organizations/{organization}/switch', [OrganizationController::class, 'switchOrganization'])->name('organizations.switch');

// Organization settings
Route::get('/settings', [OrganizationSettingsController::class, 'show'])->name('settings');
Route::put('/settings/general', [OrganizationSettingsController::class, 'updateGeneral'])->name('settings.general');
Route::put('/settings/invoice', [OrganizationSettingsController::class, 'updateInvoice'])->name('settings.invoice');
Route::post('/settings/invoice/logo', [OrganizationSettingsController::class, 'uploadLogo'])->name('settings.logo.upload');
Route::delete('/settings/invoice/logo', [OrganizationSettingsController::class, 'deleteLogo'])->name('settings.logo.delete');
Route::get('/settings/logo', [OrganizationSettingsController::class, 'serveLogo'])->name('settings.logo');
Route::put('/settings/communications', [OrganizationSettingsController::class, 'updateCommunications'])->name('settings.communications');
Route::put('/settings/modules', [OrganizationSettingsController::class, 'updateModules'])->name('settings.modules');

// Organization data export (GDPR portability)
Route::post('/settings/export', [OrganizationSettingsController::class, 'exportData'])->middleware('throttle:3,5')->name('settings.export');
Route::get('/settings/export/download', [OrganizationSettingsController::class, 'downloadExport'])->name('settings.export.download');

// Expense categories
Route::get('/settings/expense-categories', [ExpenseCategoryController::class, 'index'])->name('settings.expense-categories.index');
Route::post('/settings/expense-categories', [ExpenseCategoryController::class, 'store'])->name('settings.expense-categories.store');
Route::delete('/settings/expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'destroy'])->name('settings.expense-categories.destroy');

// Activity log
Route::get('/settings/activity-log', [ActivityLogController::class, 'index'])->name('settings.activity-log');

// Organization members
Route::post('/organizations/{organization}/members/{user}/role', [MemberController::class, 'updateRole'])->name('organizations.members.updateRole');
Route::delete('/organizations/{organization}/members/{user}', [MemberController::class, 'remove'])->name('organizations.members.remove');
Route::post('/organizations/{organization}/leave', [MemberController::class, 'leave'])->name('organizations.leave');

// Organization invitations
Route::post('/organizations/{organization}/invitations', [InvitationController::class, 'store'])->middleware('throttle:10,60')->name('organizations.invitations.store');
Route::delete('/organizations/{organization}/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('organizations.invitations.destroy');
Route::post('/organizations/{organization}/invitations/{invitation}/resend', [InvitationController::class, 'resend'])->name('organizations.invitations.resend');

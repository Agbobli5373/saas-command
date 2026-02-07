<?php

use App\Http\Controllers\Settings\BillingController;
use App\Http\Controllers\Settings\NotificationController;
use App\Http\Controllers\Settings\OperationsController;
use App\Http\Controllers\Settings\PasswordController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\TwoFactorAuthenticationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/password', [PasswordController::class, 'edit'])->name('user-password.edit');

    Route::put('settings/password', [PasswordController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', function () {
        return Inertia::render('settings/appearance');
    })->name('appearance.edit');

    Route::get('settings/two-factor', [TwoFactorAuthenticationController::class, 'show'])
        ->name('two-factor.show');

    Route::get('settings/billing', [BillingController::class, 'edit'])
        ->name('billing.edit');

    Route::post('settings/billing/checkout', [BillingController::class, 'checkout'])
        ->name('billing.checkout');

    Route::post('settings/billing/portal', [BillingController::class, 'portal'])
        ->name('billing.portal');

    Route::post('settings/billing/swap', [BillingController::class, 'swap'])
        ->name('billing.swap');

    Route::post('settings/billing/cancel', [BillingController::class, 'cancel'])
        ->name('billing.cancel');

    Route::post('settings/billing/resume', [BillingController::class, 'resume'])
        ->name('billing.resume');

    Route::get('settings/notifications', [NotificationController::class, 'index'])
        ->name('notifications.index');

    Route::post('settings/notifications/read-all', [NotificationController::class, 'readAll'])
        ->name('notifications.read-all');

    Route::post('settings/notifications/{notification}/read', [NotificationController::class, 'read'])
        ->name('notifications.read');

    Route::get('settings/operations', [OperationsController::class, 'show'])
        ->name('operations.show');
});

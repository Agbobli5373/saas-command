<?php

use App\Http\Controllers\Billing\StripeWebhookController;
use App\Http\Controllers\Workspace\CurrentWorkspaceController;
use App\Http\Controllers\Workspace\WorkspaceController;
use App\Http\Controllers\Workspace\WorkspaceInvitationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::post('stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])->name('cashier.webhook');

Route::get('/', function () {
    return Inertia::render('welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::put('workspaces/current', [CurrentWorkspaceController::class, 'update'])
        ->name('workspaces.current.update');

    Route::post('workspaces/invitations', [WorkspaceInvitationController::class, 'store'])
        ->name('workspaces.invitations.store');

    Route::get('workspaces/invitations/{token}/accept', [WorkspaceInvitationController::class, 'accept'])
        ->name('workspaces.invitations.accept');
});

Route::get('workspace', [WorkspaceController::class, 'show'])
    ->middleware(['auth', 'verified', 'subscribed'])
    ->name('workspace');

require __DIR__.'/settings.php';

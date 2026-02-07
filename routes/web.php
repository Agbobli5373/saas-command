<?php

use App\Http\Controllers\Billing\StripeWebhookController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\Workspace\CurrentWorkspaceController;
use App\Http\Controllers\Workspace\WorkspaceController;
use App\Http\Controllers\Workspace\WorkspaceInvitationController;
use App\Http\Controllers\Workspace\WorkspaceWebhookEndpointController;
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
})->middleware(['auth', 'verified', 'onboarded'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('onboarding', [OnboardingController::class, 'show'])
        ->name('onboarding.show');

    Route::post('onboarding', [OnboardingController::class, 'store'])
        ->name('onboarding.store');
});

Route::middleware(['auth'])->group(function () {
    Route::put('workspaces/current', [CurrentWorkspaceController::class, 'update'])
        ->name('workspaces.current.update');

    Route::post('workspaces/invitations', [WorkspaceInvitationController::class, 'store'])
        ->name('workspaces.invitations.store');

    Route::post('workspaces/webhooks', [WorkspaceWebhookEndpointController::class, 'store'])
        ->name('workspaces.webhooks.store');

    Route::delete('workspaces/webhooks/{endpoint}', [WorkspaceWebhookEndpointController::class, 'destroy'])
        ->name('workspaces.webhooks.destroy');

    Route::get('workspaces/invitations/{token}/accept', [WorkspaceInvitationController::class, 'accept'])
        ->name('workspaces.invitations.accept');

    Route::patch('workspaces/members/{member}', [WorkspaceController::class, 'updateMemberRole'])
        ->name('workspaces.members.update');

    Route::delete('workspaces/members/{member}', [WorkspaceController::class, 'destroyMember'])
        ->name('workspaces.members.destroy');

    Route::post('workspaces/ownership/transfer', [WorkspaceController::class, 'transferOwnership'])
        ->name('workspaces.ownership.transfer');
});

Route::get('workspace', [WorkspaceController::class, 'show'])
    ->middleware(['auth', 'verified', 'onboarded'])
    ->name('workspace');

require __DIR__.'/settings.php';

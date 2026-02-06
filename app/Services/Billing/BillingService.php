<?php

namespace App\Services\Billing;

use App\Models\User;

interface BillingService
{
    public function checkout(User $user, string $priceId, string $successUrl, string $cancelUrl): string;

    public function billingPortal(User $user, string $returnUrl): string;

    public function swap(User $user, string $priceId): void;

    public function cancel(User $user): void;

    public function resume(User $user): void;
}

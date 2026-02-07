<?php

namespace App\Services\Billing;

use App\Models\User;

interface BillingService
{
    public function checkout(User $user, string $priceId, string $successUrl, string $cancelUrl): string;

    public function billingPortal(User $user, string $returnUrl): string;

    /**
     * @return array<int, array{
     *     id: string,
     *     number: string|null,
     *     status: string,
     *     total: string,
     *     amountPaid: string,
     *     date: string,
     *     currency: string,
     *     hostedInvoiceUrl: string|null,
     *     invoicePdfUrl: string|null
     * }>
     */
    public function invoices(User $user, int $limit = 10): array;

    public function swap(User $user, string $priceId): void;

    public function cancel(User $user): void;

    public function resume(User $user): void;
}

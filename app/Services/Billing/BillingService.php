<?php

namespace App\Services\Billing;

use App\Models\Workspace;

interface BillingService
{
    public function checkout(Workspace $workspace, string $priceId, string $successUrl, string $cancelUrl): string;

    public function billingPortal(Workspace $workspace, string $returnUrl): string;

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
    public function invoices(Workspace $workspace, int $limit = 10): array;

    public function swap(Workspace $workspace, string $priceId): void;

    public function cancel(Workspace $workspace): void;

    public function resume(Workspace $workspace): void;
}

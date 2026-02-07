import { Form, Head } from '@inertiajs/react';
import { AlertTriangle, Check, CircleAlert, CreditCard, Receipt } from 'lucide-react';
import { useMemo, useState } from 'react';
import BillingController from '@/actions/App/Http/Controllers/Settings/BillingController';
import Heading from '@/components/heading';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit } from '@/routes/billing';
import type { BreadcrumbItem } from '@/types';

type BillingPlan = {
    key: string;
    priceId: string;
    title: string;
    priceLabel: string;
    intervalLabel: string;
    description: string;
    features: string[];
    highlighted: boolean;
};

type BillingInvoice = {
    id: string;
    number: string | null;
    status: string;
    total: string;
    amountPaid: string;
    date: string;
    currency: string;
    hostedInvoiceUrl: string | null;
    invoicePdfUrl: string | null;
};

type BillingWebhookOutcome = {
    status: 'warning';
    message: string;
    occurredAt: string;
};

type BillingProps = {
    status?: string;
    plans: BillingPlan[];
    stripeConfigWarnings: string[];
    webhookOutcome: BillingWebhookOutcome | null;
    currentPriceId?: string | null;
    isSubscribed: boolean;
    onGracePeriod: boolean;
    endsAt?: string | null;
    seatCount: number;
    billedSeatCount: number;
    invoices: BillingInvoice[];
};

type InvoiceFilter = 'all' | 'paid' | 'open' | 'failed';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Billing settings',
        href: edit().url,
    },
];

const invoiceFilterLabels: Record<InvoiceFilter, string> = {
    all: 'All',
    paid: 'Paid',
    open: 'Open',
    failed: 'Failed',
};

const failedStatuses = new Set(['uncollectible', 'void']);
const openStatuses = new Set(['open', 'draft']);

export default function Billing({
    status,
    plans,
    stripeConfigWarnings,
    webhookOutcome,
    currentPriceId,
    isSubscribed,
    onGracePeriod,
    endsAt,
    seatCount,
    billedSeatCount,
    invoices,
}: BillingProps) {
    const [invoiceFilter, setInvoiceFilter] = useState<InvoiceFilter>('all');
    const defaultPlan = plans[0]?.key ?? null;

    const filteredInvoices = useMemo(() => {
        if (invoiceFilter === 'all') {
            return invoices;
        }

        if (invoiceFilter === 'paid') {
            return invoices.filter((invoice) => invoice.status === 'paid');
        }

        if (invoiceFilter === 'open') {
            return invoices.filter((invoice) => openStatuses.has(invoice.status));
        }

        return invoices.filter((invoice) => failedStatuses.has(invoice.status));
    }, [invoiceFilter, invoices]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Billing settings" />

            <h1 className="sr-only">Billing Settings</h1>

            <SettingsLayout>
                <div className="space-y-8">
                    <Heading
                        variant="small"
                        title="Billing & Subscription"
                        description="Control plans, renewals, and Stripe customer billing from one place"
                    />

                    {status ? (
                        <Alert>
                            <CircleAlert className="h-4 w-4" />
                            <AlertTitle>Billing update</AlertTitle>
                            <AlertDescription>{status}</AlertDescription>
                        </Alert>
                    ) : null}

                    {webhookOutcome ? (
                        <Alert variant="destructive">
                            <AlertTriangle className="h-4 w-4" />
                            <AlertTitle>Payment attention required</AlertTitle>
                            <AlertDescription>
                                {webhookOutcome.message} Last update on{' '}
                                {new Date(webhookOutcome.occurredAt).toLocaleString()}.
                                <div className="mt-3">
                                    <Form {...BillingController.portal.form()}>
                                        {({ processing }) => (
                                            <Button size="sm" disabled={processing || !isSubscribed}>
                                                Update payment method
                                            </Button>
                                        )}
                                    </Form>
                                </div>
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    {stripeConfigWarnings.map((warning) => (
                        <Alert key={warning} variant="destructive">
                            <AlertTriangle className="h-4 w-4" />
                            <AlertTitle>Stripe configuration warning</AlertTitle>
                            <AlertDescription>{warning}</AlertDescription>
                        </Alert>
                    ))}

                    <Card className="border-border/70 bg-gradient-to-br from-background via-background to-muted/30">
                        <CardHeader className="gap-5">
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <CardTitle className="text-xl">Current subscription</CardTitle>
                                    <CardDescription className="mt-1">
                                        {isSubscribed
                                            ? 'Your workspace has an active Stripe subscription.'
                                            : 'No active subscription on this account yet.'}
                                    </CardDescription>
                                </div>

                                <Badge variant={isSubscribed ? 'default' : 'outline'}>
                                    {isSubscribed ? 'Active' : 'Inactive'}
                                </Badge>
                            </div>

                            <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                {onGracePeriod ? (
                                    <Badge variant="secondary">Grace period</Badge>
                                ) : null}
                                <Badge variant="outline">{seatCount} active seats</Badge>
                                <Badge variant="outline">{billedSeatCount} billed seats</Badge>
                                {endsAt ? (
                                    <span>
                                        Renews until {new Date(endsAt).toLocaleDateString()}
                                    </span>
                                ) : null}
                            </div>
                        </CardHeader>

                        <CardFooter className="flex flex-wrap gap-3">
                            <Form {...BillingController.checkout.form()}>
                                {({ processing }) => (
                                    <>
                                        <input type="hidden" name="plan" value={defaultPlan ?? ''} />
                                        <Button disabled={processing || isSubscribed || defaultPlan === null}>
                                            Start subscription
                                        </Button>
                                    </>
                                )}
                            </Form>

                            <Form {...BillingController.portal.form()}>
                                {({ processing }) => (
                                    <Button variant="outline" disabled={processing || !isSubscribed}>
                                        <CreditCard className="h-4 w-4" />
                                        Open customer portal
                                    </Button>
                                )}
                            </Form>

                            <Form {...BillingController.cancel.form()}>
                                {({ processing }) => (
                                    <Button variant="destructive" disabled={processing || !isSubscribed || onGracePeriod}>
                                        Cancel subscription
                                    </Button>
                                )}
                            </Form>

                            <Form {...BillingController.resume.form()}>
                                {({ processing }) => (
                                    <Button variant="secondary" disabled={processing || !onGracePeriod}>
                                        Resume subscription
                                    </Button>
                                )}
                            </Form>
                        </CardFooter>
                    </Card>

                    <Card>
                        <CardHeader className="gap-4 md:flex-row md:items-start md:justify-between">
                            <div>
                                <CardTitle>Invoice history</CardTitle>
                                <CardDescription>
                                    Recent billing invoices from your Stripe customer account.
                                </CardDescription>
                            </div>

                            <div className="flex flex-wrap gap-2">
                                {(Object.keys(invoiceFilterLabels) as InvoiceFilter[]).map((filter) => (
                                    <Button
                                        key={filter}
                                        type="button"
                                        size="sm"
                                        variant={invoiceFilter === filter ? 'default' : 'outline'}
                                        onClick={() => setInvoiceFilter(filter)}
                                    >
                                        {invoiceFilterLabels[filter]}
                                    </Button>
                                ))}
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {filteredInvoices.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No invoices found for the selected filter.
                                </p>
                            ) : (
                                filteredInvoices.map((invoice) => (
                                    <div
                                        key={invoice.id}
                                        className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border/70 px-4 py-3"
                                    >
                                        <div className="space-y-1">
                                            <p className="text-sm font-medium">
                                                {invoice.number ?? invoice.id}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {new Date(invoice.date).toLocaleDateString()} . {invoice.currency}
                                            </p>
                                        </div>

                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge variant={invoice.status === 'paid' ? 'default' : 'outline'}>
                                                {invoice.status}
                                            </Badge>
                                            <span className="text-sm font-medium">{invoice.total}</span>
                                            {invoice.hostedInvoiceUrl ? (
                                                <a
                                                    href={invoice.hostedInvoiceUrl}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-sm text-primary underline underline-offset-4"
                                                >
                                                    View
                                                </a>
                                            ) : null}
                                            {invoice.invoicePdfUrl ? (
                                                <a
                                                    href={invoice.invoicePdfUrl}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex items-center gap-1 text-sm text-primary underline underline-offset-4"
                                                >
                                                    <Receipt className="h-3.5 w-3.5" />
                                                    PDF
                                                </a>
                                            ) : null}
                                        </div>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    {plans.length === 0 ? (
                        <Alert>
                            <CircleAlert className="h-4 w-4" />
                            <AlertTitle>No plans configured</AlertTitle>
                            <AlertDescription>
                                Add `STRIPE_PRICE_STARTER_MONTHLY` and `STRIPE_PRICE_STARTER_YEARLY` to your `.env`,
                                then run `php artisan config:clear`.
                            </AlertDescription>
                        </Alert>
                    ) : (
                        <div className="grid gap-4 md:grid-cols-2">
                            {plans.map((plan) => {
                                const isCurrent = currentPriceId === plan.priceId;

                                return (
                                    <Card
                                        key={plan.key}
                                        className={plan.highlighted ? 'border-primary/40 shadow-sm' : ''}
                                    >
                                        <CardHeader className="space-y-3">
                                            <div className="flex items-center justify-between gap-2">
                                                <CardTitle>{plan.title}</CardTitle>
                                                {isCurrent ? (
                                                    <Badge>Current</Badge>
                                                ) : plan.highlighted ? (
                                                    <Badge variant="secondary">Popular</Badge>
                                                ) : null}
                                            </div>

                                            <CardDescription>{plan.description}</CardDescription>

                                            <p className="text-2xl font-semibold tracking-tight">
                                                {plan.priceLabel}
                                                <span className="ml-1 text-sm font-normal text-muted-foreground">
                                                    {plan.intervalLabel}
                                                </span>
                                            </p>
                                        </CardHeader>

                                        <CardContent>
                                            <ul className="space-y-2 text-sm text-muted-foreground">
                                                {plan.features.map((feature) => (
                                                    <li key={feature} className="flex items-start gap-2">
                                                        <Check className="mt-0.5 h-4 w-4 text-primary" />
                                                        <span>{feature}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        </CardContent>

                                        <CardFooter>
                                            {!isSubscribed ? (
                                                <Form {...BillingController.checkout.form()} className="w-full">
                                                    {({ processing }) => (
                                                        <>
                                                            <input type="hidden" name="plan" value={plan.key} />
                                                            <Button className="w-full" disabled={processing || isCurrent}>
                                                                Start with {plan.title}
                                                            </Button>
                                                        </>
                                                    )}
                                                </Form>
                                            ) : (
                                                <Form {...BillingController.swap.form()} className="w-full">
                                                    {({ processing }) => (
                                                        <>
                                                            <input type="hidden" name="plan" value={plan.key} />
                                                            <Button
                                                                variant="outline"
                                                                className="w-full"
                                                                disabled={processing || isCurrent}
                                                            >
                                                                Switch to {plan.title}
                                                            </Button>
                                                        </>
                                                    )}
                                                </Form>
                                            )}
                                        </CardFooter>
                                    </Card>
                                );
                            })}
                        </div>
                    )}
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

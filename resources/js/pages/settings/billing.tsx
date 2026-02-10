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
import { useI18n } from '@/hooks/use-i18n';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit } from '@/routes/billing';
import type { BreadcrumbItem } from '@/types';

type BillingPlan = {
    key: string;
    billingMode: 'free' | 'stripe';
    priceId: string | null;
    title: string;
    priceLabel: string;
    intervalLabel: string;
    description: string;
    features: string[];
    featureFlags: string[];
    limits: Record<string, number | null>;
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

type BillingAuditActor = {
    id: number;
    name: string;
    email: string;
};

type BillingAuditEvent = {
    id: number;
    eventType: string;
    source: string;
    severity: string;
    title: string;
    description: string | null;
    context: Record<string, unknown>;
    occurredAt: string | null;
    actor: BillingAuditActor | null;
};

type BillingUsagePeriod = {
    start: string;
    end: string;
    label: string;
};

type BillingUsageMetric = {
    key: string;
    title: string;
    description: string | null;
    quota: number | null;
    used: number;
    remaining: number | null;
    percentage: number | null;
    isUnlimited: boolean;
    isExceeded: boolean;
};

type BillingProps = {
    status?: string;
    plans: BillingPlan[];
    stripeConfigWarnings: string[];
    webhookOutcome: BillingWebhookOutcome | null;
    currentPriceId?: string | null;
    currentPlanKey?: string | null;
    currentPlanTitle?: string | null;
    currentPlanBillingMode?: 'free' | 'stripe' | null;
    isSubscribed: boolean;
    onGracePeriod: boolean;
    endsAt?: string | null;
    seatCount: number;
    seatLimit: number | null;
    remainingSeatCapacity: number | null;
    billedSeatCount: number;
    usagePeriod: BillingUsagePeriod;
    usageMetrics: BillingUsageMetric[];
    invoices: BillingInvoice[];
    auditTimeline: BillingAuditEvent[];
};

type InvoiceFilter = 'all' | 'paid' | 'open' | 'failed';
type AuditSeverity = 'info' | 'warning' | 'error';

const failedStatuses = new Set(['uncollectible', 'void']);
const openStatuses = new Set(['open', 'draft']);

export default function Billing({
    status,
    plans,
    stripeConfigWarnings,
    webhookOutcome,
    currentPriceId,
    currentPlanKey,
    currentPlanTitle,
    currentPlanBillingMode,
    isSubscribed,
    onGracePeriod,
    endsAt,
    seatCount,
    seatLimit,
    remainingSeatCapacity,
    billedSeatCount,
    usagePeriod,
    usageMetrics,
    invoices,
    auditTimeline,
}: BillingProps) {
    const { t, formatDate, formatDateTime } = useI18n();
    const [invoiceFilter, setInvoiceFilter] = useState<InvoiceFilter>('all');

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('Billing settings'),
            href: edit().url,
        },
    ];

    const invoiceFilterLabels: Record<InvoiceFilter, string> = {
        all: t('All'),
        paid: t('Paid'),
        open: t('Open'),
        failed: t('Failed'),
    };
    const invoiceStatusLabels: Record<string, string> = {
        paid: t('paid'),
        open: t('open'),
        draft: t('draft'),
        uncollectible: t('uncollectible'),
        void: t('void'),
    };
    const severityLabels: Record<AuditSeverity, string> = {
        info: t('info'),
        warning: t('warning'),
        error: t('error'),
    };

    const paidPlans = useMemo(
        () => plans.filter((plan) => plan.billingMode === 'stripe' && plan.priceId !== null),
        [plans],
    );

    const defaultPaidPlan = paidPlans[0]?.key ?? null;

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
            <Head title={t('Billing settings')} />

            <h1 className="sr-only">{t('Billing Settings')}</h1>

            <SettingsLayout>
                <div className="space-y-8">
                    <Heading
                        variant="small"
                        title={t('Billing & Subscription')}
                        description={t('Control plans, renewals, and Stripe customer billing from one place')}
                    />

                    {status ? (
                        <Alert>
                            <CircleAlert className="h-4 w-4" />
                            <AlertTitle>{t('Billing update')}</AlertTitle>
                            <AlertDescription>{status}</AlertDescription>
                        </Alert>
                    ) : null}

                    {webhookOutcome ? (
                        <Alert variant="destructive">
                            <AlertTriangle className="h-4 w-4" />
                            <AlertTitle>{t('Payment attention required')}</AlertTitle>
                            <AlertDescription>
                                {webhookOutcome.message} {t('Last update on')} {formatDateTime(webhookOutcome.occurredAt)}.
                                <div className="mt-3">
                                    <Form {...BillingController.portal.form()}>
                                        {({ processing }) => (
                                            <Button size="sm" disabled={processing || !isSubscribed}>
                                                {t('Update payment method')}
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
                            <AlertTitle>{t('Stripe configuration warning')}</AlertTitle>
                            <AlertDescription>{warning}</AlertDescription>
                        </Alert>
                    ))}

                    <Card className="border-border/70 bg-gradient-to-br from-background via-background to-muted/30">
                        <CardHeader className="gap-5">
                            <div className="flex items-center justify-between gap-4">
                                <div>
                                    <CardTitle className="text-xl">{t('Current subscription')}</CardTitle>
                                    <CardDescription className="mt-1">
                                        {isSubscribed
                                            ? t('Your workspace has an active Stripe subscription.')
                                            : currentPlanBillingMode === 'free'
                                              ? t('Your workspace is on the free tier with no Stripe billing required.')
                                              : t('No active subscription on this account yet.')}
                                    </CardDescription>
                                </div>

                                <Badge variant={isSubscribed ? 'default' : 'outline'}>
                                    {isSubscribed ? t('Active') : t('Inactive')}
                                </Badge>
                            </div>

                            <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                                {currentPlanTitle ? <Badge variant="secondary">{t('Plan: :plan', { plan: currentPlanTitle })}</Badge> : null}
                                {onGracePeriod ? <Badge variant="secondary">{t('Grace period')}</Badge> : null}
                                <Badge variant="outline">{t(':count active seats', { count: seatCount })}</Badge>
                                <Badge variant="outline">{t(':count billed seats', { count: billedSeatCount })}</Badge>
                                {seatLimit !== null ? (
                                    <Badge variant="outline">
                                        {t('Seat capacity :used/:limit', {
                                            used: seatCount,
                                            limit: seatLimit,
                                        })}
                                    </Badge>
                                ) : null}
                                {remainingSeatCapacity !== null ? (
                                    <span>{t(':count seats available', { count: remainingSeatCapacity })}</span>
                                ) : null}
                                {endsAt ? (
                                    <span>
                                        {t('Renews until :date', { date: formatDate(endsAt) })}
                                    </span>
                                ) : null}
                            </div>
                        </CardHeader>

                        <CardFooter className="flex flex-wrap gap-3">
                            {defaultPaidPlan ? (
                                <Form {...BillingController.checkout.form()}>
                                    {({ processing }) => (
                                        <>
                                            <input type="hidden" name="plan" value={defaultPaidPlan} />
                                            <Button disabled={processing || isSubscribed}>
                                                {t('Start subscription')}
                                            </Button>
                                        </>
                                    )}
                                </Form>
                            ) : null}

                            <Form {...BillingController.portal.form()}>
                                {({ processing }) => (
                                    <Button variant="outline" disabled={processing || !isSubscribed}>
                                        <CreditCard className="h-4 w-4" />
                                        {t('Open customer portal')}
                                    </Button>
                                )}
                            </Form>

                            <Form {...BillingController.cancel.form()}>
                                {({ processing }) => (
                                    <Button variant="destructive" disabled={processing || !isSubscribed || onGracePeriod}>
                                        {t('Cancel subscription')}
                                    </Button>
                                )}
                            </Form>

                            <Form {...BillingController.resume.form()}>
                                {({ processing }) => (
                                    <Button variant="secondary" disabled={processing || !onGracePeriod}>
                                        {t('Resume subscription')}
                                    </Button>
                                )}
                            </Form>
                        </CardFooter>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Usage this period')}</CardTitle>
                            <CardDescription>
                                {t('Metered usage for :period.', { period: usagePeriod.label })}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {usageMetrics.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('No usage metrics configured for this workspace.')}
                                </p>
                            ) : (
                                usageMetrics.map((metric) => (
                                    <div
                                        key={metric.key}
                                        className="space-y-2 rounded-lg border border-border/70 px-4 py-3"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="text-sm font-medium">{metric.title}</p>
                                            <Badge
                                                variant={metric.isExceeded ? 'destructive' : 'outline'}
                                            >
                                                {metric.isUnlimited
                                                    ? t(':count used', { count: metric.used })
                                                    : t(':used/:quota', {
                                                        used: metric.used,
                                                        quota: metric.quota ?? 0,
                                                    })}
                                            </Badge>
                                        </div>
                                        {metric.description ? (
                                            <p className="text-xs text-muted-foreground">
                                                {metric.description}
                                            </p>
                                        ) : null}
                                        {!metric.isUnlimited && metric.percentage !== null ? (
                                            <div className="space-y-1">
                                                <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
                                                    <div
                                                        className={`h-full rounded-full transition-all ${
                                                            metric.isExceeded
                                                                ? 'bg-destructive'
                                                                : 'bg-primary'
                                                        }`}
                                                        style={{ width: `${metric.percentage}%` }}
                                                    />
                                                </div>
                                                <p className="text-xs text-muted-foreground">
                                                    {t(':count remaining', {
                                                        count: metric.remaining ?? 0,
                                                    })}
                                                </p>
                                            </div>
                                        ) : (
                                            <p className="text-xs text-muted-foreground">
                                                {t('Unlimited usage on the current plan.')}
                                            </p>
                                        )}
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{t('Billing audit timeline')}</CardTitle>
                            <CardDescription>
                                {t('Recent billing actions and Stripe events for this workspace.')}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {auditTimeline.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('No billing audit events yet.')}
                                </p>
                            ) : (
                                auditTimeline.map((event) => (
                                    <div
                                        key={event.id}
                                        className="space-y-2 rounded-lg border border-border/70 px-4 py-3"
                                    >
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p className="text-sm font-medium">{event.title}</p>
                                            <div className="flex items-center gap-2">
                                                <Badge variant="outline">{t(event.source)}</Badge>
                                                <Badge
                                                    variant={event.severity === 'error' ? 'destructive' : 'secondary'}
                                                >
                                                    {severityLabels[event.severity as AuditSeverity] ?? event.severity}
                                                </Badge>
                                            </div>
                                        </div>
                                        {event.description ? (
                                            <p className="text-sm text-muted-foreground">
                                                {event.description}
                                            </p>
                                        ) : null}
                                        <p className="text-xs text-muted-foreground">
                                            {event.occurredAt
                                                ? formatDateTime(event.occurredAt)
                                                : t('Unknown time')}
                                            {event.actor ? ` . ${event.actor.name}` : ''}
                                        </p>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="gap-4 md:flex-row md:items-start md:justify-between">
                            <div>
                                <CardTitle>{t('Invoice history')}</CardTitle>
                                <CardDescription>
                                    {t('Recent billing invoices from your Stripe customer account.')}
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
                                    {t('No invoices found for the selected filter.')}
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
                                                {formatDate(invoice.date)} . {invoice.currency}
                                            </p>
                                        </div>

                                        <div className="flex flex-wrap items-center gap-2">
                                            <Badge variant={invoice.status === 'paid' ? 'default' : 'outline'}>
                                                {invoiceStatusLabels[invoice.status] ?? invoice.status}
                                            </Badge>
                                            <span className="text-sm font-medium">{invoice.total}</span>
                                            {invoice.hostedInvoiceUrl ? (
                                                <a
                                                    href={invoice.hostedInvoiceUrl}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-sm text-primary underline underline-offset-4"
                                                >
                                                    {t('View')}
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
                                                    {t('PDF')}
                                                </a>
                                            ) : null}
                                        </div>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    {paidPlans.length === 0 ? (
                        <Alert>
                            <CircleAlert className="h-4 w-4" />
                            <AlertTitle>{t('No paid plans configured')}</AlertTitle>
                            <AlertDescription>
                                {t('Add `STRIPE_PRICE_STARTER_MONTHLY` and `STRIPE_PRICE_STARTER_YEARLY` to your `.env`, then run `php artisan config:clear`.')}
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    <div className="grid gap-4 md:grid-cols-2">
                        {plans.map((plan) => {
                            const isCurrentPlan =
                                currentPlanKey === plan.key ||
                                (plan.priceId !== null && currentPriceId === plan.priceId);

                            return (
                                <Card
                                    key={plan.key}
                                    className={plan.highlighted ? 'border-primary/40 shadow-sm' : ''}
                                >
                                    <CardHeader className="space-y-3">
                                        <div className="flex items-center justify-between gap-2">
                                            <CardTitle>{plan.title}</CardTitle>
                                            {isCurrentPlan ? (
                                                <Badge>{t('Current')}</Badge>
                                            ) : plan.highlighted ? (
                                                <Badge variant="secondary">{t('Popular')}</Badge>
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
                                        {plan.billingMode === 'free' ? (
                                            <Button className="w-full" variant="outline" disabled>
                                                {isCurrentPlan ? t('Current plan') : t('Available without checkout')}
                                            </Button>
                                        ) : !isSubscribed ? (
                                            <Form {...BillingController.checkout.form()} className="w-full">
                                                {({ processing }) => (
                                                    <>
                                                        <input type="hidden" name="plan" value={plan.key} />
                                                        <Button className="w-full" disabled={processing || isCurrentPlan}>
                                                            {t('Start with :plan', { plan: plan.title })}
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
                                                            disabled={processing || isCurrentPlan}
                                                        >
                                                            {t('Switch to :plan', { plan: plan.title })}
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
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

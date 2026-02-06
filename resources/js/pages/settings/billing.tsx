import { Form, Head } from '@inertiajs/react';
import { Check, CircleAlert, CreditCard } from 'lucide-react';
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

type BillingProps = {
    status?: string;
    plans: Record<string, string>;
    currentPriceId?: string | null;
    isSubscribed: boolean;
    onGracePeriod: boolean;
    endsAt?: string | null;
};

type PlanMeta = {
    title: string;
    priceLabel: string;
    intervalLabel: string;
    description: string;
    features: string[];
    highlighted?: boolean;
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Billing settings',
        href: edit().url,
    },
];

const planMeta: Record<string, PlanMeta> = {
    starter_monthly: {
        title: 'Starter Monthly',
        priceLabel: '$29',
        intervalLabel: '/month',
        description: 'Best for early-stage SaaS projects that need fast iteration.',
        features: [
            'Unlimited authenticated users',
            'Stripe subscription billing',
            'Core analytics and event tracking',
        ],
    },
    starter_yearly: {
        title: 'Starter Yearly',
        priceLabel: '$290',
        intervalLabel: '/year',
        description: 'Lower annual cost with everything in monthly included.',
        features: [
            'Everything in Starter Monthly',
            'Annual savings over monthly billing',
            'Priority email support',
        ],
        highlighted: true,
    },
};

export default function Billing({
    status,
    plans,
    currentPriceId,
    isSubscribed,
    onGracePeriod,
    endsAt,
}: BillingProps) {
    const availablePlans = Object.entries(plans);
    const defaultPlan = availablePlans[0]?.[0] ?? null;

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

                    {availablePlans.length === 0 ? (
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
                            {availablePlans.map(([planKey, priceId]) => {
                                const metadata = planMeta[planKey];
                                const isCurrent = currentPriceId === priceId;

                                return (
                                    <Card
                                        key={planKey}
                                        className={metadata?.highlighted ? 'border-primary/40 shadow-sm' : ''}
                                    >
                                        <CardHeader className="space-y-3">
                                            <div className="flex items-center justify-between gap-2">
                                                <CardTitle>{metadata?.title ?? planKey}</CardTitle>
                                                {isCurrent ? (
                                                    <Badge>Current</Badge>
                                                ) : metadata?.highlighted ? (
                                                    <Badge variant="secondary">Popular</Badge>
                                                ) : null}
                                            </div>

                                            <CardDescription>
                                                {metadata?.description ?? 'Subscription plan'}
                                            </CardDescription>

                                            <p className="text-2xl font-semibold tracking-tight">
                                                {metadata?.priceLabel ?? 'Configured in Stripe'}
                                                <span className="ml-1 text-sm font-normal text-muted-foreground">
                                                    {metadata?.intervalLabel ?? ''}
                                                </span>
                                            </p>
                                        </CardHeader>

                                        <CardContent>
                                            <ul className="space-y-2 text-sm text-muted-foreground">
                                                {(metadata?.features ?? []).map((feature) => (
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
                                                            <input type="hidden" name="plan" value={planKey} />
                                                            <Button className="w-full" disabled={processing || isCurrent}>
                                                                Start with {metadata?.title ?? 'this plan'}
                                                            </Button>
                                                        </>
                                                    )}
                                                </Form>
                                            ) : (
                                                <Form {...BillingController.swap.form()} className="w-full">
                                                    {({ processing }) => (
                                                        <>
                                                            <input type="hidden" name="plan" value={planKey} />
                                                            <Button
                                                                variant="outline"
                                                                className="w-full"
                                                                disabled={processing || isCurrent}
                                                            >
                                                                Switch to {metadata?.title ?? 'this plan'}
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

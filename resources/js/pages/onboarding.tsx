import { Form, Head, Link } from '@inertiajs/react';
import { CircleAlert, Sparkles } from 'lucide-react';
import { useMemo, useState } from 'react';
import OnboardingController from '@/actions/App/Http/Controllers/OnboardingController';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { logout } from '@/routes';

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

type OnboardingProps = {
    status?: string;
    workspaceName: string;
    plans: BillingPlan[];
    stripeConfigWarnings: string[];
};

export default function Onboarding({
    status,
    workspaceName,
    plans,
    stripeConfigWarnings,
}: OnboardingProps) {
    const defaultPlan = useMemo(
        () => plans.find((plan) => plan.highlighted)?.key ?? plans[0]?.key ?? '',
        [plans],
    );

    const [selectedPlan, setSelectedPlan] = useState<string>(defaultPlan);

    return (
        <div className="relative min-h-screen bg-gradient-to-b from-background via-muted/30 to-background px-4 py-10">
            <Head title="Onboarding" />

            <div className="mx-auto max-w-4xl space-y-6">
                <div className="flex items-start justify-between gap-3">
                    <div>
                        <Badge variant="secondary" className="mb-3">
                            Step 1 of 1
                        </Badge>
                        <h1 className="text-3xl font-semibold tracking-tight">
                            Set up your workspace
                        </h1>
                        <p className="mt-2 text-muted-foreground">
                            Name your workspace and choose a starting plan to
                            launch your SaaS account.
                        </p>
                    </div>
                    <Link href={logout()} method="post" as="button" className="text-sm text-muted-foreground underline-offset-4 hover:underline">
                        Log out
                    </Link>
                </div>

                {status ? (
                    <Alert>
                        <CircleAlert className="h-4 w-4" />
                        <AlertTitle>Onboarding update</AlertTitle>
                        <AlertDescription>{status}</AlertDescription>
                    </Alert>
                ) : null}

                {stripeConfigWarnings.map((warning) => (
                    <Alert key={warning} variant="destructive">
                        <CircleAlert className="h-4 w-4" />
                        <AlertTitle>Stripe configuration warning</AlertTitle>
                        <AlertDescription>{warning}</AlertDescription>
                    </Alert>
                ))}

                <Form {...OnboardingController.store.form()}>
                    {({ processing, errors }) => (
                        <Card className="border-border/70 bg-card/90 backdrop-blur">
                            <CardHeader>
                                <CardTitle>Workspace setup</CardTitle>
                                <CardDescription>
                                    Choose a workspace identity and billing plan.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="grid gap-2">
                                    <Label htmlFor="workspace_name">
                                        Workspace name
                                    </Label>
                                    <Input
                                        id="workspace_name"
                                        name="workspace_name"
                                        defaultValue={workspaceName}
                                        autoFocus
                                    />
                                    <InputError message={errors.workspace_name} />
                                </div>

                                <input
                                    type="hidden"
                                    name="plan"
                                    value={selectedPlan}
                                />
                                <InputError message={errors.plan} />

                                <div className="grid gap-3 md:grid-cols-2">
                                    {plans.map((plan) => (
                                        <button
                                            key={plan.key}
                                            type="button"
                                            onClick={() =>
                                                setSelectedPlan(plan.key)
                                            }
                                            className={`rounded-xl border p-4 text-left transition ${selectedPlan === plan.key ? 'border-primary ring-primary/20 ring-4' : 'border-border/70 hover:border-border'}`}
                                        >
                                            <div className="mb-2 flex items-start justify-between">
                                                <p className="font-semibold">
                                                    {plan.title}
                                                </p>
                                                {plan.highlighted ? (
                                                    <Badge variant="default">
                                                        <Sparkles className="size-3" />
                                                        Popular
                                                    </Badge>
                                                ) : null}
                                            </div>
                                            <p className="text-xl font-semibold">
                                                {plan.priceLabel}
                                                <span className="ml-1 text-sm font-normal text-muted-foreground">
                                                    {plan.intervalLabel}
                                                </span>
                                            </p>
                                            <p className="mt-2 text-sm text-muted-foreground">
                                                {plan.description}
                                            </p>
                                        </button>
                                    ))}
                                </div>

                                <div className="flex justify-end">
                                    <Button
                                        type="submit"
                                        disabled={
                                            processing || selectedPlan === ''
                                        }
                                    >
                                        Continue to checkout
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </Form>
            </div>
        </div>
    );
}

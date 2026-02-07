<?php

namespace App\Providers;

use App\Services\Billing\BillingService;
use App\Services\Billing\CashierBillingService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BillingService::class, CashierBillingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureStripeBillingWarnings();
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }

    protected function configureStripeBillingWarnings(): void
    {
        $warnings = [];

        $stripeKey = config('services.stripe.key');
        $stripeSecret = config('services.stripe.secret');

        if (! is_string($stripeKey) || $stripeKey === '') {
            $warnings[] = 'Stripe publishable key is missing. Set STRIPE_KEY in your .env.';
        }

        if (! is_string($stripeSecret) || $stripeSecret === '') {
            $warnings[] = 'Stripe secret key is missing. Set STRIPE_SECRET in your .env.';
        }

        /** @var array<string, array<string, mixed>> $plans */
        $plans = config('services.stripe.plans', []);
        $priceIds = [];

        foreach ($plans as $planKey => $plan) {
            $priceId = $plan['price_id'] ?? null;

            if (! is_string($priceId) || $priceId === '') {
                $warnings[] = sprintf(
                    'Stripe price for "%s" is missing. Set %s in your .env.',
                    $plan['title'] ?? $planKey,
                    strtoupper('stripe_price_'.$planKey)
                );

                continue;
            }

            $priceIds[] = $priceId;
        }

        if (count($priceIds) !== count(array_unique($priceIds))) {
            $warnings[] = 'Monthly and yearly plans must use different Stripe price IDs.';
        }

        config()->set('services.stripe.warnings', $warnings);
    }
}

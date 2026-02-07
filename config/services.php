<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'trial_days' => env('STRIPE_TRIAL_DAYS', 14),
        'default_plan' => env('STRIPE_DEFAULT_PLAN', 'free'),
        'seat_quantity' => [
            'sync_with_stripe' => env('STRIPE_SYNC_SEAT_QUANTITY', false),
        ],
        'plans' => [
            'free' => [
                'enabled' => env('STRIPE_FREE_PLAN_ENABLED', true),
                'billing_mode' => 'free',
                'title' => 'Free',
                'price_label' => '$0',
                'interval_label' => '/forever',
                'description' => 'Ideal for validating your idea before scaling.',
                'features' => [
                    'Up to 3 active seats',
                    'Workspace and invitation flow',
                    'Email support',
                ],
                'feature_flags' => [
                    'team_invitations',
                    'workspace_management',
                ],
                'limits' => [
                    'seats' => env('STRIPE_FREE_PLAN_SEAT_LIMIT', 3),
                ],
                'highlighted' => false,
            ],
            'starter_monthly' => [
                'enabled' => true,
                'billing_mode' => 'stripe',
                'price_id' => env('STRIPE_PRICE_STARTER_MONTHLY'),
                'title' => 'Starter Monthly',
                'price_label' => '$29',
                'interval_label' => '/month',
                'description' => 'Best for early-stage SaaS projects that need fast iteration.',
                'features' => [
                    'Unlimited authenticated users',
                    'Stripe subscription billing',
                    'Core analytics and event tracking',
                ],
                'feature_flags' => [
                    'team_invitations',
                    'workspace_management',
                    'priority_support',
                ],
                'limits' => [
                    'seats' => null,
                ],
                'highlighted' => false,
            ],
            'starter_yearly' => [
                'enabled' => true,
                'billing_mode' => 'stripe',
                'price_id' => env('STRIPE_PRICE_STARTER_YEARLY'),
                'title' => 'Starter Yearly',
                'price_label' => '$290',
                'interval_label' => '/year',
                'description' => 'Lower annual cost with everything in monthly included.',
                'features' => [
                    'Everything in Starter Monthly',
                    'Annual savings over monthly billing',
                    'Priority email support',
                ],
                'feature_flags' => [
                    'team_invitations',
                    'workspace_management',
                    'priority_support',
                ],
                'limits' => [
                    'seats' => null,
                ],
                'highlighted' => true,
            ],
        ],
    ],

];

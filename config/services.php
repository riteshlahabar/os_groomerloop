<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    /*
    |--------------------------------------------------------------------------
    | Stripe
    |--------------------------------------------------------------------------
    |
    | Modules\Billing\Services\Gateways\StripeGateway (invariant #5, spec §30). Set
    | BILLING_GATEWAY=stripe in .env once 'secret' is filled in; the webhook secret is unused
    | until a webhook endpoint exists. 'key' is the publishable key /admin/billing's Stripe.js
    | card field needs; it is handed to that page by GET /api/v1/billing/payment-capabilities
    | (Modules\Billing\Services\CardEntry) and is public by design — it can create tokens and
    | nothing else. The secret key is never exposed to a browser.
    |
    | Both must be set for a card to be addable: 'secret' alone lets the server charge a card
    | already on file, while the page reports card entry unconfigured, since without 'key'
    | there is nothing to tokenise a new one with.
    |
    */
    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

];

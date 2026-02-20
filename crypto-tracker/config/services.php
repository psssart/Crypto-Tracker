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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'moralis' => [
        'api_key' => env('MORALIS_API_KEY'),
        'stream_id' => env('MORALIS_STREAM_ID'),
    ],

    'alchemy' => [
        'key' => env('ALCHEMY_API_KEY'),
        'auth_token' => env('ALCHEMY_AUTH_TOKEN'),
        'ethereum' => env('ALCHEMY_WEBHOOK_ID_ETH'),
        'arbitrum' => env('ALCHEMY_WEBHOOK_ID_ARB'),
        'solana'   => env('ALCHEMY_WEBHOOK_ID_SOL'),
        'polygon'   => env('ALCHEMY_WEBHOOK_ID_POL'),
        'base'   => env('ALCHEMY_WEBHOOK_ID_BAS'),
    ],

    'coingecko' => [
        'key' => env('COINGECKO_API_KEY'),
    ],

    'etherscan' => [
        'key' => env('ETHERSCAN_API_KEY'),
    ],

    'trongrid' => [
        'key' => env('TRONGRID_API_KEY'),
    ],

    'helius' => [
        'key' => env('HELIUS_API_KEY'),
    ],

    'blockchair' => [
        'key' => env('BLOCKCHAIR_API_KEY'),
    ],

    'telegram-bot-api' => [
        'token' => env('TELEGRAM_TOKEN'),
    ],

];

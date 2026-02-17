<?php

return [

    'providers' => [

        'alltick' => [
            'name' => 'AllTick',
            'description' => 'Real-time & historical market data (stocks, forex, crypto).',
            'docs_url' => 'https://en.apis.alltick.co/',
            'secret_field' => [
                'key' => 'api_key',
                'label' => 'API token',
                'help' => 'Use the token from your AllTick dashboard (API keys section).',
            ],
            'default_settings' => [
                // 'default_symbol' => 'BTCUSDT',
            ],
            'health_check' => [
                'enabled' => true,
            ],
            'ws_source_id' => 'alltick',
        ],

        'freecryptoapi' => [
            'name' => 'FreeCryptoAPI',
            'description' => 'Crypto prices and data via simple REST API.',
            'docs_url' => 'https://freecryptoapi.com/documentation/',
            'secret_field' => [
                'key' => 'api_key',
                'label' => 'API key',
                'help' => 'Used as Bearer token in the Authorization header.',
            ],
            'default_settings' => [
                // 'default_symbol' => 'BTC',
            ],
            'health_check' => [
                'enabled' => true,
            ],
            'ws_source_id' => 'freecryptoapi',
        ],

        'bybit' => [
            'name' => 'Bybit',
            'description' => 'Crypto derivatives & spot exchange (API v5).',
            'docs_url' => 'https://bybit-exchange.github.io/docs/v5/intro',
            'secret_field' => [
                'key' => 'api_key',
                'label' => 'API key',
                'help' => 'System-generated API key from Bybit API Management (v5).',
            ],
            'extra_secret_field' => [
                'key' => 'api_secret',
                'label' => 'API secret',
                'help' => 'Secret key paired with your API key. Never expose this in client apps.',
            ],
            'default_settings' => [],
            'health_check' => [
                'enabled' => true,
            ],
            'ws_source_id' => 'bybit',
        ],

        'moralis' => [
            'name' => 'Moralis',
            'description' => 'Web3 data API for wallet balances, NFTs, and transaction history.',
            'docs_url' => 'https://docs.moralis.io/',
            'secret_field' => [
                'key' => 'api_key',
                'label' => 'API key',
                'help' => 'Get your API key from the Moralis admin panel.',
            ],
            'default_settings' => [],
            'health_check' => [
                'enabled' => false,
            ],
        ],

        'alchemy' => [
            'name' => 'Alchemy',
            'description' => 'Blockchain infrastructure and developer tools.',
            'docs_url' => 'https://docs.alchemy.com/',
            'secret_field' => [
                'key' => 'api_key',
                'label' => 'API key',
                'help' => 'Create an app in Alchemy dashboard and copy the API key.',
            ],
            'default_settings' => [],
            'health_check' => [
                'enabled' => false,
            ],
        ],

        'etherscan' => [
            'name' => 'Etherscan',
            'description' => 'Ethereum blockchain explorer API for contract and transaction data.',
            'docs_url' => 'https://docs.etherscan.io/',
            'secret_field' => [
                'key' => 'api_key',
                'label' => 'API key',
                'help' => 'Generate a free API key from your Etherscan account.',
            ],
            'default_settings' => [],
            'health_check' => [
                'enabled' => false,
            ],
        ],

        'openai' => [
            'name' => 'OpenAI',
            'description' => 'LLM responses (text, tools, structured output).',
            'docs_url' => 'https://platform.openai.com/docs/api-reference/responses',
            'secret_field' => [
                'key' => 'api_key',
                'label' => 'API key',
                'help' => 'Create an API key in OpenAI dashboard. Keep it server-side only.',
            ],
            'default_settings' => [
                'model' => 'gpt-4o-mini',
            ],
            'health_check' => [
                'enabled' => true,
            ],
        ],

    ],

];

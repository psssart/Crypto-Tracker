<?php

namespace Database\Seeders;

use App\Models\Network;
use Illuminate\Database\Seeder;

class NetworkSeeder extends Seeder
{
    public function run(): void
    {
        $networks = [
            [
                'name' => 'Ethereum',
                'slug' => 'ethereum',
                'chain_id' => 1,
                'currency_symbol' => 'ETH',
                'explorer_url' => 'https://etherscan.io',
            ],
            [
                'name' => 'Polygon',
                'slug' => 'polygon',
                'chain_id' => 137,
                'currency_symbol' => 'MATIC',
                'explorer_url' => 'https://polygonscan.com',
            ],
            [
                'name' => 'BNB Smart Chain',
                'slug' => 'bsc',
                'chain_id' => 56,
                'currency_symbol' => 'BNB',
                'explorer_url' => 'https://bscscan.com',
            ],
            [
                'name' => 'Solana',
                'slug' => 'solana',
                'chain_id' => null,
                'currency_symbol' => 'SOL',
                'explorer_url' => 'https://explorer.solana.com',
            ],
            [
                'name' => 'Bitcoin',
                'slug' => 'bitcoin',
                'chain_id' => null,
                'currency_symbol' => 'BTC',
                'explorer_url' => 'https://blockstream.info',
            ],
            [
                'name' => 'Tron',
                'slug' => 'tron',
                'chain_id' => null,
                'currency_symbol' => 'TRX',
                'explorer_url' => 'https://tronscan.org',
            ],
            [
                'name' => 'Arbitrum',
                'slug' => 'arbitrum',
                'chain_id' => 42161,
                'currency_symbol' => 'ARB',
                'explorer_url' => 'https://arbiscan.io',
            ],
            [
                'name' => 'BASE',
                'slug' => 'base',
                'chain_id' => 8453,
                'currency_symbol' => 'ETH',
                'explorer_url' => 'https://basescan.org',
            ],
            [
                'name' => 'Optimism',
                'slug' => 'optimism',
                'chain_id' => 10,
                'currency_symbol' => 'ETH',
                'explorer_url' => 'https://optimistic.etherscan.io',
            ],
            [
                'name' => 'Avalanche',
                'slug' => 'avalanche',
                'chain_id' => 43114,
                'currency_symbol' => 'AVAX',
                'explorer_url' => 'https://snowscan.xyz',
            ],
            [
                'name' => 'Fantom',
                'slug' => 'fantom',
                'chain_id' => 250,
                'currency_symbol' => 'FTM',
                'explorer_url' => 'https://ftmscan.com',
            ],
            [
                'name' => 'Cronos',
                'slug' => 'cronos',
                'chain_id' => 25,
                'currency_symbol' => 'CRO',
                'explorer_url' => 'https://explorer.cronos.org',
            ],
            [
                'name' => 'Gnosis',
                'slug' => 'gnosis',
                'chain_id' => 100,
                'currency_symbol' => 'xDAI',
                'explorer_url' => 'https://gnosisscan.io',
            ],
            [
                'name' => 'Linea',
                'slug' => 'linea',
                'chain_id' => 59144,
                'currency_symbol' => 'ETH',
                'explorer_url' => 'https://lineascan.build',
            ],
            [
                'name' => 'Flow',
                'slug' => 'flow',
                'chain_id' => 747,
                'currency_symbol' => 'FLOW',
                'explorer_url' => 'https://evm.flowscan.io',
            ],
            [
                'name' => 'Chiliz',
                'slug' => 'chiliz',
                'chain_id' => 88888,
                'currency_symbol' => 'CHZ',
                'explorer_url' => 'https://scan.chiliz.com',
            ],
            [
                'name' => 'PulseChain',
                'slug' => 'pulsechain',
                'chain_id' => 369,
                'currency_symbol' => 'PLS',
                'explorer_url' => 'https://scan.pulsechain.com',
            ],
            [
                'name' => 'Sei',
                'slug' => 'sei',
                'chain_id' => 1329,
                'currency_symbol' => 'SEI',
                'explorer_url' => 'https://seitrace.com',
            ],
            [
                'name' => 'Ronin',
                'slug' => 'ronin',
                'chain_id' => 2020,
                'currency_symbol' => 'RON',
                'explorer_url' => 'https://app.roninchain.com',
            ],
            [
                'name' => 'Lisk',
                'slug' => 'lisk',
                'chain_id' => 1135,
                'currency_symbol' => 'ETH',
                'explorer_url' => 'https://blockscout.lisk.com',
            ],
            [
                'name' => 'Monad',
                'slug' => 'monad',
                'chain_id' => 10143,
                'currency_symbol' => 'MON',
                'explorer_url' => 'https://testnet.monadexplorer.com',
            ],
            [
                'name' => 'HyperEVM',
                'slug' => 'hyperevm',
                'chain_id' => 999,
                'currency_symbol' => 'HYPE',
                'explorer_url' => 'https://hyperliquid.cloud.blockscout.com',
            ],
            [
                'name' => 'Palm',
                'slug' => 'palm',
                'chain_id' => 11297108109,
                'currency_symbol' => 'PALM',
                'explorer_url' => 'https://palm.chainlens.com',
            ],
        ];

        foreach ($networks as $network) {
            Network::updateOrCreate(
                ['slug' => $network['slug']],
                $network,
            );
        }
    }
}

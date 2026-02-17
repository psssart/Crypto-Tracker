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
        ];

        foreach ($networks as $network) {
            Network::updateOrCreate(
                ['slug' => $network['slug']],
                $network,
            );
        }
    }
}

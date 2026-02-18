<?php

namespace Database\Seeders;

use App\Jobs\SyncWalletHistory;
use App\Models\Network;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class WhaleWalletSeeder extends Seeder
{
    public function run(): void
    {
        $networks = Network::all()->keyBy('slug');

        $whales = [
            // Ethereum (~20)
            ['slug' => 'ethereum', 'address' => '0x28C6c06298d514Db089934071355E5743bf21d60', 'label' => 'Binance Hot Wallet', 'balance' => 4_200_000_000],
            ['slug' => 'ethereum', 'address' => '0x21a31Ee1afC51d94C2eFcCAa2092aD1028285549', 'label' => 'Binance Hot Wallet 2', 'balance' => 1_800_000_000],
            ['slug' => 'ethereum', 'address' => '0xDFd5293D8e347dFe59E90eFd55b2956a1343963d', 'label' => 'Binance Hot Wallet 3', 'balance' => 1_500_000_000],
            ['slug' => 'ethereum', 'address' => '0xA090e606E30bD747d4E6245a1517EbE430F0057e', 'label' => 'Coinbase Commerce', 'balance' => 2_100_000_000],
            ['slug' => 'ethereum', 'address' => '0x503828976D22510aad0201ac7EC88293211D23Da', 'label' => 'Coinbase Cold Wallet', 'balance' => 3_500_000_000],
            ['slug' => 'ethereum', 'address' => '0x2910543Af39abA0Cd09dBb2D50200b3E800A63D2', 'label' => 'Kraken', 'balance' => 1_900_000_000],
            ['slug' => 'ethereum', 'address' => '0x40B38765696e3d5d8d9d834D8AaD4bB6e418E489', 'label' => 'OKX', 'balance' => 1_300_000_000],
            ['slug' => 'ethereum', 'address' => '0x1111111254EEB25477B68fb85Ed929f73A960582', 'label' => '1inch Aggregation Router', 'balance' => 450_000_000],
            ['slug' => 'ethereum', 'address' => '0xae0ee0a63a2ce6baeeffe56e7714fb4efe48d419', 'label' => 'Lido: Staking', 'balance' => 8_500_000_000],
            ['slug' => 'ethereum', 'address' => '0x47ac0Fb4F2D84898e4D9E7b4DaB3C24507a6D503', 'label' => 'Binance Cold Wallet', 'balance' => 6_200_000_000],
            ['slug' => 'ethereum', 'address' => '0x56Eddb7aa87536c09CCc2793473599fD21A8b17F', 'label' => 'Bitfinex', 'balance' => 1_400_000_000],
            ['slug' => 'ethereum', 'address' => '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045', 'label' => 'Vitalik Buterin', 'balance' => 650_000_000],
            ['slug' => 'ethereum', 'address' => '0x3DdfA8eC3052539b6C9549F12cEA2C295cfF5296', 'label' => 'Justin Sun', 'balance' => 1_100_000_000],
            ['slug' => 'ethereum', 'address' => '0x0716a17FBAeE714f1E6aB0f9d59edbC5f09815C0', 'label' => 'Jump Trading', 'balance' => 780_000_000],
            ['slug' => 'ethereum', 'address' => '0x4862733B5FdDFd35f35ea8CCf08F5045e57388B3', 'label' => 'Wintermute', 'balance' => 520_000_000],
            ['slug' => 'ethereum', 'address' => '0x8103683202aa8DA10536036EDef04CDd865C225E', 'label' => 'Arbitrum: Bridge', 'balance' => 3_800_000_000],
            ['slug' => 'ethereum', 'address' => '0x00000000219ab540356cBB839Cbe05303d7705Fa', 'label' => 'Ethereum Beacon Deposit', 'balance' => 45_000_000_000],
            ['slug' => 'ethereum', 'address' => '0x742d35Cc6634C0532925a3b844Bc9e7595f2bD1E', 'label' => 'Gemini', 'balance' => 900_000_000],
            ['slug' => 'ethereum', 'address' => '0xBE0eB53F46cd790Cd13851d5EFf43D12404d33E8', 'label' => 'Binance 7', 'balance' => 2_800_000_000],
            ['slug' => 'ethereum', 'address' => '0xDA9dfA130Df4dE4673b89022EE50ff26f6EA73Cf', 'label' => 'Kraken 13', 'balance' => 1_050_000_000],

            // BSC (~4)
            ['slug' => 'bsc', 'address' => '0x8894E0a0c962CB723c1ef8a1B63d0b16E4249681', 'label' => 'Binance: Staking', 'balance' => 5_600_000_000],
            ['slug' => 'bsc', 'address' => '0x1FAD009fDa5B3015D135b4559C0eBF02D7628c09', 'label' => 'PancakeSwap: Deployer', 'balance' => 320_000_000],
            ['slug' => 'bsc', 'address' => '0xF68a4b64162906efF0fF6aE34E2bB1Cd42FEf62d', 'label' => 'Binance Hot Wallet (BSC)', 'balance' => 2_100_000_000],
            ['slug' => 'bsc', 'address' => '0x631Fc1EA2270e98fbD9D92658eCe0f5a269Aa161', 'label' => 'Venus Protocol', 'balance' => 410_000_000],

            // Polygon (~3)
            ['slug' => 'polygon', 'address' => '0xA0c68C638235ee32657e8f720a23ceC1bFc77C77', 'label' => 'Polygon: Bridge', 'balance' => 2_900_000_000],
            ['slug' => 'polygon', 'address' => '0x5B67676a984807a212b1c59eBFc9B3568a474F0a', 'label' => 'QuickSwap', 'balance' => 180_000_000],
            ['slug' => 'polygon', 'address' => '0x0d500B1d8E8eF31E21C99d1Db9A6444d3ADf1270', 'label' => 'Wrapped MATIC', 'balance' => 850_000_000],

            // Bitcoin (~2)
            ['slug' => 'bitcoin', 'address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa', 'label' => 'Satoshi Genesis', 'balance' => 1_200_000_000],
            ['slug' => 'bitcoin', 'address' => 'bc1qgdjqv0av3q56jvd82tkdjpy7gdp9ut8tlqmgrpmv24sq90ecnvqqjwvw97', 'label' => 'Bitfinex Cold Wallet', 'balance' => 4_800_000_000],

            // Solana (~1)
            ['slug' => 'solana', 'address' => 'GK2zqSsXLA2rwVZk347RYhh6jJpRsCA69FjLW93ZGi3B', 'label' => 'Solana Foundation', 'balance' => 2_200_000_000],
        ];

        foreach ($whales as $whale) {
            $network = $networks->get($whale['slug']);

            if (! $network) {
                continue;
            }

            $wallet = Wallet::updateOrCreate(
                ['network_id' => $network->id, 'address' => $whale['address']],
                [
                    'is_whale' => true,
                    'balance_usd' => $whale['balance'],
                    'metadata' => ['label' => $whale['label']],
                ],
            );

            SyncWalletHistory::dispatch($wallet);
        }
    }
}

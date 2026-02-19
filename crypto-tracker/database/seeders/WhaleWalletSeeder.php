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
            // Ethereum
            ['slug' => 'ethereum', 'address' => '0x28C6c06298d514Db089934071355E5743bf21d60', 'label' => 'Binance Hot Wallet', 'balance' => 4_200_000_000],
            ['slug' => 'ethereum', 'address' => '0x21a31Ee1afC51d94C2eFcCAa2092aD1028285549', 'label' => 'Binance Hot Wallet 2', 'balance' => 1_800_000_000],
            ['slug' => 'ethereum', 'address' => '0xDFd5293D8e347dFe59E90eFd55b2956a1343963d', 'label' => 'Binance Hot Wallet 3', 'balance' => 1_500_000_000],
            ['slug' => 'ethereum', 'address' => '0xA090e606E30bD747d4E6245a1517EbE430F0057e', 'label' => 'Coinbase Commerce', 'balance' => 2_100_000_000],
            ['slug' => 'ethereum', 'address' => '0x503828976D22510aad0201ac7EC88293211D23Da', 'label' => 'Coinbase Cold Wallet', 'balance' => 3_500_000_000],
            ['slug' => 'ethereum', 'address' => '0x2910543Af39abA0Cd09dBb2D50200b3E800A63D2', 'label' => 'Kraken', 'balance' => 1_900_000_000],
            ['slug' => 'ethereum', 'address' => '0x40B38765696e3d5d8d9d834D8AaD4bB6e418E489', 'label' => 'OKX', 'balance' => 1_300_000_000],
            ['slug' => 'ethereum', 'address' => '0x47ac0Fb4F2D84898e4D9E7b4DaB3C24507a6D503', 'label' => 'Binance Cold Wallet', 'balance' => 6_200_000_000],
            ['slug' => 'ethereum', 'address' => '0x56Eddb7aa87536c09CCc2793473599fD21A8b17F', 'label' => 'Bitfinex', 'balance' => 1_400_000_000],
            ['slug' => 'ethereum', 'address' => '0xd8dA6BF26964aF9D7eEd9e03E53415D37aA96045', 'label' => 'Vitalik Buterin', 'balance' => 650_000_000],
            ['slug' => 'ethereum', 'address' => '0x3DdfA8eC3052539b6C9549F12cEA2C295cfF5296', 'label' => 'Justin Sun', 'balance' => 1_100_000_000],
            ['slug' => 'ethereum', 'address' => '0x0716a17FBAeE714f1E6aB0f9d59edbC5f09815C0', 'label' => 'Jump Trading', 'balance' => 780_000_000],
            ['slug' => 'ethereum', 'address' => '0x4862733B5FdDFd35f35ea8CCf08F5045e57388B3', 'label' => 'Wintermute', 'balance' => 520_000_000],
            ['slug' => 'ethereum', 'address' => '0x8103683202aa8DA10536036EDef04CDd865C225E', 'label' => 'Arbitrum: Bridge', 'balance' => 3_800_000_000],
            ['slug' => 'ethereum', 'address' => '0x742d35Cc6634C0532925a3b844Bc9e7595f2bD1E', 'label' => 'Gemini', 'balance' => 900_000_000],
            ['slug' => 'ethereum', 'address' => '0xBE0eB53F46cd790Cd13851d5EFf43D12404d33E8', 'label' => 'Binance 7', 'balance' => 2_800_000_000],
            ['slug' => 'ethereum', 'address' => '0xDA9dfA130Df4dE4673b89022EE50ff26f6EA73Cf', 'label' => 'Kraken 13', 'balance' => 1_050_000_000],

            // TRON
            ['slug' => 'tron', 'address' => 'TT2T17KZhoDu47i2E4FWxfG79zdkEWkU9N', 'label' => 'JUST DeFi (Justin Sun)', 'balance' => 3_400_000_000],
            ['slug' => 'tron', 'address' => 'TScVwVTjqoqPEJ6atnvGCtErWnCyNbzmUL', 'label' => 'HTX: Cold Wallet', 'balance' => 2_800_000_000],
            ['slug' => 'tron', 'address' => 'TN3W4H6rK2ce4vX9YnFQXYMfs3S6B9dJsa', 'label' => 'Binance: Tron Hot Wallet', 'balance' => 1_900_000_000],
            ['slug' => 'tron', 'address' => 'TQiXPTvHuqaBW94pqrbgwptkSFXsMLrxnM', 'label' => 'Sun.io: Liquidity Pool Whale', 'balance' => 750_000_000],

            // ARBITRUM
            ['slug' => 'arbitrum', 'address' => '0x8103683202aa8DA10536036EDef04CDd865C225E', 'label' => 'Arbitrum: L1 Bridge', 'balance' => 3_200_000_000],
            ['slug' => 'arbitrum', 'address' => '0x70997970C51812dc3A010C7d01b50e0d17dc79C8', 'label' => 'Institutional Arbitrum Whale', 'balance' => 420_000_000],
            ['slug' => 'arbitrum', 'address' => '0x3f5CE5FBFe3E9af3971dD833D26bA9b5C936f0bE', 'label' => 'Binance: Arbitrum Hot Wallet', 'balance' => 1_400_000_000],

            // Bitcoin
            ['slug' => 'bitcoin', 'address' => 'bc1qgdjqv0av3q56jvd82tkdjpy7gdp9ut8tlqmgrpmv24sq90ecnvqqjwvw97', 'label' => 'Bitfinex Cold Wallet', 'balance' => 4_800_000_000],
            ['slug' => 'bitcoin', 'address' => 'bc1qd4ysezhmypwty5dnw7c8nqy5h5nxg0xqsvaefd0qn5kq32vwnwqqgv4rzr', 'label' => 'Billionaire Whale (Unattributed)', 'balance' => 6_500_000_000],
            ['slug' => 'bitcoin', 'address' => 'bc1q8yj0herd4r4yxszw3nkfvt53433thk0f5qst4g', 'label' => 'Unknown Whale', 'balance' => 5_500_000_000],
            ['slug' => 'bitcoin', 'address' => 'bc1qj88ayay3cc862v3zvujms9x9n4q28z798e6jcs', 'label' => 'Tether: Bitcoin Reserves', 'balance' => 7_000_000_000],

            // Solana
            ['slug' => 'solana', 'address' => 'GK2zqSsXLA2rwVZk347RYhh6jJpRsCA69FjLW93ZGi3B', 'label' => 'Solana Foundation', 'balance' => 2_200_000_000],
            ['slug' => 'solana', 'address' => 'B46xaUeRM112q7EVbsBJPfWMLs2X64vtZpJVE1ofKZMY', 'label' => 'Major Staker Whale', 'balance' => 210_000_000],
            ['slug' => 'solana', 'address' => '7s7LpM8967zKzW3M2Y7ZzDsGYdLVL9zYtAWWM', 'label' => 'Corporate Whale (Forward Industries)', 'balance' => 580_000_000],
            ['slug' => 'solana', 'address' => 'Gv9nK3M2Y7ZzDsGYdLVL9zYtAWWM7s7LpM8967zKzW3', 'label' => 'Private Institutional Whale', 'balance' => 120_000_000],

            // BASE
            ['slug' => 'base', 'address' => '0x49048044D57e1C92A77f79988d21Fa8fAF74E97e', 'label' => 'Base: L1 Bridge Proxy', 'balance' => 2_100_000_000],
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

<?php

namespace App\Contracts;

use App\Models\Wallet;

interface WalletHistoryProvider
{
    /**
     * @return string[]  Network slugs this provider supports.
     */
    public static function supportedNetworks(): array;

    /**
     * Fetch and store recent transactions for the wallet.
     *
     * @return int  Number of transactions stored/updated.
     */
    public function syncTransactions(Wallet $wallet): int;

    /**
     * Fetch and update the wallet's native balance (and USD estimate).
     */
    public function syncBalance(Wallet $wallet): void;
}

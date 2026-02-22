<?php

namespace App\Contracts;

use App\Models\Wallet;
use Illuminate\Support\Carbon;

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
     * Fetch and store transactions for the wallet within a date range.
     * Paginates through results as needed.
     *
     * @return int  Number of transactions stored/updated.
     */
    public function fetchTransactions(Wallet $wallet, Carbon $from, Carbon $to): int;

    /**
     * Fetch and update the wallet's native balance (and USD estimate).
     */
    public function syncBalance(Wallet $wallet): void;
}

<?php

namespace App\Contracts;

use App\DTOs\ParsedTransaction;
use Illuminate\Http\Request;

interface CryptoWebhookHandler
{
    public function verifySignature(Request $request): bool;

    /** @return ParsedTransaction[] */
    public function parseTransactions(array $payload): array;
}

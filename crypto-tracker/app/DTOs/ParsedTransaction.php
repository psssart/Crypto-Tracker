<?php

namespace App\DTOs;

use Illuminate\Support\Carbon;

class ParsedTransaction
{
    public function __construct(
        public readonly string $networkSlug,
        public readonly string $txHash,
        public readonly string $fromAddress,
        public readonly string $toAddress,
        public readonly string $amount,
        public readonly ?int $blockNumber,
        public readonly ?Carbon $minedAt,
    ) {}
}

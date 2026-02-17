<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'hash',
        'from_address',
        'to_address',
        'amount',
        'fee',
        'block_number',
        'mined_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:18',
            'fee' => 'decimal:18',
            'mined_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}

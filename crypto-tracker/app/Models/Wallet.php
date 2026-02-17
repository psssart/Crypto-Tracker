<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    use HasFactory;

    protected $fillable = [
        'network_id',
        'address',
        'is_whale',
        'metadata',
        'balance_usd',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_whale' => 'boolean',
            'metadata' => 'array',
            'balance_usd' => 'decimal:18',
            'last_synced_at' => 'datetime',
        ];
    }

    public function network(): BelongsTo
    {
        return $this->belongsTo(Network::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_wallet')
            ->withPivot('custom_label', 'is_notified', 'notify_threshold_usd')
            ->withTimestamps();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}

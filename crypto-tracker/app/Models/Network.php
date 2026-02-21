<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Network extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'chain_id',
        'currency_symbol',
        'explorer_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function wallets(): HasMany
    {
        return $this->hasMany(Wallet::class);
    }
}

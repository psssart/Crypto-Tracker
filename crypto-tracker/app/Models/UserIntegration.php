<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'api_key',
        'settings',
        'last_used_at',
        'revoked_at',
    ];

    protected $casts = [
        'settings' => 'encrypted:array',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',

        'api_key' => 'encrypted',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

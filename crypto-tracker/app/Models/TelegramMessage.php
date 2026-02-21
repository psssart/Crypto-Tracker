<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramMessage extends Model
{
    protected $fillable = [
        'telegram_chat_id',
        'direction',
        'text',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
        ];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(TelegramChat::class, 'telegram_chat_id');
    }
}

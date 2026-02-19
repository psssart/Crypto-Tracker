<?php

namespace App\Models;

use App\Notifications\CustomResetPassword;
use App\Notifications\VerifyEmailCustom;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function sendEmailVerificationNotification()
    {
        $this->notify(new VerifyEmailCustom);
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new CustomResetPassword($token));
    }

    public function integrations()
    {
        return $this->hasMany(UserIntegration::class);
    }

    public function wallets()
    {
        return $this->belongsToMany(Wallet::class, 'user_wallet')
            ->withPivot('custom_label', 'is_notified', 'notify_threshold_usd', 'notify_via', 'notify_direction', 'notify_cooldown_minutes', 'last_notified_at', 'notes')
            ->withTimestamps();
    }

    public function telegramChat(): HasOne
    {
        return $this->hasOne(TelegramChat::class);
    }

    public function routeNotificationForTelegram(): ?string
    {
        return $this->telegramChat?->chat_id;
    }
}

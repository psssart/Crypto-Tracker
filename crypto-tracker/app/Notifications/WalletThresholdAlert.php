<?php

namespace App\Notifications;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Telegram\TelegramChannel;
use NotificationChannels\Telegram\TelegramMessage;

class WalletThresholdAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Wallet $wallet,
        public Transaction $transaction,
        public string $amountUsd,
    ) {
    }

    public function via(object $notifiable): array
    {
        $pivot = $notifiable->wallets()
            ->where('wallet_id', $this->wallet->id)
            ->first()
            ?->pivot;

        $notifyVia = $pivot?->notify_via ?? 'email';

        $channels = [];

        if (in_array($notifyVia, ['email', 'both'])) {
            $channels[] = 'mail';
        }

        if (in_array($notifyVia, ['telegram', 'both'])) {
            $channels[] = TelegramChannel::class;
        }

        return $channels ?: ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = $notifiable->wallets()
            ->where('wallet_id', $this->wallet->id)
            ->first()
            ?->pivot->custom_label ?? $this->wallet->address;

        $network = $this->wallet->network->name ?? 'Unknown';

        return (new MailMessage)
            ->subject("Wallet Alert: \${$this->amountUsd} transaction detected")
            ->line("A transaction exceeding your threshold was detected on **{$label}** ({$network}).")
            ->line("**Transaction hash:** {$this->transaction->hash}")
            ->line("**From:** {$this->transaction->from_address}")
            ->line("**To:** {$this->transaction->to_address}")
            ->line("**Estimated value:** \${$this->amountUsd} USD");
    }

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $label = $notifiable->wallets()
            ->where('wallet_id', $this->wallet->id)
            ->first()
            ?->pivot->custom_label ?? $this->wallet->address;

        $network = $this->wallet->network->name ?? 'Unknown';

        $text = implode("\n", [
            "*Wallet Alert*",
            '',
            "A transaction of *\${$this->amountUsd} USD* was detected on *{$label}* ({$network}).",
            '',
            "Hash: `{$this->transaction->hash}`",
            "From: `{$this->transaction->from_address}`",
            "To: `{$this->transaction->to_address}`",
        ]);

        return TelegramMessage::create($text);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'wallet_id' => $this->wallet->id,
            'transaction_id' => $this->transaction->id,
            'amount_usd' => $this->amountUsd,
        ];
    }
}

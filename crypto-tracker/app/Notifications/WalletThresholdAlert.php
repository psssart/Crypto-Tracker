<?php

namespace App\Notifications;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
        return ['mail'];
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

    public function toArray(object $notifiable): array
    {
        return [
            'wallet_id' => $this->wallet->id,
            'transaction_id' => $this->transaction->id,
            'amount_usd' => $this->amountUsd,
        ];
    }
}

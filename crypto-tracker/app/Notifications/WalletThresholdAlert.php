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

    private const EXPLORER_MAP = [
        'ethereum' => 'https://etherscan.io/tx/',
        'polygon' => 'https://polygonscan.com/tx/',
        'bsc' => 'https://bscscan.com/tx/',
        'arbitrum' => 'https://arbiscan.io/tx/',
        'base' => 'https://basescan.org/tx/',
        'solana' => 'https://solscan.io/tx/',
        'bitcoin' => 'https://mempool.space/tx/',
        'tron' => 'https://tronscan.org/#/transaction/',
    ];

    private const NATIVE_SYMBOL = [
        'ethereum' => 'ETH',
        'polygon' => 'MATIC',
        'bsc' => 'BNB',
        'arbitrum' => 'ETH',
        'base' => 'ETH',
        'solana' => 'SOL',
        'bitcoin' => 'BTC',
        'tron' => 'TRX',
    ];

    public function toTelegram(object $notifiable): TelegramMessage
    {
        $label = $notifiable->wallets()
            ->where('wallet_id', $this->wallet->id)
            ->first()
            ?->pivot->custom_label;

        $slug = $this->wallet->network->slug ?? '';
        $networkName = $this->wallet->network->name ?? 'Unknown';
        $symbol = self::NATIVE_SYMBOL[$slug] ?? '';
        $explorerBase = self::EXPLORER_MAP[$slug] ?? null;

        $walletAddr = strtolower($this->wallet->address);
        $isIncoming = strtolower($this->transaction->to_address) === $walletAddr;
        $direction = $isIncoming ? "\xE2\xAC\x87 Incoming" : "\xE2\xAC\x86 Outgoing";

        $amount = rtrim(rtrim($this->transaction->amount, '0'), '.');
        $amountLine = "<b>\${$this->amountUsd}</b> ({$amount} {$symbol})";

        $from = $this->shortenAddress($this->transaction->from_address);
        $to = $this->shortenAddress($this->transaction->to_address);

        $walletLabel = $label ? htmlspecialchars($label) : '<code>' . $this->shortenAddress($this->wallet->address) . '</code>';

        $lines = [
            "\xF0\x9F\x9A\xA8 <b>Wallet Alert</b>",
            '',
            "\xF0\x9F\x92\xB0 {$amountLine}",
            "\xF0\x9F\x93\x8D {$networkName}  \xC2\xB7  {$direction}",
            "\xF0\x9F\x91\x9B {$walletLabel}",
            '',
            "From: <code>{$from}</code>",
            "To: <code>{$to}</code>",
        ];

        if ($explorerBase) {
            $lines[] = '';
            $lines[] = "\xF0\x9F\x94\x97 <a href=\"{$explorerBase}{$this->transaction->hash}\">View transaction</a>";
        }

        return TelegramMessage::create(implode("\n", $lines))
            ->options(['parse_mode' => 'HTML', 'disable_web_page_preview' => true]);
    }

    private function shortenAddress(string $address): string
    {
        if (strlen($address) <= 16) {
            return $address;
        }

        return substr($address, 0, 6) . '...' . substr($address, -4);
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

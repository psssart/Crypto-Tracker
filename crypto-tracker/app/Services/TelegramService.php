<?php

namespace App\Services;

use App\Models\TelegramChat;
use App\Models\TelegramMessage;
use SergiX44\Nutgram\Nutgram;

class TelegramService
{
    public function __construct(
        private Nutgram $bot,
    ) {}

    public function sendMessage(string $chatId, string $text): void
    {
        $this->bot->sendMessage($text, chat_id: $chatId);

        $telegramChat = TelegramChat::where('chat_id', $chatId)->first();

        if ($telegramChat) {
            TelegramMessage::create([
                'telegram_chat_id' => $telegramChat->id,
                'direction' => 'out',
                'text' => $text,
            ]);
        }
    }
}

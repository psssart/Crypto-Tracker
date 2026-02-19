<?php

namespace App\Telegram\Middleware;

use App\Models\TelegramChat;
use App\Models\TelegramMessage;
use SergiX44\Nutgram\Nutgram;

class LogIncomingUpdates
{
    public function __invoke(Nutgram $bot, $next): void
    {
        $chat = $bot->chat();

        if ($chat !== null) {
            $telegramChat = TelegramChat::updateOrCreate(
                ['chat_id' => (string) $chat->id],
                [
                    'telegram_id' => $bot->userId() ? (string) $bot->userId() : null,
                    'username' => $bot->user()?->username,
                    'type' => $chat->type ?? 'private',
                ],
            );

            TelegramMessage::create([
                'telegram_chat_id' => $telegramChat->id,
                'direction' => 'in',
                'text' => $bot->message()?->text,
                'raw_payload' => json_decode(json_encode($bot->update()), true),
            ]);
        }

        $next($bot);
    }
}

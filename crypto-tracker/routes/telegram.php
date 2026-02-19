<?php

/** @var SergiX44\Nutgram\Nutgram $bot */

use App\Models\TelegramChat;
use App\Telegram\Middleware\LogIncomingUpdates;
use Illuminate\Support\Facades\Cache;
use SergiX44\Nutgram\Nutgram;

/*
|--------------------------------------------------------------------------
| Nutgram Handlers
|--------------------------------------------------------------------------
|
| Here is where you can register telegram handlers for Nutgram. These
| handlers are loaded by the NutgramServiceProvider. Enjoy!
|
*/

$bot->middleware(LogIncomingUpdates::class);

$bot->onCommand('start', function (Nutgram $bot) {
    $appUrl = config('app.url');
    $bot->sendMessage(
        "Welcome to Crypto Tracker!\n\n"
        . "To receive wallet alerts here, register or log in at {$appUrl} and connect Telegram from your profile page."
    );
})->description('Start the bot');

$bot->onCommand('start {token}', function (Nutgram $bot, string $token) {
    $chat = $bot->chat();

    $userId = Cache::pull("telegram_link:{$token}");

    if (! $userId) {
        $bot->sendMessage('This link has expired or is invalid. Please generate a new one from your profile.');

        return;
    }

    TelegramChat::updateOrCreate(
        ['chat_id' => (string) $chat->id],
        [
            'user_id' => $userId,
            'telegram_id' => $bot->userId() ? (string) $bot->userId() : null,
            'username' => $bot->user()?->username,
            'type' => $chat->type ?? 'private',
        ],
    );

    $bot->sendMessage('Your Telegram account has been linked successfully! You will now receive notifications here.');
});

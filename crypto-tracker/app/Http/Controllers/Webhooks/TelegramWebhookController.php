<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use SergiX44\Nutgram\Nutgram;

class TelegramWebhookController extends Controller
{
    /**
     * Handle the incoming Telegram webhook request.
     */
    public function __invoke(Nutgram $bot)
    {
        // Nutgram's run() method automatically parses the Request
        // and matches it against your definitions in routes/telegram.php
        $bot->run();

        return response()->json(['status' => 'success']);
    }
}

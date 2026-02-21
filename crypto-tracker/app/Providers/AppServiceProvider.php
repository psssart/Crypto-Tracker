<?php

namespace App\Providers;

use App\Models\TelegramChat;
use App\Models\TelegramMessage;
use App\Services\ApiService;
use App\Services\TelegramService;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public array $singletons = [

        ApiService::class,
        TelegramService::class,

    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceScheme('https');

        Vite::prefetch(concurrency: 3);

        Event::listen(NotificationSent::class, function (NotificationSent $event) {
            if ($event->channel !== 'telegram') {
                return;
            }

            $chatId = $event->notifiable->routeNotificationForTelegram();

            if (! $chatId) {
                return;
            }

            $telegramChat = TelegramChat::where('chat_id', $chatId)->first();

            if (! $telegramChat) {
                return;
            }

            $text = null;
            $response = $event->response;

            if (is_object($response) && method_exists($response, 'toArray')) {
                $text = $response->toArray()['text'] ?? null;
            }

            TelegramMessage::create([
                'telegram_chat_id' => $telegramChat->id,
                'direction' => 'out',
                'text' => $text,
                'raw_payload' => [
                    'notification' => get_class($event->notification),
                    'data' => $event->notification->toArray($event->notifiable),
                ],
            ]);
        });
    }
}

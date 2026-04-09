<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Horizon\Events\LongWaitDetected;

class SendHorizonTelegramAlert
{
    public function handle(LongWaitDetected $event): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (! $token || ! $chatId) {
            return;
        }

        $minutes = (int) round($event->seconds / 60);
        $message = sprintf(
            "⚠️ *Horizon — Long Wait Detected*\n\nConnection: `%s`\nQueue: `%s`\nWait: *%d min*\nApp: `%s`",
            $event->connection,
            $event->queue,
            $minutes,
            config('app.name'),
        );

        Http::timeout(5)
            ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'Markdown',
            ])
            ->onError(function () use ($message) {
                Log::warning('Horizon Telegram alert failed to send.', ['message' => $message]);
            });
    }
}

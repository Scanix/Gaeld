<?php

namespace Tests\Unit\Listeners;

use App\Listeners\SendHorizonTelegramAlert;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Horizon\Events\LongWaitDetected;
use Tests\TestCase;

/**
 * SendHorizonTelegramAlert previously had zero test coverage despite being
 * the only alerting path when a queue's wait time exceeds Horizon's
 * threshold. A silent regression here (e.g. wrong config keys, message
 * formatting throwing) means production queue backlogs go unnoticed.
 */
class SendHorizonTelegramAlertTest extends TestCase
{
    public function test_does_nothing_when_telegram_is_not_configured(): void
    {
        config()->set('services.telegram.bot_token', null);
        config()->set('services.telegram.chat_id', null);

        Http::fake();

        (new SendHorizonTelegramAlert)->handle(new LongWaitDetected('redis', 'default', 120));

        Http::assertNothingSent();
    }

    public function test_sends_a_formatted_telegram_message_when_configured(): void
    {
        config()->set('services.telegram.bot_token', 'test-token');
        config()->set('services.telegram.chat_id', '12345');
        config()->set('app.name', 'Gäld Test');

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);

        (new SendHorizonTelegramAlert)->handle(new LongWaitDetected('redis', 'exports', 180));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.telegram.org/bottest-token/sendMessage'
                && $request['chat_id'] === '12345'
                && str_contains($request['text'], 'Connection: `redis`')
                && str_contains($request['text'], 'Queue: `exports`')
                && str_contains($request['text'], 'Wait: *3 min*')
                && str_contains($request['text'], 'Gäld Test');
        });
    }

    public function test_logs_a_warning_when_the_telegram_request_fails(): void
    {
        config()->set('services.telegram.bot_token', 'test-token');
        config()->set('services.telegram.chat_id', '12345');

        Http::fake(['api.telegram.org/*' => Http::response('bad request', 400)]);

        Log::shouldReceive('warning')
            ->once()
            ->with('Horizon Telegram alert failed to send.', \Mockery::on(
                fn (array $context) => isset($context['message'])
            ));

        (new SendHorizonTelegramAlert)->handle(new LongWaitDetected('redis', 'default', 60));
    }
}

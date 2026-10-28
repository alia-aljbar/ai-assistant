<?php

use App\Channels\Telegram\TelegramApiException;
use App\Channels\Telegram\TelegramClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Any request that is not explicitly faked fails the test.
    Http::preventStrayRequests();
});

it('sends a text message to the chat', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    (new TelegramClient('fake-token'))->sendMessage(111, 'Hello');

    Http::assertSent(fn (Request $request) => $request->url() === 'https://api.telegram.org/botfake-token/sendMessage'
        && $request['chat_id'] === 111
        && $request['text'] === 'Hello');
});

it('splits messages longer than the Telegram limit', function () {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    (new TelegramClient('fake-token'))->sendMessage(111, str_repeat('a', 5000));

    Http::assertSentCount(2);
});

it('does not call Telegram for blank text', function () {
    Http::fake();

    (new TelegramClient('fake-token'))->sendMessage(111, "  \n ");

    Http::assertNothingSent();
});

it('throws a descriptive exception that does not leak the token', function () {
    Http::fake(['api.telegram.org/*' => Http::response(
        ['ok' => false, 'description' => 'Bad Request: chat not found'],
        400,
    )]);

    try {
        (new TelegramClient('secret-token-123'))->sendMessage(111, 'Hello');
        $this->fail('Expected TelegramApiException was not thrown.');
    } catch (TelegramApiException $e) {
        expect($e->getMessage())
            ->toContain('chat not found')
            ->not->toContain('secret-token-123');
    }
});

it('hides the token when the connection itself fails', function () {
    Http::fake(fn () => throw new ConnectionException(
        'cURL error 28 for https://api.telegram.org/botsecret-token-123/sendMessage',
    ));

    try {
        (new TelegramClient('secret-token-123'))->sendMessage(111, 'Hello');
        $this->fail('Expected TelegramApiException was not thrown.');
    } catch (TelegramApiException $e) {
        expect($e->getMessage())->not->toContain('secret-token-123');
    }
});

it('is resolved from the container with the configured token', function () {
    config()->set('services.telegram.token', 'abc');
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    app(TelegramClient::class)->sendMessage(1, 'x');

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/botabc/'));
});

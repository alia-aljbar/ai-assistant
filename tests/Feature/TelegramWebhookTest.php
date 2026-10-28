<?php

use App\Jobs\ProcessIncomingMessage;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config()->set('services.telegram.secret', 'test-secret');
    config()->set('services.telegram.allowed_chat_id', '111');
});

function telegramUpdate(int $chatId = 111, string $text = 'hello'): array
{
    return [
        'update_id' => 1,
        'message' => [
            'message_id' => 1,
            'chat' => ['id' => $chatId, 'type' => 'private'],
            'text' => $text,
        ],
    ];
}

it('rejects requests without the secret token', function () {
    Queue::fake();

    $this->postJson('/api/telegram/webhook', telegramUpdate())
        ->assertForbidden();

    Queue::assertNothingPushed();
});

it('rejects requests with a wrong secret token', function () {
    Queue::fake();

    $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'wrong'])
        ->postJson('/api/telegram/webhook', telegramUpdate())
        ->assertForbidden();

    Queue::assertNothingPushed();
});

it('rejects everything when no secret is configured', function () {
    Queue::fake();
    config()->set('services.telegram.secret', '');

    $this->postJson('/api/telegram/webhook', telegramUpdate())
        ->assertForbidden();

    Queue::assertNothingPushed();
});

it('silently ignores messages from chats that are not allowed', function () {
    Queue::fake();

    $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])
        ->postJson('/api/telegram/webhook', telegramUpdate(chatId: 999))
        ->assertOk();

    Queue::assertNothingPushed();
});

it('ignores updates that are not plain messages', function () {
    Queue::fake();

    $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])
        ->postJson('/api/telegram/webhook', ['update_id' => 2, 'edited_message' => []])
        ->assertOk();

    Queue::assertNothingPushed();
});

it('queues a job for messages from the allowed chat', function () {
    Queue::fake();

    $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => 'test-secret'])
        ->postJson('/api/telegram/webhook', telegramUpdate(text: 'hi there'))
        ->assertOk();

    Queue::assertPushed(
        ProcessIncomingMessage::class,
        fn (ProcessIncomingMessage $job) => $job->message['text'] === 'hi there',
    );
});

<?php

namespace App\Channels\Telegram;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class TelegramClient
{
    /** Telegram rejects text messages longer than this many characters. */
    private const MAX_MESSAGE_LENGTH = 4096;

    public function __construct(private readonly string $token) {}

    public function sendMessage(int|string $chatId, string $text): void
    {
        foreach ($this->chunks($text) as $chunk) {
            $this->call('sendMessage', [
                'chat_id' => $chatId,
                'text' => $chunk,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function call(string $method, array $payload): void
    {
        if ($this->token === '') {
            throw new TelegramApiException('Telegram bot token is not configured.');
        }

        try {
            $response = $this->http()->post($method, $payload);
        } catch (ConnectionException) {
            // The original message contains the request URL, i.e. the bot token.
            throw new TelegramApiException("Telegram {$method} failed: connection error.");
        }

        if ($response->failed()) {
            throw new TelegramApiException(sprintf(
                'Telegram %s failed with HTTP %d: %s',
                $method,
                $response->status(),
                (string) ($response->json('description') ?? 'unknown error'),
            ));
        }
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl('https://api.telegram.org/bot'.$this->token.'/')
            ->timeout(15)
            ->asJson();
    }

    /**
     * @return list<string>
     */
    private function chunks(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        return mb_str_split($text, self::MAX_MESSAGE_LENGTH);
    }
}

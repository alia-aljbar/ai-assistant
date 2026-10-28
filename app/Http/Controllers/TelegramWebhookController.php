<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessIncomingMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        // 1) Only Telegram knows the secret we registered with the webhook.
        $expected = (string) config('services.telegram.secret');
        $provided = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        abort_unless($expected !== '' && hash_equals($expected, $provided), 403);

        // 2) We only handle plain messages for now.
        $message = $request->input('message');

        if (! is_array($message)) {
            return response()->json(['ok' => true]);
        }

        // 3) Whitelist: only the owner's chat is allowed to talk to the agent.
        $allowed = (string) config('services.telegram.allowed_chat_id');
        $chatId = (string) data_get($message, 'chat.id', '');

        if ($allowed === '' || $chatId !== $allowed) {
            return response()->json(['ok' => true]);
        }

        // 4) Answer Telegram immediately; the real work happens in the queue.
        ProcessIncomingMessage::dispatch($message);

        return response()->json(['ok' => true]);
    }
}

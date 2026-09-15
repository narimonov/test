<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\Support\TelegramBridge;
use App\Services\SupportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Telegram posts every agent message here. A reply to one of our handover
 * messages is delivered back into that user's support chat on the site.
 *
 * Telegram sends the configured secret in a header on every call; anything
 * without it is dropped.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramBridge $telegram, SupportService $support)
    {
        if (! $telegram->verifySecret($request->header('X-Telegram-Bot-Api-Secret-Token'))) {
            Log::warning('Rejected a Telegram webhook with a bad secret');

            return response()->json(['ok' => false], 403);
        }

        $update = $request->all();
        $text = $update['message']['text'] ?? null;

        if (! $text) {
            return response()->json(['ok' => true]);
        }

        $conversationId = $telegram->conversationIdFromReply($update);

        if (! $conversationId) {
            // Chatter in the agents' channel that is not a reply to a handover.
            return response()->json(['ok' => true]);
        }

        $conversation = Conversation::where('type', Conversation::TYPE_SUPPORT)->find($conversationId);

        if (! $conversation) {
            return response()->json(['ok' => true]);
        }

        $from = $update['message']['from'] ?? [];
        $agentName = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')) ?: 'Support';

        $support->recordAgentReply(
            $conversation,
            $text,
            $agentName,
            (string) ($update['message']['message_id'] ?? '')
        );

        return response()->json(['ok' => true]);
    }
}

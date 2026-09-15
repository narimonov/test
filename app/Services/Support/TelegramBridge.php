<?php

namespace App\Services\Support;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Carries escalated support chats to and from Telegram.
 *
 * Outbound: we post into the agents' chat, tagging the message with the
 * conversation id. Agents reply to that message.
 *
 * Inbound: Telegram's webhook gives us the reply together with the message it
 * replied to, and the tag in that original tells us which conversation the
 * answer belongs to.
 */
class TelegramBridge
{
    /** @var array */
    protected $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function isConfigured(): bool
    {
        return ! empty($this->config['bot_token']) && ! empty($this->config['chat_id']);
    }

    /** Hand a conversation over to the agents. */
    public function escalate(Conversation $conversation, string $question, array $context = []): bool
    {
        $lines = [
            '🔧 Support handover — #' . $conversation->id,
            'From: ' . ($context['name'] ?? 'Unknown') . ' (' . ($context['role'] ?? 'user') . ')',
            $context['email'] ?? '',
            '',
            $question,
            '',
            'Reply to this message and your answer goes straight back to them.',
        ];

        return $this->send(implode("\n", array_filter($lines, fn ($line) => $line !== '')));
    }

    /** Relay a user's follow-up on an already escalated conversation. */
    public function relayUserMessage(Conversation $conversation, Message $message): bool
    {
        return $this->send('💬 #' . $conversation->id . ' — ' . ($message->author_name ?: 'User') . ":\n" . $message->body);
    }

    /**
     * Pull the conversation id back out of the message an agent replied to.
     */
    public function conversationIdFromReply(array $update): ?int
    {
        $repliedTo = $update['message']['reply_to_message']['text'] ?? '';

        return preg_match('/#(\d+)/', $repliedTo, $matches) ? (int) $matches[1] : null;
    }

    public function verifySecret(?string $providedSecret): bool
    {
        $expected = $this->config['webhook_secret'] ?? null;

        // Without a configured secret the webhook stays shut rather than open.
        if (! $expected) {
            return false;
        }

        return is_string($providedSecret) && hash_equals($expected, $providedSecret);
    }

    protected function send(string $text): bool
    {
        if (! $this->isConfigured()) {
            // No bot yet: the handover is still recorded, an agent picks it up
            // from the admin panel instead.
            Log::info('Telegram not configured, support handover left in the admin queue', ['text' => $text]);

            return false;
        }

        try {
            $response = Http::timeout(10)->post(
                "https://api.telegram.org/bot{$this->config['bot_token']}/sendMessage",
                ['chat_id' => $this->config['chat_id'], 'text' => $text]
            );

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Telegram send failed', ['message' => $e->getMessage()]);

            return false;
        }
    }
}

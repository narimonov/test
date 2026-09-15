<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Support\AiResponder;
use App\Services\Support\TelegramBridge;

/**
 * Runs the support conversation: the assistant answers first, and a human
 * takes over the moment it cannot help or the user asks for one.
 */
class SupportService
{
    /** @var AiResponder */
    protected $ai;

    /** @var TelegramBridge */
    protected $telegram;


    public function __construct(AiResponder $ai, TelegramBridge $telegram)
    {
        $this->ai = $ai;
        $this->telegram = $telegram;
    }

    public function openFor(User $user): Conversation
    {
        $existing = Conversation::where('type', 'support')
            ->where('status', 'open')
            ->whereHas('participants', fn ($query) => $query->where('user_id', $user->id))
            ->latest()
            ->first();

        if ($existing) {
            return $existing;
        }

        $conversation = Conversation::create([
            'type'    => 'support',
            'subject' => 'Support',
            'status'  => 'open',
        ]);

        $conversation->participants()->create(['user_id' => $user->id]);

        $conversation->messages()->create([
            'author_type' => Message::AUTHOR_AI,
            'author_name' => 'Assistant',
            'body'        => "Hi — what can I help you with? I can answer questions about verification, "
                . "documents, plans, scoring and reviews. If I can't, I'll bring in a person.",
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        return $conversation->fresh();
    }

    /**
     * Record the user's message and produce the next reply.
     */
    public function handleUserMessage(Conversation $conversation, User $user, string $body): array
    {
        $message = $conversation->messages()->create([
            'user_id'     => $user->id,
            'author_type' => Message::AUTHOR_USER,
            'author_name' => $user->name,
            'body'        => $body,
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        // Once a human is on it, the assistant stops answering.
        if ($conversation->isEscalated()) {
            $this->telegram->relayUserMessage($conversation, $message);

            return ['escalated' => true, 'reply' => null];
        }

        $answer = $this->ai->answer($this->history($conversation), [
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role,
        ]);

        $reply = $conversation->messages()->create([
            'author_type'       => Message::AUTHOR_AI,
            'author_name'       => 'Assistant',
            'body'              => $answer['reply'],
            'resolved_question' => $answer['resolved'],
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        if ($answer['resolved']) {
            return ['escalated' => false, 'reply' => $reply];
        }

        /*
         * Two ways to reach a person. Either the question needs one outright —
         * a refund, account access, a direct ask — or the assistant has come up
         * short several times running. Answering questions well, however many,
         * is never a reason to fetch anyone.
         */
        $immediate = ! empty($answer['immediate']);

        if ($immediate || $this->countUnresolved($conversation) >= (int) config('support.ai.max_turns_before_escalation', 3)) {
            $this->escalate($conversation, $user, $body);

            return ['escalated' => true, 'reply' => $reply];
        }

        return ['escalated' => false, 'reply' => $reply];
    }

    public function escalate(Conversation $conversation, User $user, string $question): void
    {
        if ($conversation->isEscalated()) {
            return;
        }

        $conversation->forceFill(['subject' => 'Support — with an agent'])->save();

        $delivered = $this->telegram->escalate($conversation, $question, [
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->role,
        ]);

        // Without a bot configured nobody is watching Telegram, so say what
        // actually happens rather than promising a reply that will not come.
        $conversation->messages()->create([
            'author_type' => Message::AUTHOR_SYSTEM,
            'author_name' => 'System',
            'body'        => $delivered
                ? 'Handed over to a support agent. Their reply will appear here.'
                : 'Handed over to our team. It is in the support queue and someone will reply here.',
        ]);
    }

    /** A reply that arrived from Telegram. */
    public function recordAgentReply(Conversation $conversation, string $body, string $agentName, ?string $telegramMessageId = null): Message
    {
        $message = $conversation->messages()->create([
            'author_type'         => Message::AUTHOR_AGENT,
            'author_name'         => $agentName,
            'body'                => $body,
            'telegram_message_id' => $telegramMessageId,
        ]);

        $conversation->forceFill([
            'last_message_at' => now(),
            'subject'         => 'Support — with an agent',
        ])->save();

        return $message;
    }

    protected function history(Conversation $conversation): array
    {
        return $conversation->messages()
            ->whereIn('author_type', [Message::AUTHOR_USER, Message::AUTHOR_AI])
            ->orderBy('created_at')
            ->get()
            ->map(fn (Message $message) => [
                'role'    => $message->author_type === Message::AUTHOR_USER ? 'user' : 'assistant',
                'content' => (string) $message->body,
            ])
            ->all();
    }

    /**
     * How many questions in a row the assistant has failed to answer, read
     * back off its own replies. A single answered question resets the run.
     */
    protected function countUnresolved(Conversation $conversation): int
    {
        $recent = $conversation->messages()
            ->where('author_type', Message::AUTHOR_AI)
            ->whereNotNull('resolved_question')
            ->orderByDesc('id')
            ->limit(10)
            ->pluck('resolved_question');

        $run = 0;

        foreach ($recent as $resolved) {
            if ($resolved) {
                break;
            }

            $run++;
        }

        return $run;
    }
}

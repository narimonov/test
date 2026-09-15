<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\SupportService;
use Illuminate\Http\Request;

/**
 * The support queue.
 *
 * Escalations are pushed to Telegram when a bot is configured, but they land
 * here either way — so a handover is never lost just because the bot is not
 * set up yet.
 */
class AdminSupportController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('status', 'escalated');

        $conversations = Conversation::query()
            ->where('type', Conversation::TYPE_SUPPORT)
            ->with(['users:id,name,email,role'])
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->get()
            ->filter(function (Conversation $conversation) use ($filter) {
                if ($filter === 'all') {
                    return true;
                }

                return $filter === 'escalated'
                    ? $conversation->isEscalated()
                    : ! $conversation->isEscalated();
            })
            ->map(function (Conversation $conversation) {
                $user = $conversation->users->first();
                $last = $conversation->messages()->latest('id')->first();

                return [
                    'id'              => $conversation->id,
                    'status'          => $conversation->status,
                    'escalated'       => $conversation->isEscalated(),
                    'messages_count'  => $conversation->messages_count,
                    'last_message_at' => $conversation->last_message_at,
                    'last_message'    => $last ? mb_substr((string) $last->body, 0, 120) : null,
                    'waiting'         => $last && $last->author_type === Message::AUTHOR_USER,
                    'user'            => $user ? [
                        'id' => $user->id, 'name' => $user->name,
                        'email' => $user->email, 'role' => $user->role,
                    ] : null,
                ];
            })
            ->values();

        return response()->json(['conversations' => $conversations]);
    }

    public function show(Conversation $conversation)
    {
        abort_unless($conversation->type === Conversation::TYPE_SUPPORT, 404);

        return response()->json([
            'conversation' => [
                'id'        => $conversation->id,
                'escalated' => $conversation->isEscalated(),
                'user'      => $conversation->users()->first(['users.id', 'name', 'email', 'role']),
            ],
            'messages' => $conversation->messages()->orderBy('created_at')->get([
                'id', 'author_type', 'author_name', 'body', 'created_at',
            ]),
        ]);
    }

    /** An admin answering from the panel, same as an agent in Telegram. */
    public function reply(Request $request, Conversation $conversation, SupportService $support)
    {
        abort_unless($conversation->type === Conversation::TYPE_SUPPORT, 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:3000'],
        ]);

        $support->recordAgentReply($conversation, $data['body'], $request->user()->name);

        return $this->show($conversation->fresh());
    }
}

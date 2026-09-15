<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\SupportService;
use Illuminate\Http\Request;

/**
 * The support widget that sits on every page. The assistant answers first and
 * a human agent takes over when it cannot help.
 */
class SupportController extends Controller
{
    public function show(Request $request, SupportService $support)
    {
        $conversation = $support->openFor($request->user());

        return response()->json($this->payload($conversation));
    }

    public function store(Request $request, SupportService $support)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:3000'],
        ]);

        $conversation = $support->openFor($request->user());

        $support->handleUserMessage($conversation, $request->user(), $data['body']);

        return response()->json($this->payload($conversation->fresh()));
    }

    /** The widget polls this for agent replies arriving from Telegram. */
    public function poll(Request $request, SupportService $support)
    {
        $conversation = $support->openFor($request->user());

        return response()->json($this->payload($conversation));
    }

    /** User asks for a person without waiting for the assistant to give up. */
    public function escalate(Request $request, SupportService $support)
    {
        $conversation = $support->openFor($request->user());

        $support->escalate($conversation, $request->user(), 'User asked for a human agent.');

        return response()->json($this->payload($conversation->fresh()));
    }

    protected function payload(Conversation $conversation): array
    {
        return [
            'conversation' => [
                'id'        => $conversation->id,
                'status'    => $conversation->status,
                'escalated' => $conversation->isEscalated(),
            ],
            'messages' => $conversation->messages()->orderBy('created_at')->get([
                'id', 'author_type', 'author_name', 'body', 'created_at',
            ]),
        ];
    }
}

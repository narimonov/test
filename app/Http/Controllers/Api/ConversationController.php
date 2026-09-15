<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Conversation;
use App\Models\DriverProfile;
use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use App\Services\PlanGate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Chat between a carrier and a driver. Attachments are PDFs or images, stored
 * on the private disk and served through a checked route.
 */
class ConversationController extends Controller
{
    public function index(Request $request)
    {
        $conversations = Conversation::query()
            ->whereHas('participants', fn ($query) => $query->where('user_id', $request->user()->id))
            ->where('type', '!=', Conversation::TYPE_SUPPORT)
            ->with(['carrier:id,company_name', 'driverProfile:id,first_name,last_name', 'jobPost:id,title'])
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn (Conversation $conversation) => $this->summarise($conversation, $request->user()));

        return response()->json(['conversations' => $conversations]);
    }

    /** Carrier opens a chat with a driver. */
    public function store(Request $request, PlanGate $plans)
    {
        $data = $request->validate([
            'driver_profile_id' => ['required', 'integer', 'exists:driver_profiles,id'],
            'job_post_id'       => ['nullable', 'integer', 'exists:job_posts,id'],
            'body'              => ['nullable', 'string', 'max:5000'],
        ]);

        $user = $request->user();
        $carrier = $user->carrier;

        abort_unless($carrier, 403, 'Only company accounts can start a conversation.');

        $driver = DriverProfile::findOrFail($data['driver_profile_id']);

        $this->assertDriverCanReceiveMessages($driver);
        $this->assertMayContact($carrier, $driver, $plans);

        $conversation = Conversation::firstOrCreate([
            'type'              => Conversation::TYPE_HIRING,
            'carrier_id'        => $carrier->id,
            'driver_profile_id' => $driver->id,
        ], [
            'job_post_id' => $data['job_post_id'] ?? null,
            'subject'     => $carrier->company_name,
        ]);

        $this->syncParticipants($conversation, $user, $driver);

        if (! empty($data['body'])) {
            $this->appendMessage($conversation, $user, $data['body']);
        }

        return response()->json(['conversation' => $this->summarise($conversation->fresh(), $user)], 201);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->assertParticipant($request, $conversation);

        $messages = $conversation->messages()->with('attachments')->orderBy('created_at')->get();

        $conversation->participants()
            ->where('user_id', $request->user()->id)
            ->update(['last_read_at' => now()]);

        return response()->json([
            'conversation' => $this->summarise($conversation, $request->user()),
            'messages'     => $messages,
        ]);
    }

    public function storeMessage(Request $request, Conversation $conversation)
    {
        $this->assertParticipant($request, $conversation);

        $data = $request->validate([
            'body'           => ['required_without:attachments', 'nullable', 'string', 'max:5000'],
            'attachments'    => ['nullable', 'array', 'max:5'],
            'attachments.*'  => ['file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $message = $this->appendMessage(
            $conversation,
            $request->user(),
            $data['body'] ?? null,
            $request->file('attachments', [])
        );

        return response()->json(['message' => $message->load('attachments')], 201);
    }

    /** Serves a chat attachment to participants only. */
    public function downloadAttachment(Request $request, MessageAttachment $attachment)
    {
        $conversation = $attachment->message->conversation;

        $this->assertParticipant($request, $conversation);

        $disk = Storage::disk(config('documents.disk'));

        abort_unless($disk->exists($attachment->path), 404);

        return response()->file($disk->path($attachment->path), [
            'Content-Type'        => $attachment->mime_type,
            'Content-Disposition' => 'inline; filename="' . $attachment->original_name . '"',
        ]);
    }

    // ------------------------------------------------------------------

    protected function appendMessage(Conversation $conversation, User $user, ?string $body, array $files = []): Message
    {
        $message = $conversation->messages()->create([
            'user_id'     => $user->id,
            'author_type' => Message::AUTHOR_USER,
            'author_name' => $user->name,
            'body'        => $body,
        ]);

        foreach ($files as $file) {
            $path = $file->store("conversations/{$conversation->id}", config('documents.disk'));

            MessageAttachment::create([
                'message_id'    => $message->id,
                'path'          => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getClientMimeType(),
                'size_bytes'    => $file->getSize(),
            ]);
        }

        $conversation->forceFill(['last_message_at' => now()])->save();

        return $message->fresh();
    }

    /**
     * A driver entered by a recruiter has no account, so an in-app message
     * would go nowhere. Say so instead of sending into the void.
     */
    protected function assertDriverCanReceiveMessages(DriverProfile $driver): void
    {
        if ($driver->user_id !== null) {
            return;
        }

        throw ValidationException::withMessages([
            'driver_profile_id' => [
                'This driver was added by hand and has no account yet, so they cannot receive messages here. '
                . ($driver->phone ? "Reach them on {$driver->phone}." : 'Use the contact details on their profile.'),
            ],
        ]);
    }

    /**
     * Starter can only message drivers who applied; Growth and Pro can reach
     * anyone in the pool.
     */
    protected function assertMayContact($carrier, DriverProfile $driver, PlanGate $plans): void
    {
        if ($plans->allows($carrier, 'chat_with_any_driver')) {
            return;
        }

        $applied = Application::where('driver_profile_id', $driver->id)
            ->whereHas('jobPost', fn ($query) => $query->where('carrier_id', $carrier->id))
            ->exists();

        if (! $applied) {
            throw ValidationException::withMessages([
                'driver_profile_id' => ['Your plan only allows messaging drivers who applied to you. '
                    . 'Upgrade to Growth to contact any driver.'],
            ]);
        }
    }

    protected function syncParticipants(Conversation $conversation, User $carrierUser, DriverProfile $driver): void
    {
        $userIds = array_filter([$carrierUser->id, $driver->user_id]);

        foreach ($userIds as $userId) {
            $conversation->participants()->firstOrCreate(['user_id' => $userId]);
        }
    }

    protected function assertParticipant(Request $request, Conversation $conversation): void
    {
        abort_unless(
            $request->user()->isAdmin() || $conversation->includes($request->user()),
            403,
            'You are not part of this conversation.'
        );
    }

    protected function summarise(Conversation $conversation, User $user): array
    {
        $participant = $conversation->participants()->where('user_id', $user->id)->first();
        $lastRead = optional($participant)->last_read_at;

        return [
            'id'              => $conversation->id,
            'type'            => $conversation->type,
            'subject'         => $conversation->subject,
            'status'          => $conversation->status,
            'last_message_at' => $conversation->last_message_at,
            'carrier'         => $conversation->carrier,
            'driver'          => $conversation->driverProfile,
            'job'             => $conversation->jobPost,
            'unread'          => $conversation->messages()
                ->when($lastRead, fn ($query) => $query->where('created_at', '>', $lastRead))
                ->where('user_id', '!=', $user->id)
                ->count(),
        ];
    }
}

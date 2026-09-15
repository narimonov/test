<?php

namespace App\Services\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Anthropic Messages API. Falls back to the rule-based answers whenever the
 * call fails, so support never goes silent because of an upstream outage.
 */
class ClaudeResponder implements AiResponder
{
    /** @var array */
    protected $config;

    /** @var AiResponder */
    protected $fallback;

    public function __construct(array $config, AiResponder $fallback)
    {
        $this->config = $config;
        $this->fallback = $fallback;
    }

    public function answer(array $history, array $context = []): array
    {
        if (empty($this->config['api_key'])) {
            return $this->fallback->answer($history, $context);
        }

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'x-api-key'         => $this->config['api_key'],
                    'anthropic-version' => '2023-06-01',
                ])
                ->post(rtrim($this->config['base_url'], '/') . '/v1/messages', [
                    'model'      => $this->config['model'],
                    'max_tokens' => $this->config['max_tokens'],
                    'system'     => $this->systemPrompt($context),
                    'messages'   => array_map(fn ($turn) => [
                        'role'    => $turn['role'] === 'assistant' ? 'assistant' : 'user',
                        'content' => $turn['content'],
                    ], $history),
                ]);

            if (! $response->successful()) {
                Log::warning('Support AI call failed', ['status' => $response->status()]);

                return $this->fallback->answer($history, $context);
            }

            $reply = collect($response->json('content', []))
                ->where('type', 'text')
                ->pluck('text')
                ->implode("\n");

            if (trim($reply) === '') {
                return $this->fallback->answer($history, $context);
            }

            // The model marks its own handovers so we do not have to guess.
            $resolved = ! str_contains($reply, '[ESCALATE]');

            return [
                'reply'    => trim(str_replace('[ESCALATE]', '', $reply)),
                'resolved' => $resolved,
            ];
        } catch (\Throwable $e) {
            Log::warning('Support AI threw', ['message' => $e->getMessage()]);

            return $this->fallback->answer($history, $context);
        }
    }

    protected function systemPrompt(array $context): string
    {
        $role = $context['role'] ?? 'user';

        return <<<PROMPT
You are the support assistant for DriverHub, a two-sided CDL driver recruiting platform.
Carriers post jobs and search a driver pool; drivers build a profile and apply.

Facts you may rely on:
- Carrier accounts are verified against FMCSA by MC or USDOT number. The confirmation code goes
  to the phone or email FMCSA holds for that carrier, never to an address the user typed.
- Drivers upload CDL and medical card photos and mask sensitive areas. Masked areas are destroyed,
  not blurred. The result is a watermarked PDF; only carriers the driver applied to can open it.
- Plans: Starter \$50/mo (3 active jobs), Growth \$100/mo (unlimited jobs, full talent pool,
  scoring weights, chat with any driver), Pro \$500/mo (adds personal recruiting and priority support).
  Payment by card, Payme or Click.
- Reviews require proof of a real working relationship and are checked by an admin before publishing.
  Three published unsatisfactory reviews lead to a blacklist, which can be appealed.
- Drivers are scored 0-100 against carrier criteria, with knockout rules that disqualify outright.

The person you are talking to is a {$role}.

Answer briefly and concretely. Never invent account details, payment status, or anything about a
specific person's file — you cannot see their data. If the question needs account access, a refund,
a legal decision, or anything you are not certain about, reply with a short apology and the exact
token [ESCALATE] so a human takes over.
PROMPT;
    }
}

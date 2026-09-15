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
                'reply'     => trim(str_replace('[ESCALATE]', '', $reply)),
                'resolved'  => $resolved,
                // The prompt asks for that token only when a person is genuinely
                // needed, so treat it as a request to hand over now.
                'immediate' => ! $resolved,
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

More of what you can rely on:
- Scoring: knockout rules disqualify outright (wrong CDL class, expired licence, suspension, under a
  year of experience). Everything else is weighted — experience, accidents, moving violations, job
  hopping, tenure, gaps, endorsements, equipment, SAP status, DUI, work authorisation. Grades: A 85+,
  B 70+, C 50+. Carriers on Growth and Pro can change the weights.
- MVRs need the driver's written authorisation (FCRA and DPPA). A record pulled in the last 30 days
  is reused at no cost instead of being bought again.
- Hiring opens an onboarding checklist: the 49 CFR Part 391 baseline for company drivers, or lease,
  insurance and inspection steps for owner operators. Each step names who must act.
- Flights to orientation can be booked in-app or recorded if bought elsewhere.
- Carrier reputation combines FMCSA's public safety record with Google's rating.
- Drivers never pay anything.

Answer the question asked, in a few short paragraphs, concretely. Answer general questions about what
the platform is and how it works fully and confidently — that is your job, not something to pass on.

Never invent account details, payment status, or anything about a specific person's file; you cannot
see their data. Hand over — by replying with a brief apology and the exact token [ESCALATE] — only
when the question genuinely needs a human: account access, a refund or payment dispute, a legal
matter, a moderation decision, or a direct request to speak to a person.
PROMPT;
    }
}

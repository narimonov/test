<?php

namespace App\Services\Support;

/**
 * Answers the questions support actually gets, without an external call.
 *
 * Anything it does not recognise is handed to a human rather than guessed at —
 * a wrong answer about verification or billing costs more than a handover.
 */
class RuleBasedResponder implements AiResponder
{
    /** @var array<int, array{match: string[], reply: string}> */
    protected $answers = [
        [
            'match' => ['fmcsa', 'dot number', 'mc number', 'not found', 'verify company', 'company verification'],
            'reply' => "Company accounts are verified against FMCSA. Enter the MC or USDOT number exactly as it is "
                . "registered. If it comes back \"not found\", the number is wrong or the carrier is not in the FMCSA "
                . "database yet. If it comes back \"not allowed to operate\", the authority is inactive or out of "
                . "service and the account cannot be opened until that is resolved with FMCSA.\n\n"
                . "The confirmation code is sent to the phone or email FMCSA holds for the carrier, not to the address "
                . "you typed. If you no longer control that contact, update it with FMCSA first.",
        ],
        [
            'match' => ['code', 'sms', 'did not receive', 'didn\'t receive', 'no code', 'resend'],
            'reply' => "Codes expire after 15 minutes. Use \"Send code\" again to get a fresh one, and try the other "
                . "channel if one is not arriving — companies can choose between the phone and the email FMCSA has on "
                . "file. If neither arrives, the contact FMCSA holds may be out of date.",
        ],
        [
            'match' => ['plan', 'price', 'billing', 'subscription', 'upgrade', 'invoice', 'refund', 'pay'],
            'reply' => "There are three plans. Starter at \$50/month covers up to 3 active job posts and applicant "
                . "scoring. Growth at \$100/month removes the limits and opens the full talent pool, scoring weights "
                . "and direct chat. Pro at \$500/month adds personal recruiting, where a recruiter works your req and "
                . "sends matched drivers.\n\nYou can pay by card, Payme or Click from the Billing page. Changing plan "
                . "takes effect immediately and the remaining days on your current term are carried over.",
        ],
        [
            'match' => ['document', 'cdl', 'medical card', 'upload', 'redact', 'blur', 'pdf'],
            'reply' => "Photograph the document and drag a box over anything you do not want carriers to see — the "
                . "licence number, date of birth, home address. Those areas are destroyed, not blurred, so they cannot "
                . "be recovered. The result is saved as a watermarked PDF and only the companies you applied to can "
                . "open it. The original photo is never shared.",
        ],
        [
            'match' => ['review', 'blacklist', 'appeal', 'rating', 'proof'],
            'reply' => "A review has to be backed by proof that the two sides worked together — a rate confirmation, "
                . "settlement, employment letter or similar. Our team checks the proof and contacts the other side "
                . "before the review is published, so nothing goes live unverified.\n\nThree published unsatisfactory "
                . "reviews put an account on the blacklist. If you believe that is unfair, open Blacklist & appeal and "
                . "submit an appeal — an admin reviews it and can lift the restriction.",
        ],
        [
            'match' => ['score', 'ranking', 'criteria', 'weight', 'knockout'],
            'reply' => "Every driver is scored 0–100 against your criteria. Knockout rules disqualify outright — for "
                . "example the wrong CDL class or an expired licence — and the rest are weighted: experience, "
                . "accidents, violations, job hopping and so on. On Growth and Pro you can change those weights on the "
                . "Criteria page and every score recalculates.",
        ],
        [
            'match' => ['chat', 'message', 'contact driver', 'talk to'],
            'reply' => "Open a driver from your applicants or the talent pool and use Message. Starter can chat with "
                . "drivers who applied to you; Growth and Pro can start a conversation with any driver. You can attach "
                . "PDFs and images in the chat.",
        ],
        [
            'match' => ['privacy', 'data', 'gdpr', 'ccpa', 'delete my', 'dppa', 'fcra'],
            'reply' => "The Privacy Policy sets out exactly what we collect and why, including why a phone number and "
                . "a CDL number are handled the way they are under the TCPA, the DPPA and the FCRA. You can request a "
                . "copy of your data or its deletion from that page.",
        ],
    ];

    public function answer(array $history, array $context = []): array
    {
        $question = mb_strtolower((string) (end($history)['content'] ?? ''));

        foreach ($this->answers as $entry) {
            foreach ($entry['match'] as $needle) {
                if (str_contains($question, $needle)) {
                    return ['reply' => $entry['reply'], 'resolved' => true];
                }
            }
        }

        return [
            'reply' => "I don't want to guess at this one. Let me bring in a member of our team — "
                . "they'll reply here shortly.",
            'resolved' => false,
        ];
    }
}

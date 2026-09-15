<?php

namespace App\Services\Support;

/**
 * Answers support questions from a knowledge base, without an external call.
 *
 * Every topic carries weighted terms and the best-scoring one wins, so a
 * question phrased in an unexpected way still lands somewhere sensible. What
 * it will not do is guess about a specific account: a refund, a payment
 * dispute or "why was I rejected" needs a person, and those hand over
 * immediately. General questions never do — an unmatched one gets an honest
 * summary of what this is and what can be asked.
 */
class RuleBasedResponder implements AiResponder
{
    /**
     * Questions only a human can answer, because they need account access,
     * money, or a judgement call.
     */
    protected $needsHuman = [
        'refund', 'chargeback', 'dispute', 'charged twice', 'double charged', 'money back',
        'delete my account', 'close my account', 'cancel my account',
        'why was i rejected', 'why did you reject', 'why am i blacklisted',
        'speak to', 'talk to a human', 'talk to a person', 'real person', 'human agent',
        'lawyer', 'legal action', 'sue', 'subpoena', 'court',
        'my payment failed', 'not working for my account', 'hacked', 'someone else used',
        'bank account', 'bank details', 'routing number', 'direct deposit', 'payout details',
        'my settlement', 'wire my',
    ];

    /**
     * Topics. `terms` are weighted: a term worth 3 is a strong signal, 1 is a
     * hint. The topic with the highest total wins.
     *
     * @var array<int, array{terms: array<string,int>, reply: string|callable}>
     */
    protected $topics = [];

    public function __construct()
    {
        $this->topics = $this->buildTopics();
    }

    public function answer(array $history, array $context = []): array
    {
        $question = mb_strtolower(trim((string) (end($history)['content'] ?? '')));

        if ($question === '') {
            return ['reply' => 'Ask me anything about how DriverHub works.', 'resolved' => true];
        }

        if ($this->wantsAHuman($question)) {
            return [
                'reply' => "That one needs someone who can look at your account, so I'm bringing in a member of "
                    . "our team. They'll reply right here.",
                'resolved'  => false,
                'immediate' => true,
            ];
        }

        $best = $this->bestTopic($question);

        if ($best) {
            $reply = is_callable($best['reply']) ? $best['reply']($context) : $best['reply'];

            return ['reply' => $reply, 'resolved' => true];
        }

        // Nothing matched, but the question is a general one — answer it as
        // best we honestly can rather than pushing it at a person.
        return ['reply' => $this->orientation($context), 'resolved' => true];
    }

    // ------------------------------------------------------------------

    protected function wantsAHuman(string $question): bool
    {
        foreach ($this->needsHuman as $phrase) {
            if (str_contains($question, $phrase)) {
                return true;
            }
        }

        return false;
    }

    protected function bestTopic(string $question): ?array
    {
        $scored = [];

        foreach ($this->topics as $index => $topic) {
            $score = 0;

            foreach ($topic['terms'] as $term => $weight) {
                if (str_contains($question, $term)) {
                    $score += $weight;
                }
            }

            if ($score > 0) {
                $scored[$index] = $score;
            }
        }

        if (! $scored) {
            return null;
        }

        arsort($scored);

        return $this->topics[array_key_first($scored)];
    }

    /** The answer to "what is this?", tailored to who is asking. */
    protected function orientation(array $context): string
    {
        $role = $context['role'] ?? null;

        $intro = "DriverHub is a hiring platform for CDL drivers. Carriers post jobs and search a driver pool; "
            . "drivers build one profile and apply with it. What makes it different is that every driver is scored "
            . "0-100 against criteria the carrier sets, so applicants arrive ranked instead of as a pile of PDFs.\n\n";

        if ($role === 'driver') {
            return $intro . "As a driver you fill in your profile once, upload your CDL and medical card with the "
                . "private parts masked out, then apply to open jobs in one click. You can see how you score, message "
                . "carriers directly, and track onboarding once you are hired.\n\n"
                . "Ask me about your profile, documents, applying, scoring, reviews or privacy.";
        }

        if ($role === 'carrier' || $role === 'admin') {
            return $intro . "As a carrier you post a job with real requirements, and you get two lists: people who "
                . "applied, and matching drivers who have not seen you yet — both ranked. You can tune the scoring "
                . "weights, message drivers, order a motor vehicle record, and run onboarding to the first dispatch.\n\n"
                . "Ask me about verification, plans, scoring, the driver pool, MVRs, onboarding or reviews.";
        }

        return $intro . "Ask me about accounts, verification, plans, documents, scoring or reviews.";
    }

    protected function buildTopics(): array
    {
        return [
            // ---------------------------------------------------------- what is this
            [
                'terms' => [
                    'what is this' => 3, 'what about is this' => 3, 'about this' => 3, 'what does this' => 3,
                    'this website' => 3, 'this site' => 3, 'this platform' => 3, 'this app' => 3,
                    'what is driverhub' => 3, 'who are you' => 2, 'what do you do' => 2,
                    'purpose' => 2, 'explain' => 1, 'overview' => 2, 'tell me about' => 2,
                ],
                'reply' => fn (array $context) => $this->orientation($context),
            ],

            // ---------------------------------------------------------- how it works
            [
                'terms' => [
                    'how does it work' => 3, 'how does this work' => 3, 'how it works' => 3,
                    'how do you work' => 2, 'process' => 1, 'workflow' => 2, 'steps' => 1,
                ],
                'reply' => "End to end it goes like this.\n\n"
                    . "A carrier signs up with its MC or USDOT number, which is checked against FMCSA. The "
                    . "confirmation code goes to the contact FMCSA holds for that carrier, so only the real company "
                    . "can open the account.\n\n"
                    . "A driver signs up, fills in one profile — CDL, experience, safety history, equipment — and "
                    . "uploads their licence and medical card with anything private masked out.\n\n"
                    . "The carrier posts a job with requirements. Knockout rules drop anyone who does not qualify; "
                    . "everyone else is scored 0-100 on weighted criteria and comes back sorted. The carrier can also "
                    . "see matching drivers who have not applied.\n\n"
                    . "From there: message the driver, order a motor vehicle record, hire, and work the onboarding "
                    . "checklist to the first dispatch. Afterwards both sides can review each other, with proof.",
            ],

            // ---------------------------------------------------------- getting started
            [
                'terms' => [
                    'get started' => 3, 'getting started' => 3, 'how do i start' => 3, 'where do i start' => 3,
                    'first step' => 2, 'begin' => 1, 'sign up' => 2, 'register' => 2, 'create account' => 3,
                    'new here' => 2, 'what should i do' => 2,
                    'get hired' => 5, 'find a job' => 3, 'find work' => 3, 'looking for work' => 3,
                    'how do i apply' => 3, 'apply for a job' => 3,
                ],
                'reply' => function (array $context) {
                    $role = $context['role'] ?? null;

                    if ($role === 'driver') {
                        return "Start with Profile — the fuller it is, the better you score and the sooner carriers "
                            . "find you. Then Documents: photograph your CDL and medical card and mask the licence "
                            . "number, date of birth and address. After that, Find jobs and apply. You need your "
                            . "phone or email confirmed before you can apply.";
                    }

                    if ($role === 'carrier') {
                        return "Three things, in order. Confirm your company with the FMCSA code — nothing opens "
                            . "until that is done. Pick a plan on Billing. Then post a job on Job posts, with real "
                            . "requirements, because those requirements drive both the ranking of applicants and the "
                            . "list of matching drivers.\n\n"
                            . "Optionally, set your scoring weights on Criteria first so the very first applicants "
                            . "are already ranked your way.";
                    }

                    return "Create an account as either a driver or a carrier. Drivers fill in a profile and apply "
                        . "to jobs; carriers verify with FMCSA, choose a plan and post jobs.";
                },
            ],

            // ---------------------------------------------------------- FMCSA / company verification
            [
                'terms' => [
                    'fmcsa' => 3, 'dot number' => 3, 'usdot' => 3, 'mc number' => 3, 'docket' => 2,
                    'verify company' => 3, 'company verification' => 3, 'not found' => 2,
                    'allowed to operate' => 3, 'authority' => 2, 'out of service' => 2,
                ],
                'reply' => "Company accounts are verified against FMCSA. Enter the MC or USDOT number exactly as it "
                    . "is registered.\n\n"
                    . "\"Not found\" means the number is wrong or the carrier is not in the FMCSA database yet. "
                    . "\"Not allowed to operate\" means the authority is inactive or out of service, and the account "
                    . "cannot open until that is resolved with FMCSA directly.\n\n"
                    . "The confirmation code is sent to the phone or email FMCSA holds for the carrier — never to an "
                    . "address you typed. That is deliberate: it stops anyone opening an account in a company's name. "
                    . "If you no longer control that contact, update it with FMCSA first.",
            ],

            // ---------------------------------------------------------- codes
            [
                'terms' => [
                    'code' => 2, 'sms' => 2, 'text message' => 2, 'did not receive' => 3, "didn't receive" => 3,
                    'no code' => 3, 'resend' => 3, 'expired' => 2, 'verification' => 2, 'otp' => 3,
                ],
                'reply' => "Codes last 15 minutes. Press \"Send code\" again for a fresh one, and try the other "
                    . "channel if one is not arriving — companies can choose between the phone and the email FMCSA "
                    . "holds, drivers between their own phone and email.\n\n"
                    . "If neither arrives for a company, the contact FMCSA has on file is probably out of date.",
            ],

            // ---------------------------------------------------------- plans and payment
            [
                'terms' => [
                    'plan' => 3, 'price' => 3, 'pricing' => 3, 'cost' => 3, 'how much' => 3, 'billing' => 3,
                    'subscription' => 3, 'upgrade' => 2, 'downgrade' => 2, 'invoice' => 2,
                    'pay' => 2, 'payme' => 3, 'click' => 2, 'stripe' => 3, 'card' => 2,
                    'free' => 2, 'trial' => 2, 'expensive' => 2,
                ],
                'reply' => "Three plans, all monthly:\n\n"
                    . "Starter, \$50 — up to 3 active job posts, applicants ranked by your criteria, talent pool "
                    . "search capped at the top 25 results, chat with drivers who applied to you.\n\n"
                    . "Growth, \$100 — unlimited job posts, the full talent pool with no cap, your own scoring "
                    . "weights, chat with any driver, and manual driver entry.\n\n"
                    . "Pro, \$500 — everything in Growth plus personal recruiting: a recruiter works your requisition, "
                    . "sends matched drivers and helps them through onboarding. That gap is large because Pro is "
                    . "people's time, not software.\n\n"
                    . "Payment is by card through Stripe, or Payme or Click. Changing plan takes effect immediately "
                    . "and unused days carry over. Drivers pay nothing, ever.",
            ],

            // ---------------------------------------------------------- documents
            [
                'terms' => [
                    'document' => 3, 'cdl' => 2, 'medical card' => 3, 'upload' => 3, 'redact' => 3,
                    'blur' => 3, 'mask' => 3, 'pdf' => 2, 'photo' => 2, 'licence' => 2, 'license' => 2,
                    'watermark' => 3, 'scan' => 2,
                ],
                'reply' => "Photograph the document and drag a box over anything you do not want carriers to see — "
                    . "the licence number, date of birth, home address.\n\n"
                    . "Those areas are destroyed, not blurred. Blurred text can be reconstructed, so the area is "
                    . "collapsed to a handful of pixels and stretched back; the original pixels are gone. A watermark "
                    . "is burned into the image itself, then it is saved as a PDF.\n\n"
                    . "Only carriers you applied to can open that PDF. The original photograph is never shared with "
                    . "anyone.",
            ],

            // ---------------------------------------------------------- scoring
            [
                'terms' => [
                    'score' => 3, 'scoring' => 3, 'ranking' => 3, 'rank' => 2, 'criteria' => 3,
                    'weight' => 3, 'knockout' => 3, 'grade' => 2, 'points' => 2, 'tier' => 2,
                    'why am i ranked' => 3, 'disqualified' => 3,
                ],
                'reply' => "Every driver is scored out of 100 against the carrier's criteria.\n\n"
                    . "Knockout rules are an outright no — the wrong CDL class, an expired licence, a suspension, "
                    . "under a year of experience. Fail one and there is no score at all; you show at the bottom "
                    . "marked OUT, with the reason.\n\n"
                    . "Everything else is weighted: experience, accidents, moving violations, job hopping, longest "
                    . "tenure, employment gaps, endorsements, equipment, SAP status, DUI history and work "
                    . "authorisation. The result maps to a grade: A is 85+, B is 70, C is 50.\n\n"
                    . "On Growth and Pro a carrier can change those weights on the Criteria page, and every score "
                    . "recalculates immediately.",
            ],

            // ---------------------------------------------------------- driver pool / matching
            [
                'terms' => [
                    'talent pool' => 3, 'driver pool' => 3, 'find drivers' => 3, 'search drivers' => 3,
                    'matching' => 3, 'matches' => 3, 'candidates' => 2, 'applicants' => 2, 'sourcing' => 2,
                ],
                'reply' => "Two lists come off every job post.\n\n"
                    . "Applicants — people who applied, ranked by your criteria, with the knockouts pushed to the "
                    . "bottom and the reason shown.\n\n"
                    . "Matching drivers — people who meet the same requirements but have not applied. Same scoring, "
                    . "minus anyone already hired elsewhere or blacklisted. You can message them straight from there.\n\n"
                    . "The Driver pool page searches everyone, with filters for state, CDL class, experience, "
                    . "accidents, violations, job hopping, endorsements and equipment. Starter sees the top 25 "
                    . "results; Growth and Pro see everything.",
            ],

            // ---------------------------------------------------------- chat
            [
                'terms' => [
                    'chat' => 3, 'message' => 3, 'contact driver' => 3, 'talk to driver' => 2,
                    'attachment' => 2, 'send file' => 2, 'messaging' => 3,
                ],
                'reply' => "Open a driver from your applicants, the matching list or the pool and press Message. "
                    . "You can attach PDFs and images; files sit on a private disk and only the two of you can open "
                    . "them.\n\n"
                    . "Starter can message drivers who applied to you. Growth and Pro can start a conversation with "
                    . "anyone in the pool. A driver a recruiter entered by hand has no account, so there is nobody to "
                    . "message — call them instead.",
            ],

            // ---------------------------------------------------------- MVR
            [
                'terms' => [
                    'mvr' => 3, 'motor vehicle record' => 3, 'driving record' => 3, 'dmv' => 2,
                    'violations' => 2, 'pull a record' => 3, 'state fee' => 2,
                ],
                'reply' => "Motor vehicle records are ordered from the driver's card, and two rules govern it.\n\n"
                    . "Nothing is pulled without the driver's recorded authorisation — the FCRA wants written "
                    . "permission and the DPPA wants a permissible use. If they have not authorised it, the order is "
                    . "refused and the driver is asked on their privacy page.\n\n"
                    . "A record pulled in the last 30 days is reused instead of bought again. States charge per pull "
                    . "and a month-old record is the same record, so you see \"reusable — no fee\" and pay nothing. "
                    . "Otherwise the state fee is shown before you order.",
            ],

            // ---------------------------------------------------------- onboarding
            [
                'terms' => [
                    'onboarding' => 3, 'orientation' => 2, 'checklist' => 3, 'hire' => 2, 'hired' => 2,
                    'first dispatch' => 3, 'road test' => 3, 'drug test' => 2, 'clearinghouse' => 3,
                    'dqf' => 3, 'qualification file' => 3, 'psp' => 2,
                ],
                'reply' => function (array $context) {
                    $opening = ($context['role'] ?? null) === 'driver'
                        ? "Once a carrier hires you, an onboarding checklist opens for the two of you to work "
                            . "through together."
                        : "Hiring a driver opens an onboarding checklist automatically.";

                    return $opening . "\n\n"
                    . "For a company driver that is the federal baseline in the order carriers actually work it: "
                    . "application, CDL and medical verification, MVR, PSP, previous employer checks, Clearinghouse "
                    . "query, drug test, DOT physical, the qualification file, travel, orientation, road test, ELD "
                    . "training, truck assignment, payroll, first dispatch. Owner operators get lease, insurance and "
                    . "inspection steps instead.\n\n"
                    . "Each step says who is expected to act. A driver can only move their own. Progress counts "
                    . "required steps, and work done elsewhere closes the matching step — ordering an MVR closes the "
                    . "MVR step, booking a flight closes travel.";
                },
            ],

            // ---------------------------------------------------------- travel
            [
                'terms' => [
                    'flight' => 3, 'travel' => 3, 'ticket' => 2, 'airline' => 3, 'airport' => 2,
                    'fly' => 2, 'book a flight' => 3,
                ],
                'reply' => "Driver travel books a flight to orientation for someone you have hired. Search by "
                    . "airport codes and date, pick an option and book.\n\n"
                    . "If you bought the ticket somewhere else, record it instead — onboarding only needs to know the "
                    . "driver can get there.",
            ],

            // ---------------------------------------------------------- reviews and blacklist
            [
                'terms' => [
                    'review' => 3, 'rating' => 3, 'blacklist' => 3, 'appeal' => 3, 'proof' => 3,
                    'feedback' => 2, 'reputation' => 2, 'star' => 2, 'complaint' => 2,
                ],
                'reply' => "A review has to be backed by evidence that the two of you actually worked together — a "
                    . "rate confirmation, settlement, pay stub or employment letter. Our team checks that evidence "
                    . "and contacts the other side before anything is published. A review without proof is never "
                    . "published.\n\n"
                    . "Three published unsatisfactory reviews put an account on the blacklist, and that rule applies "
                    . "to both sides equally. If you think it is unfair, open Standing & appeals and submit one — an "
                    . "admin reviews it and can lift the restriction. Once lifted, the old reviews stop counting, so "
                    . "you are not re-listed the same day.",
            ],

            // ---------------------------------------------------------- privacy and law
            [
                'terms' => [
                    'privacy' => 3, 'gdpr' => 2, 'ccpa' => 3, 'dppa' => 3, 'fcra' => 3, 'tcpa' => 3,
                    'my data' => 3, 'delete my data' => 3, 'consent' => 3, 'legal basis' => 3,
                    'why do you need' => 3, 'personal information' => 2,
                ],
                'reply' => "The Privacy page sets out every field we collect and which rule makes it necessary.\n\n"
                    . "Your phone is covered by the TCPA, which needs your express consent before anyone texts you, "
                    . "including verification codes. Your CDL number is personal information under the DPPA and may "
                    . "only be used for listed purposes, of which verifying a CDL holder for an employer is one. A "
                    . "driving record needs written authorisation under both the FCRA and the DPPA, which is why we "
                    . "ask for it separately.\n\n"
                    . "You can request a copy of your data or its deletion from that page. We do not sell personal "
                    . "information.",
            ],

            // ---------------------------------------------------------- personal recruiting
            [
                'terms' => [
                    'personal recruiting' => 3, 'recruiter' => 3, 'do it for me' => 3,
                    'recruit for me' => 3, 'done for you' => 2,
                ],
                'reply' => "Personal recruiting is part of the Pro plan at \$500/month. You write a brief — lanes, "
                    . "pay, equipment, home time, what disqualifies someone — and a recruiter works it: sources "
                    . "drivers, screens them, sends you the ones that fit and helps each one through onboarding. "
                    . "It runs as a thread you can follow, and referred drivers appear against the request.",
            ],

            // ---------------------------------------------------------- account / login
            [
                'terms' => [
                    'password' => 3, 'log in' => 2, 'login' => 2, 'sign in' => 2, 'locked out' => 3,
                    'blocked' => 3, 'cannot access' => 2, "can't log in" => 3, 'forgot' => 3,
                ],
                'reply' => "If a sign-in is rejected, the usual causes are a mistyped email, an account that has not "
                    . "confirmed its phone or email yet, or a company that has not completed FMCSA verification — "
                    . "until that code is confirmed nothing else opens.\n\n"
                    . "If the message says the account is blocked, an admin blocked it and the reason is shown. Ask "
                    . "me for a person and I will bring one in.",
            ],

            // ---------------------------------------------------------- courtesy
            [
                'terms' => [
                    'hello' => 3, 'hi ' => 3, 'hey' => 3, 'good morning' => 3, 'good afternoon' => 3,
                    'salom' => 3, 'assalom' => 3,
                ],
                'reply' => "Hello. Ask me anything about how DriverHub works — verification, plans, documents, "
                    . "scoring, the driver pool, MVRs, onboarding, reviews or privacy.",
            ],
            [
                'terms' => ['thank' => 3, 'thanks' => 3, 'rahmat' => 3, 'appreciate' => 2],
                'reply' => "Glad that helped. Anything else you want to know?",
            ],
        ];
    }
}

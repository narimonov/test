<?php

/*
|--------------------------------------------------------------------------
| Support widget
|--------------------------------------------------------------------------
|
| Every page carries a support button. The AI assistant answers first; when it
| cannot resolve the question the ticket is escalated to a human agent, who
| works it from Telegram. Whatever the agent sends in Telegram appears in the
| user's chat on the site.
|
*/

return [

    'ai' => [
        // rules = built-in answers, no external call. claude = Anthropic API.
        'driver' => env('SUPPORT_AI_DRIVER', 'rules'),

        'claude' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model'   => env('SUPPORT_AI_MODEL', 'claude-sonnet-5'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
            'max_tokens' => 600,
        ],

        // The assistant hands over after this many unresolved turns.
        'max_turns_before_escalation' => env('SUPPORT_AI_MAX_TURNS', 3),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),

        // Group or channel the agents watch.
        'chat_id'   => env('TELEGRAM_SUPPORT_CHAT_ID'),

        // Telegram calls this with a secret header we check on every request.
        'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    ],
];

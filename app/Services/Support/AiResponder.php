<?php

namespace App\Services\Support;

interface AiResponder
{
    /**
     * Answer a support question.
     *
     * @param  array  $history  [['role' => 'user'|'assistant', 'content' => '...'], ...]
     * @return array{reply: string, resolved: bool, immediate?: bool}
     *         resolved=false means the assistant came up short; a run of those
     *         eventually fetches a person. immediate=true means fetch one now,
     *         because the question needs account access or a judgement call.
     */
    public function answer(array $history, array $context = []): array;
}

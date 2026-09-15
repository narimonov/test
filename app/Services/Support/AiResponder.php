<?php

namespace App\Services\Support;

interface AiResponder
{
    /**
     * Answer a support question.
     *
     * @param  array  $history  [['role' => 'user'|'assistant', 'content' => '...'], ...]
     * @return array{reply: string, resolved: bool}
     *         resolved=false means hand this over to a human.
     */
    public function answer(array $history, array $context = []): array;
}

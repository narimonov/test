<?php

namespace App\Services\Mvr;

/**
 * One motor vehicle record, in the same shape whichever provider returned it.
 */
class MvrResult
{
    public $reference;
    public $status = 'completed';       // completed | pending | failed
    public $licenceStatus;              // valid | suspended | revoked | expired
    public $violations = 0;
    public $accidents = 0;
    public $suspensions = 0;
    public $costCents;
    public $raw = [];
    public $failureReason;

    public static function fromArray(array $data): self
    {
        $result = new self();

        $result->reference     = $data['reference'] ?? null;
        $result->status        = $data['status'] ?? 'completed';
        $result->licenceStatus = $data['licence_status'] ?? null;
        $result->violations    = (int) ($data['violations'] ?? 0);
        $result->accidents     = (int) ($data['accidents'] ?? 0);
        $result->suspensions   = (int) ($data['suspensions'] ?? 0);
        $result->costCents     = $data['cost_cents'] ?? null;
        $result->failureReason = $data['failure_reason'] ?? null;
        $result->raw           = $data['raw'] ?? $data;

        return $result;
    }

    public function isClean(): bool
    {
        return $this->violations === 0
            && $this->accidents === 0
            && $this->suspensions === 0
            && in_array($this->licenceStatus, [null, 'valid'], true);
    }
}

<?php

namespace App\Concerns;

use Illuminate\Validation\ValidationException;

/**
 * Structural guard for the Document/Posting engine: once a transaction header is
 * "posted", update()/destroy() must refuse to silently mutate it — corrections have to
 * go through a return/reversal/adjustment document instead. Models default to a
 * status column valued Draft/Posted/Cancelled; override isPosted() when a model uses
 * a different vocabulary (e.g. StockUpdate's Pending/Approved/Rejected).
 */
trait HasPostingLifecycle
{
    public function isPosted(): bool
    {
        return $this->status === 'Posted';
    }

    public function assertEditable(): void
    {
        if ($this->isPosted()) {
            throw ValidationException::withMessages([
                'status' => class_basename($this)." #{$this->getKey()} is already posted and cannot be edited directly. Use a return, reversal, or adjustment document instead.",
            ]);
        }
    }
}

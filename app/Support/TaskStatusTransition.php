<?php

namespace App\Support;

/**
 * Pure task status-transition matrix used by TaskController::updateStatus.
 * Extracted so the matrix itself (which role may do which transition) is
 * unit-testable without a database — see tests/Unit/TaskStatusTransitionTest.php.
 */
class TaskStatusTransition
{
    private const STRUCTURAL = [
        'pending->in_progress',
        'in_progress->completed',
        'pending->cancelled',
        'in_progress->cancelled',
    ];

    /** True if this is a real transition shape, regardless of who's asking. */
    public static function isStructurallyValid(string $from, string $to): bool
    {
        return in_array("$from->$to", self::STRUCTURAL, true);
    }

    /**
     * True if this exact transition is allowed for THIS caller:
     * pending->in_progress and in_progress->completed are the assignee's;
     * ...->cancelled is a Manager/Owner's.
     */
    public static function isValid(string $from, string $to, bool $isAssignee, bool $isReviewer): bool
    {
        return match (true) {
            $from === 'pending' && $to === 'in_progress' => $isAssignee,
            $from === 'in_progress' && $to === 'completed' => $isAssignee,
            $from === 'pending' && $to === 'cancelled' => $isReviewer,
            $from === 'in_progress' && $to === 'cancelled' => $isReviewer,
            default => false,
        };
    }
}

<?php

namespace Tests\Unit;

use App\Support\TaskStatusTransition;
use PHPUnit\Framework\TestCase;

/**
 * Isolated unit coverage for the task status transition matrix used by
 * TaskController::updateStatus. No database needed.
 */
class TaskStatusTransitionTest extends TestCase
{
    public function test_assignee_can_start_a_pending_task(): void
    {
        $this->assertTrue(TaskStatusTransition::isValid('pending', 'in_progress', true, false));
    }

    public function test_non_assignee_cannot_start_a_pending_task(): void
    {
        $this->assertFalse(TaskStatusTransition::isValid('pending', 'in_progress', false, false));
    }

    public function test_assignee_can_complete_an_in_progress_task(): void
    {
        $this->assertTrue(TaskStatusTransition::isValid('in_progress', 'completed', true, false));
    }

    public function test_reviewer_can_cancel_a_pending_task(): void
    {
        $this->assertTrue(TaskStatusTransition::isValid('pending', 'cancelled', false, true));
    }

    public function test_reviewer_can_cancel_an_in_progress_task(): void
    {
        $this->assertTrue(TaskStatusTransition::isValid('in_progress', 'cancelled', false, true));
    }

    public function test_assignee_alone_cannot_cancel(): void
    {
        $this->assertFalse(TaskStatusTransition::isValid('pending', 'cancelled', true, false));
    }

    public function test_reviewer_cannot_start_or_complete_on_someone_elses_behalf(): void
    {
        $this->assertFalse(TaskStatusTransition::isValid('pending', 'in_progress', false, true));
        $this->assertFalse(TaskStatusTransition::isValid('in_progress', 'completed', false, true));
    }

    public function test_completed_and_cancelled_are_terminal(): void
    {
        $this->assertFalse(TaskStatusTransition::isValid('completed', 'in_progress', true, true));
        $this->assertFalse(TaskStatusTransition::isValid('cancelled', 'in_progress', true, true));
        $this->assertFalse(TaskStatusTransition::isValid('completed', 'cancelled', true, true));
    }

    public function test_structural_validity_is_independent_of_the_caller(): void
    {
        $this->assertTrue(TaskStatusTransition::isStructurallyValid('pending', 'in_progress'));
        $this->assertTrue(TaskStatusTransition::isStructurallyValid('in_progress', 'completed'));
        $this->assertTrue(TaskStatusTransition::isStructurallyValid('pending', 'cancelled'));
        $this->assertTrue(TaskStatusTransition::isStructurallyValid('in_progress', 'cancelled'));
    }

    public function test_structurally_invalid_transitions(): void
    {
        $this->assertFalse(TaskStatusTransition::isStructurallyValid('pending', 'completed'));
        $this->assertFalse(TaskStatusTransition::isStructurallyValid('completed', 'pending'));
        $this->assertFalse(TaskStatusTransition::isStructurallyValid('cancelled', 'completed'));
    }
}

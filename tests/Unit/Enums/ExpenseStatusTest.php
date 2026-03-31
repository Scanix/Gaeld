<?php

namespace Tests\Unit\Enums;

use App\Domains\Expenses\Enums\ExpenseStatus;
use Tests\TestCase;

class ExpenseStatusTest extends TestCase
{
    public function test_pending_can_transition_to_approved(): void
    {
        $this->assertTrue(ExpenseStatus::Pending->canTransitionTo(ExpenseStatus::Approved));
    }

    public function test_pending_cannot_transition_to_posted(): void
    {
        $this->assertFalse(ExpenseStatus::Pending->canTransitionTo(ExpenseStatus::Posted));
    }

    public function test_approved_can_transition_to_posted(): void
    {
        $this->assertTrue(ExpenseStatus::Approved->canTransitionTo(ExpenseStatus::Posted));
    }

    public function test_posted_cannot_transition(): void
    {
        $this->assertEmpty(ExpenseStatus::Posted->allowedTransitions());
    }

    public function test_pending_is_editable(): void
    {
        $this->assertTrue(ExpenseStatus::Pending->isEditable());
    }

    public function test_posted_is_not_editable(): void
    {
        $this->assertFalse(ExpenseStatus::Posted->isEditable());
    }

    public function test_pending_is_deletable(): void
    {
        $this->assertTrue(ExpenseStatus::Pending->isDeletable());
    }

    public function test_approved_is_not_deletable(): void
    {
        $this->assertFalse(ExpenseStatus::Approved->isDeletable());
    }

    public function test_posted_is_not_deletable(): void
    {
        $this->assertFalse(ExpenseStatus::Posted->isDeletable());
    }

    public function test_labels_return_non_empty_translated_strings(): void
    {
        foreach (ExpenseStatus::cases() as $status) {
            $label = $status->label();
            $this->assertIsString($label);
            $this->assertNotEmpty($label);
        }
    }
}

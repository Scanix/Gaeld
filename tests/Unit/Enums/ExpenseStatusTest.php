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

    public function test_pending_can_transition_to_posted(): void
    {
        $this->assertTrue(ExpenseStatus::Pending->canTransitionTo(ExpenseStatus::Posted));
    }

    public function test_approved_can_transition_to_posted(): void
    {
        $this->assertTrue(ExpenseStatus::Approved->canTransitionTo(ExpenseStatus::Posted));
    }

    public function test_approved_can_transition_back_to_pending(): void
    {
        $this->assertTrue(ExpenseStatus::Approved->canTransitionTo(ExpenseStatus::Pending));
    }

    public function test_posted_can_transition_to_cancelled(): void
    {
        $this->assertTrue(ExpenseStatus::Posted->canTransitionTo(ExpenseStatus::Cancelled));
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

    public function test_approved_is_deletable(): void
    {
        $this->assertTrue(ExpenseStatus::Approved->isDeletable());
    }

    public function test_posted_is_not_deletable(): void
    {
        $this->assertFalse(ExpenseStatus::Posted->isDeletable());
    }

    public function test_cancelled_is_not_editable_or_deletable(): void
    {
        $this->assertFalse(ExpenseStatus::Cancelled->isEditable());
        $this->assertFalse(ExpenseStatus::Cancelled->isDeletable());
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

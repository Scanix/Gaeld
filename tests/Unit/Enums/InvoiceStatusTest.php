<?php

namespace Tests\Unit\Enums;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use PHPUnit\Framework\TestCase;

class InvoiceStatusTest extends TestCase
{
    public function test_draft_can_transition_to_sent(): void
    {
        $this->assertTrue(InvoiceStatus::Draft->canTransitionTo(InvoiceStatus::Sent));
    }

    public function test_draft_can_transition_to_cancelled(): void
    {
        $this->assertTrue(InvoiceStatus::Draft->canTransitionTo(InvoiceStatus::Cancelled));
    }

    public function test_draft_cannot_transition_to_paid(): void
    {
        $this->assertFalse(InvoiceStatus::Draft->canTransitionTo(InvoiceStatus::Paid));
    }

    public function test_sent_can_transition_to_paid(): void
    {
        $this->assertTrue(InvoiceStatus::Sent->canTransitionTo(InvoiceStatus::Paid));
    }

    public function test_sent_can_transition_to_overdue(): void
    {
        $this->assertTrue(InvoiceStatus::Sent->canTransitionTo(InvoiceStatus::Overdue));
    }

    public function test_overdue_can_transition_to_paid(): void
    {
        $this->assertTrue(InvoiceStatus::Overdue->canTransitionTo(InvoiceStatus::Paid));
    }

    public function test_paid_cannot_transition(): void
    {
        $this->assertEmpty(InvoiceStatus::Paid->allowedTransitions());
    }

    public function test_cancelled_cannot_transition(): void
    {
        $this->assertEmpty(InvoiceStatus::Cancelled->allowedTransitions());
    }

    public function test_only_draft_is_editable(): void
    {
        $this->assertTrue(InvoiceStatus::Draft->isEditable());
        $this->assertFalse(InvoiceStatus::Sent->isEditable());
        $this->assertFalse(InvoiceStatus::Paid->isEditable());
    }

    public function test_only_draft_is_deletable(): void
    {
        $this->assertTrue(InvoiceStatus::Draft->isDeletable());
        $this->assertFalse(InvoiceStatus::Sent->isDeletable());
        $this->assertFalse(InvoiceStatus::Paid->isDeletable());
    }
}

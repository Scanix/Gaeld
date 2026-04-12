<?php

namespace App\Domains\Expenses\Notifications;

use App\Domains\Expenses\Models\Expense;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ExpenseApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Expense $expense,
    ) {}

    /** @return string[] */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'expense_approved',
            'expense_id' => $this->expense->id,
            'url' => route('expenses.show', $this->expense),
        ];
    }
}

<?php

namespace App\Domains\Banking\Models;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_account_id',
        'bank_import_id',
        'journal_entry_id',
        'date',
        'description',
        'amount',
        'type',
        'reference',
        'debtor_name',
        'creditor_name',
        'end_to_end_id',
        'structured_reference',
        'import_hash',
        'is_reconciled',
        'matched_invoice_id',
        'matched_expense_id',
        'suggested_expense_category',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'type' => BankTransactionType::class,
            'is_reconciled' => 'boolean',
        ];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function bankImport(): BelongsTo
    {
        return $this->belongsTo(BankImport::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function matchedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'matched_invoice_id');
    }

    public function matchedExpense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'matched_expense_id');
    }

    public function bankMatches(): HasMany
    {
        return $this->hasMany(BankMatch::class);
    }
}

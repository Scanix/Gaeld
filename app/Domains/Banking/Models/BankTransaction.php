<?php

namespace App\Domains\Banking\Models;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Individual transaction row parsed from a CAMT bank statement.
 *
 * Carries debtor/creditor details, structured references, and
 * reconciliation state (matched invoice or expense, journal entry link).
 *
 * @property int $id
 * @property int $bank_account_id
 * @property int|null $bank_import_id
 * @property string|null $journal_entry_id
 * @property Carbon $date
 * @property string|null $description
 * @property string $amount
 * @property BankTransactionType $type
 * @property string|null $reference
 * @property string|null $debtor_name
 * @property string|null $creditor_name
 * @property string|null $end_to_end_id
 * @property string|null $structured_reference
 * @property string $import_hash
 * @property bool $is_reconciled
 * @property bool|null $is_personal
 * @property string|null $matched_invoice_id
 * @property string|null $matched_expense_id
 * @property string|null $suggested_expense_category
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read BankAccount $bankAccount
 * @property-read BankImport|null $bankImport
 * @property-read JournalEntry|null $journalEntry
 * @property-read Invoice|null $matchedInvoice
 * @property-read Expense|null $matchedExpense
 * @property-read Collection<int, BankMatch> $bankMatches
 */
class BankTransaction extends Model
{
    use Auditable, HasFactory;

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
        'is_personal',
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
            'is_personal' => 'boolean',
        ];
    }

    /** @return BelongsTo<BankAccount, $this> */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /** @return BelongsTo<BankImport, $this> */
    public function bankImport(): BelongsTo
    {
        return $this->belongsTo(BankImport::class);
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /** @return BelongsTo<Invoice, $this> */
    public function matchedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'matched_invoice_id');
    }

    /** @return BelongsTo<Expense, $this> */
    public function matchedExpense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'matched_expense_id');
    }

    /** @return HasMany<BankMatch, $this> */
    public function bankMatches(): HasMany
    {
        return $this->hasMany(BankMatch::class);
    }
}

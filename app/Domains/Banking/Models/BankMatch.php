<?php

namespace App\Domains\Banking\Models;

use App\Domains\Banking\Enums\BankMatchType;
use App\Domains\Invoicing\Models\Invoice;
use App\Support\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Records a link between a bank transaction and an invoice for reconciliation.
 *
 * Stores the match type (automatic / manual), a confidence score,
 * and whether the match has been confirmed by a user.
 *
 * @property int $id
 * @property int $bank_transaction_id
 * @property string $invoice_id
 * @property int $confidence
 * @property BankMatchType $match_type
 * @property bool $is_confirmed
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BankMatch extends Model
{
    use Auditable;

    protected $fillable = [
        'bank_transaction_id',
        'invoice_id',
        'confidence',
        'match_type',
        'is_confirmed',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => 'integer',
            'match_type' => BankMatchType::class,
            'is_confirmed' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}

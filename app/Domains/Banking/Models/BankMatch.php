<?php

namespace App\Domains\Banking\Models;

use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankMatch extends Model
{
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
            'is_confirmed' => 'boolean',
            'confirmed_at' => 'datetime',
        ];
    }

    public const TYPE_QR_REFERENCE = 'qr_reference';

    public const TYPE_AMOUNT_CUSTOMER = 'amount_customer';

    public const TYPE_HEURISTIC = 'heuristic';

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(BankTransaction::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}

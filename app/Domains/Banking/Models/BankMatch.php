<?php

namespace App\Domains\Banking\Models;

use App\Domains\Banking\Enums\BankMatchType;
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

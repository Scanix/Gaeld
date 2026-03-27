<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Accounting\Models\VatRate;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $invoice_id
 * @property string $description
 * @property string $quantity
 * @property string $unit_price
 * @property string $amount
 * @property string|null $vat_rate_id
 * @property string|null $vat_amount
 * @property int $sort_order
 */
class InvoiceLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'amount',
        'vat_rate_id',
        'vat_amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }

    public function calculateAndSave(): void
    {
        $this->amount = bcmul($this->quantity, $this->unit_price, 2);

        if ($this->vatRate) {
            $this->vat_amount = bcmul($this->amount, bcdiv($this->vatRate->rate, '100', 4), 2);
        }

        $this->save();
    }
}

<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Accounting\Models\VatRate;
use App\Domains\Invoicing\Enums\InvoiceLineType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single line item on an invoice (description, quantity, unit price, VAT).
 *
 * @property int $id
 * @property string $invoice_id
 * @property InvoiceLineType $type
 * @property string|null $description
 * @property string $quantity
 * @property string $unit_price
 * @property string $amount
 * @property string|null $discount_type
 * @property string|null $vat_rate_id
 * @property string|null $vat_amount
 * @property int $sort_order
 * @property-read Invoice $invoice
 * @property-read VatRate|null $vatRate
 */
class InvoiceLine extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'type',
        'discount_type',
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
            'type' => InvoiceLineType::class,
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'vat_amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<VatRate, $this> */
    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }

    public function calculateAndSave(): void
    {
        if (! $this->type->hasAmount()) {
            $this->quantity = '0.00';
            $this->unit_price = '0.00';
            $this->amount = '0.00';
            $this->vat_amount = '0.00';
            $this->vat_rate_id = null;
            $this->save();

            return;
        }

        $this->amount = Money::multiply2($this->quantity, $this->unit_price);

        if ($this->vatRate) {
            $this->vat_amount = Money::percentage($this->amount, (string) $this->vatRate->rate);
        }

        $this->save();
    }
}

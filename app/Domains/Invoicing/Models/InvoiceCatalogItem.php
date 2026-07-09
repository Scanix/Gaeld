<?php

namespace App\Domains\Invoicing\Models;

use App\Domains\Accounting\Models\VatRate;
use App\Domains\Organizations\Models\Organization;
use App\Support\Traits\Auditable;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Reusable catalog entry (product/service) that can pre-fill an invoice line,
 * scoped to an organization. A shortcut for line entry, not a hard constraint:
 * the description on an invoice line remains freely editable after selection.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property string|null $description
 * @property string|null $default_unit_price
 * @property string|null $default_vat_rate_id
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read VatRate|null $defaultVatRate
 */
class InvoiceCatalogItem extends Model
{
    use Auditable, BelongsToOrganization, HasUuids;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'default_unit_price',
        'default_vat_rate_id',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'default_unit_price' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<VatRate, $this> */
    public function defaultVatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class, 'default_vat_rate_id');
    }
}

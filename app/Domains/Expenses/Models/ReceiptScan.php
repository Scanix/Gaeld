<?php

namespace App\Domains\Expenses\Models;

use App\Domains\Expenses\Enums\ReceiptScanStatus;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Persistent record of an OCR receipt scan request.
 *
 * Created when a receipt is submitted for scanning, updated by
 * ProcessReceiptOcrJob when processing completes or fails, and
 * marked as validated when the user confirms and saves the expense.
 *
 * @property string $id
 * @property string $organization_id
 * @property int $user_id
 * @property string $scan_id
 * @property string $receipt_path
 * @property ReceiptScanStatus $status
 * @property array<string, mixed>|null $extracted_data
 * @property Carbon $expires_at
 */
class ReceiptScan extends Model
{
    use BelongsToOrganization, HasUuids;

    protected $fillable = [
        'organization_id',
        'user_id',
        'scan_id',
        'receipt_path',
        'status',
        'extracted_data',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'status'         => ReceiptScanStatus::class,
            'extracted_data' => 'array',
            'expires_at'     => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

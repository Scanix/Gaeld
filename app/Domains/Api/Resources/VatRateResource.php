<?php

namespace App\Domains\Api\Resources;

use App\Domains\Accounting\Models\VatRate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VatRate */
class VatRateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'code' => $this->code,
            'rate' => $this->rate,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
        ];
    }
}

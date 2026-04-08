<?php

namespace App\Domains\Api\Resources;

use App\Domains\Accounting\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Account */
class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type->value,
            'parent_id' => $this->parent?->uuid,
            'is_active' => $this->is_active,
            'description' => $this->description,
        ];
    }
}

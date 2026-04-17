<?php

namespace App\Domains\Accounting\Rules;

use App\Domains\Accounting\Models\CostCenter;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidCostCenterParent implements ValidationRule
{
    public function __construct(
        private readonly string $organizationId,
        private readonly ?CostCenter $current = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $parentId = (int) $value;

        $parentExists = CostCenter::query()
            ->where('organization_id', $this->organizationId)
            ->whereKey($parentId)
            ->exists();

        if (! $parentExists) {
            $fail(__('validation.exists', ['attribute' => $attribute]));

            return;
        }

        if ($this->current && $parentId === (int) $this->current->id) {
            $fail('A cost center cannot be its own parent.');

            return;
        }

        if (! $this->current) {
            return;
        }

        // Walk up the ancestry chain to ensure the selected parent is not a descendant.
        $visited = [];
        $cursorId = $parentId;

        while ($cursorId !== 0 && ! isset($visited[$cursorId])) {
            $visited[$cursorId] = true;

            if ($cursorId === (int) $this->current->id) {
                $fail('The selected parent creates a circular hierarchy.');

                return;
            }

            $cursorId = (int) (CostCenter::query()
                ->where('organization_id', $this->organizationId)
                ->whereKey($cursorId)
                ->value('parent_id') ?? 0);
        }
    }
}

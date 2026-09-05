<?php

namespace App\Domains\Payroll\Requests;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class PayrollAdjustmentRules
{
    /**
     * @return array<int, mixed>
     */
    public static function unpaidLeaveDays(Request $request): array
    {
        return [
            'nullable',
            'integer',
            'min:0',
            function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                $month = (int) $request->input('month');
                $year = (int) $request->input('year');

                if ($month < 1 || $month > 12 || $year < 1) {
                    return;
                }

                $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
                if ((int) $value > $daysInMonth) {
                    $fail(__('validation.max.numeric', [
                        'attribute' => __('app.unpaid_leave_days'),
                        'max' => $daysInMonth,
                    ]));
                }
            },
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function for(Request $request, string $organizationId): array
    {
        $selectedEmployeeIds = $request->input('employee_ids', []);
        $adjustmentEmployeeRules = [
            'required',
            'uuid',
            Rule::exists('employees', 'id')->where('organization_id', $organizationId),
        ];

        if (is_array($selectedEmployeeIds) && $selectedEmployeeIds !== []) {
            $adjustmentEmployeeRules[] = Rule::in($selectedEmployeeIds);
        }

        return [
            'adjustments' => ['nullable', 'array', 'max:500'],
            'adjustments.*.employee_id' => $adjustmentEmployeeRules,
            'adjustments.*.unpaid_leave_days' => self::unpaidLeaveDays($request),
            'adjustments.*.reimbursement_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],
        ];
    }
}

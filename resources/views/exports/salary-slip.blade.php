@php
    $employeeData = $employeeData ?? $slip->employeeDocumentData();
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('exports.salary_slip.title') }} — {{ $employeeData['first_name'] }} {{ $employeeData['last_name'] }}</title>
    @include('exports._styles')
</head>
<body>
    @include('exports._header', [
        'docTitle' => __('exports.salary_slip.title'),
        'docPeriod' => str_pad($slip->period_month, 2, '0', STR_PAD_LEFT).'/'.$slip->period_year,
        'docRef' => $employeeData['first_name'].' '.$employeeData['last_name'],
    ])

    <div class="section">
        <div class="section-title">{{ __('exports.salary_slip.employee') }}</div>
        <table class="employee-info">
            <tr><td style="width:30%;">{{ __('exports.salary_slip.name') }}</td><td>{{ $employeeData['first_name'] }} {{ $employeeData['last_name'] }}</td></tr>
                @if($employeeData['ahv_number'])
                    <tr><td>{{ __('exports.salary_slip.ahv_number') }}</td><td>{{ $employeeData['ahv_number'] }}</td></tr>
            @endif
        </table>
    </div>

    <div class="section">
        <div class="section-title">{{ __('exports.salary_slip.salary') }}</div>
        <table>
            @if(isset($slip->adjustments['base_salary']) && bccomp($slip->adjustments['base_salary'], $slip->gross_salary, 2) !== 0)
                <tr><td>{{ __('exports.salary_slip.base_salary') }}</td><td class="right">{{ number_format((float) $slip->adjustments['base_salary'], 2, '.', "'") }}</td></tr>
            @endif
            @if(isset($slip->adjustments['thirteenth_salary']) && bccomp($slip->adjustments['thirteenth_salary'], '0', 2) > 0)
                <tr><td>{{ __('exports.salary_slip.thirteenth_salary') }}</td><td class="right">{{ number_format((float) $slip->adjustments['thirteenth_salary'], 2, '.', "'") }}</td></tr>
            @endif
            @if(isset($slip->adjustments['unpaid_leave_amount']) && bccomp($slip->adjustments['unpaid_leave_amount'], '0', 2) > 0)
                <tr><td>{{ __('exports.salary_slip.unpaid_leave') }}</td><td class="right">-{{ number_format((float) $slip->adjustments['unpaid_leave_amount'], 2, '.', "'") }}</td></tr>
            @endif
            <tr>
                <td>{{ __('exports.salary_slip.gross_salary') }}</td>
                <td class="right">{{ number_format((float) $slip->gross_salary, 2, '.', "'") }}</td>
            </tr>
        </table>
    </div>

    @if(isset($slip->adjustments['reimbursement_amount']) && bccomp($slip->adjustments['reimbursement_amount'], '0', 2) > 0)
        <div class="section">
            <table>
                <tr>
                    <td>{{ __('exports.salary_slip.expense_reimbursement') }}</td>
                    <td class="right">{{ number_format((float) $slip->adjustments['reimbursement_amount'], 2, '.', "'") }}</td>
                </tr>
            </table>
        </div>
    @endif

    <div class="section">
        <div class="section-title">{{ __('exports.salary_slip.employee_deductions') }}</div>
        <table>
            @php $deductions = $slip->deductions; @endphp
            @if(isset($deductions['avs_employee']) && bccomp($deductions['avs_employee'], '0', 2) > 0)
                <tr><td>{{ __('exports.salary_slip.avs_ai_apg') }}</td><td class="right">-{{ number_format((float) $deductions['avs_employee'], 2, '.', "'") }}</td></tr>
            @endif
            @if(isset($deductions['ac_employee']) && bccomp($deductions['ac_employee'], '0', 2) > 0)
                <tr><td>{{ __('exports.salary_slip.unemployment_insurance') }}</td><td class="right">-{{ number_format((float) $deductions['ac_employee'], 2, '.', "'") }}</td></tr>
            @endif
            @if(isset($deductions['aanp_employee']) && bccomp($deductions['aanp_employee'], '0', 2) > 0)
                <tr><td>{{ __('exports.salary_slip.aanp') }}</td><td class="right">-{{ number_format((float) $deductions['aanp_employee'], 2, '.', "'") }}</td></tr>
            @endif
            @if(isset($deductions['lpp_employee']) && bccomp($deductions['lpp_employee'], '0', 2) > 0)
                <tr><td>{{ __('exports.salary_slip.pension_lpp') }}</td><td class="right">-{{ number_format((float) $deductions['lpp_employee'], 2, '.', "'") }}</td></tr>
            @endif
            @php $sourceTax = $deductions['source_tax'] ?? $slip->source_tax_amount ?? '0.00'; @endphp
            @if(bccomp((string) $sourceTax, '0', 2) > 0)
                <tr><td>{{ __('exports.salary_slip.source_tax') }}</td><td class="right">-{{ number_format((float) $sourceTax, 2, '.', "'") }}</td></tr>
            @endif
            <tr class="total-row">
                <td>{{ __('exports.salary_slip.total_deductions') }}</td>
                <td class="right">-{{ number_format((float) ($deductions['total_employee'] ?? '0'), 2, '.', "'") }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <table>
            <tr class="net-row">
                <td>{{ __('exports.salary_slip.net_salary') }}</td>
                <td class="right">{{ number_format((float) $slip->net_salary, 2, '.', "'") }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">{{ __('exports.salary_slip.employer_charges') }}</div>
        <table>
            @if(isset($deductions['avs_employer']) && bccomp($deductions['avs_employer'], '0', 2) > 0)
                <tr><td>{{ __('exports.salary_slip.avs_ai_apg_employer') }}</td><td class="right">{{ number_format((float) $deductions['avs_employer'], 2, '.', "'") }}</td></tr>
            @endif
            @if(isset($deductions['ac_employer']) && bccomp($deductions['ac_employer'], '0', 2) > 0)
                <tr><td>{{ __('exports.salary_slip.unemployment_insurance_employer') }}</td><td class="right">{{ number_format((float) $deductions['ac_employer'], 2, '.', "'") }}</td></tr>
            @endif
            @if(isset($deductions['lpp_employer']) && bccomp($deductions['lpp_employer'], '0', 2) > 0)
                <tr><td>{{ __('exports.salary_slip.pension_lpp_employer') }}</td><td class="right">{{ number_format((float) $deductions['lpp_employer'], 2, '.', "'") }}</td></tr>
            @endif
            <tr class="total-row">
                <td>{{ __('exports.salary_slip.total_employer_charges') }}</td>
                <td class="right">{{ number_format((float) ($deductions['total_employer'] ?? '0'), 2, '.', "'") }}</td>
            </tr>
        </table>
    </div>

    @include('exports._footer')
</body>
</html>

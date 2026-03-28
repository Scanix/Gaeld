<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('exports.salary_slip.title') }} — {{ $slip->employee->fullName() }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10pt; color: #1a1a1a; padding: 20mm; }
        .header { display: flex; justify-content: space-between; margin-bottom: 8mm; border-bottom: 2px solid #2563eb; padding-bottom: 4mm; }
        .header h1 { font-size: 16pt; color: #2563eb; }
        .period { font-size: 12pt; color: #4b5563; text-align: right; }
        .section { margin-bottom: 6mm; }
        .section-title { font-size: 11pt; font-weight: 700; color: #2563eb; border-bottom: 1px solid #e5e7eb; padding-bottom: 1mm; margin-bottom: 2mm; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
        th, td { padding: 2mm 3mm; text-align: left; }
        th { font-weight: 600; color: #6b7280; font-size: 9pt; }
        td { font-size: 10pt; }
        .right { text-align: right; }
        .total-row { font-weight: 700; border-top: 2px solid #2563eb; }
        .net-row { font-size: 12pt; font-weight: 700; color: #2563eb; border-top: 3px double #2563eb; }
        .employee-info td { padding: 1mm 3mm; }
        .footer { margin-top: 10mm; font-size: 8pt; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 3mm; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <h1>{{ __('exports.salary_slip.title') }}</h1>
        </div>
        <div class="period">
            {{ str_pad($slip->period_month, 2, '0', STR_PAD_LEFT) }}/{{ $slip->period_year }}
        </div>
    </div>

    <div class="section">
        <div class="section-title">{{ __('exports.salary_slip.employee') }}</div>
        <table class="employee-info">
            <tr><td style="width:30%;">{{ __('exports.salary_slip.name') }}</td><td>{{ $slip->employee->fullName() }}</td></tr>
            @if($slip->employee->ahv_number)
                <tr><td>{{ __('exports.salary_slip.ahv_number') }}</td><td>{{ $slip->employee->ahv_number }}</td></tr>
            @endif
        </table>
    </div>

    <div class="section">
        <div class="section-title">{{ __('exports.salary_slip.salary') }}</div>
        <table>
            <tr>
                <td>{{ __('exports.salary_slip.gross_salary') }}</td>
                <td class="right">{{ number_format((float) $slip->gross_salary, 2, '.', "'") }}</td>
            </tr>
        </table>
    </div>

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

    <div class="footer">
        {{ __('exports.common.generated_by') }} &mdash; {{ now()->format('d.m.Y H:i') }}
    </div>
</body>
</html>

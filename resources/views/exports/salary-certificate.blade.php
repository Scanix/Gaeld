<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('exports.salary_certificate.title') }} — {{ $certificate['employee']->fullName() }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10pt; color: #1a1a1a; padding: 20mm; }
        .header { display: flex; justify-content: space-between; margin-bottom: 8mm; border-bottom: 2px solid #2563eb; padding-bottom: 4mm; }
        .header h1 { font-size: 16pt; color: #2563eb; }
        .period { font-size: 12pt; color: #4b5563; text-align: right; }
        .notice { margin-bottom: 7mm; padding: 3mm; border: 1px solid #f59e0b; color: #92400e; font-size: 9pt; }
        .section { margin-bottom: 6mm; }
        .section-title { font-size: 11pt; font-weight: 700; color: #2563eb; border-bottom: 1px solid #e5e7eb; padding-bottom: 1mm; margin-bottom: 2mm; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 4mm; }
        th, td { padding: 2mm 3mm; text-align: left; }
        th { font-weight: 600; color: #6b7280; font-size: 9pt; }
        td { font-size: 10pt; }
        .right { text-align: right; }
        .total-row { font-weight: 700; border-top: 2px solid #2563eb; }
        .net-row { font-size: 12pt; font-weight: 700; color: #2563eb; border-top: 3px double #2563eb; }
        .footer { margin-top: 10mm; font-size: 8pt; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 3mm; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ __('exports.salary_certificate.title') }}</h1>
        <div class="period">{{ $certificate['year'] }}</div>
    </div>

    <div class="notice">{{ __('exports.salary_certificate.disclaimer') }}</div>

    <div class="section">
        <div class="section-title">{{ __('exports.salary_certificate.employee') }}</div>
        <table>
            <tr><td style="width:30%;">{{ __('exports.salary_certificate.name') }}</td><td>{{ $certificate['employee']->fullName() }}</td></tr>
            @if($certificate['employee']->ahv_number)
                <tr><td>{{ __('exports.salary_certificate.ahv_number') }}</td><td>{{ $certificate['employee']->ahv_number }}</td></tr>
            @endif
            <tr><td>{{ __('exports.salary_certificate.months_covered') }}</td><td>{{ $certificate['months_covered'] }}</td></tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">{{ __('exports.salary_certificate.employee_totals') }}</div>
        <table>
            <tr><td>{{ __('exports.salary_certificate.gross_salary') }}</td><td class="right">{{ number_format((float) $certificate['gross_salary'], 2, '.', "'") }}</td></tr>
            <tr><td>{{ __('exports.salary_certificate.avs_employee') }}</td><td class="right">-{{ number_format((float) $certificate['avs_employee'], 2, '.', "'") }}</td></tr>
            <tr><td>{{ __('exports.salary_certificate.ac_employee') }}</td><td class="right">-{{ number_format((float) $certificate['ac_employee'], 2, '.', "'") }}</td></tr>
            <tr><td>{{ __('exports.salary_certificate.aanp_employee') }}</td><td class="right">-{{ number_format((float) $certificate['aanp_employee'], 2, '.', "'") }}</td></tr>
            <tr><td>{{ __('exports.salary_certificate.lpp_employee') }}</td><td class="right">-{{ number_format((float) $certificate['lpp_employee'], 2, '.', "'") }}</td></tr>
            <tr><td>{{ __('exports.salary_certificate.source_tax') }}</td><td class="right">-{{ number_format((float) $certificate['source_tax'], 2, '.', "'") }}</td></tr>
            <tr class="total-row"><td>{{ __('exports.salary_certificate.net_salary') }}</td><td class="right">{{ number_format((float) $certificate['net_salary'], 2, '.', "'") }}</td></tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">{{ __('exports.salary_certificate.additional_payments') }}</div>
        <table>
            <tr><td>{{ __('exports.salary_certificate.reimbursements') }}</td><td class="right">{{ number_format((float) $certificate['reimbursements'], 2, '.', "'") }}</td></tr>
            <tr class="net-row"><td>{{ __('exports.salary_certificate.total_paid') }}</td><td class="right">{{ number_format((float) $certificate['total_paid'], 2, '.', "'") }}</td></tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">{{ __('exports.salary_certificate.employer_charges') }}</div>
        <table>
            <tr><td>{{ __('exports.salary_certificate.total_employer_charges') }}</td><td class="right">{{ number_format((float) $certificate['employer_charges'], 2, '.', "'") }}</td></tr>
        </table>
    </div>

    <div class="footer">{{ __('exports.common.generated_by') }} &mdash; {{ now()->format('d.m.Y H:i') }}</div>
</body>
</html>